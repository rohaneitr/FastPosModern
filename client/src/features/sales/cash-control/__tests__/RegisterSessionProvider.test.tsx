// @vitest-environment jsdom
import React from 'react';
import { render, screen, waitFor } from '@testing-library/react';
import { describe, it, expect, beforeEach, vi } from 'vitest';
import { RegisterSessionProvider, useCashControl } from '../RegisterSessionProvider';
import { cashControlApi } from '../api';
import api from '../../../../lib/api';
import { getOrCreateDeviceFingerprint } from '../utils';

// Mock dependencies
vi.mock('../api', () => ({
    cashControlApi: {
        fetchRegisterStatus: vi.fn(),
        openRegisterSession: vi.fn(),
        suspendRegisterSession: vi.fn(),
        closeRegisterSession: vi.fn()
    }
}));
vi.mock('../../../../lib/api', () => {
    const mockApi = {
        defaults: { headers: { common: {} } },
        interceptors: {
            response: {
                use: vi.fn().mockReturnValue(1),
                eject: vi.fn()
            }
        }
    };
    return {
        default: mockApi,
        ...mockApi
    };
});
vi.mock('../utils', () => ({
    getOrCreateDeviceFingerprint: vi.fn()
}));

const MockChild = () => {
    const { stage, deviceHash } = useCashControl();
    return (
        <div data-testid="child-content">
            <span data-testid="stage">{stage}</span>
            <span data-testid="hash">{deviceHash}</span>
        </div>
    );
};

describe('RegisterSessionProvider', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        api.defaults.headers.common = {};
    });

    it('initializes in BOOTSTRAPPING phase, transitions to DRAWER_LOCKED, and sets device hash header', async () => {
        const mockHash = 'mocked-sha256-hash-123';
        (getOrCreateDeviceFingerprint as any).mockResolvedValue(mockHash);

        (cashControlApi.fetchRegisterStatus as any).mockResolvedValue({
            is_open: false,
            settings: { pos_enforce_device_lock: true, pos_enforce_strict_cash_control: true },
            register: null
        });

        render(
            <RegisterSessionProvider>
                <MockChild />
            </RegisterSessionProvider>
        );

        // 1. Assert BOOTSTRAPPING phase renders the skeleton loader
        expect(screen.getByText('Initializing Secure POS Workspace...')).toBeInTheDocument();
        expect(screen.queryByTestId('child-content')).not.toBeInTheDocument();

        // 2. Wait for async resolution
        await waitFor(() => {
            // Since it's DRAWER_LOCKED, VaultInterceptorOverlay renders, NOT MockChild
            expect(screen.getByText('Drawer Locked')).toBeInTheDocument();
        });

        // 3. Assert children are short-circuited
        expect(screen.queryByTestId('child-content')).not.toBeInTheDocument();

        // 4. Assert X-Device-Hash header was successfully appended to the global axios instance
        expect(api.defaults.headers.common['X-Device-Hash']).toBe(mockHash);
    });

    it('transitions to WORKSPACE_ACTIVE if strict cash control is bypassed', async () => {
        const mockHash = 'mocked-bypass-hash';
        (getOrCreateDeviceFingerprint as any).mockResolvedValue(mockHash);

        (cashControlApi.fetchRegisterStatus as any).mockResolvedValue({
            is_open: false,
            settings: { pos_enforce_device_lock: true, pos_enforce_strict_cash_control: false },
            register: null
        });

        render(
            <RegisterSessionProvider>
                <MockChild />
            </RegisterSessionProvider>
        );

        await waitFor(() => {
            expect(screen.getByTestId('stage')).toHaveTextContent('WORKSPACE_ACTIVE');
        });
    });

    it('transitions to SUSPENDED_COUNTING if register status is suspending', async () => {
        const mockHash = 'mocked-suspend-hash';
        (getOrCreateDeviceFingerprint as any).mockResolvedValue(mockHash);

        (cashControlApi.fetchRegisterStatus as any).mockResolvedValue({
            is_open: true,
            settings: { pos_enforce_device_lock: true, pos_enforce_strict_cash_control: true },
            register: { status: 'suspending' }
        });

        render(
            <RegisterSessionProvider>
                <MockChild />
            </RegisterSessionProvider>
        );

        await waitFor(() => {
            expect(screen.getByText('Z-Report: Physical Cash Count')).toBeInTheDocument();
        });
        expect(screen.queryByTestId('child-content')).not.toBeInTheDocument();
    });

    it('demotes stage to DRAWER_LOCKED upon receiving a 422 remote eviction response via interceptor', async () => {
        const mockHash = 'mocked-eviction-hash';
        (getOrCreateDeviceFingerprint as any).mockResolvedValue(mockHash);

        (cashControlApi.fetchRegisterStatus as any).mockResolvedValue({
            is_open: true,
            settings: { pos_enforce_device_lock: true, pos_enforce_strict_cash_control: true },
            register: { status: 'open' }
        });

        render(
            <RegisterSessionProvider>
                <MockChild />
            </RegisterSessionProvider>
        );

        await waitFor(() => {
            expect(screen.getByTestId('stage')).toHaveTextContent('WORKSPACE_ACTIVE');
        });

        const interceptorError = {
            response: {
                status: 422,
                data: { message: 'FPM Security: POS checkout blocked. Cash register drawer is closed.' }
            }
        };

        const [_, errorHandler] = (api.interceptors.response.use as any).mock.calls[0];
        
        try {
            await errorHandler(interceptorError);
        } catch (e) {
            // Promise.reject is expected
        }

        // Assert the stage forcefully demoted without a page reload (child is unmounted, drawer locked UI shown)
        await waitFor(() => {
            expect(screen.getByText('Drawer Locked')).toBeInTheDocument();
        });
        expect(screen.queryByTestId('child-content')).not.toBeInTheDocument();
    });
});
