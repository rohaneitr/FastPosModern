/**
 * @vitest-environment jsdom
 */
import React from 'react';
import { render, screen, fireEvent, act, cleanup, waitFor } from '@testing-library/react';
import { expect, describe, it, vi, beforeEach, afterEach } from 'vitest';
import * as matchers from '@testing-library/jest-dom/matchers';
expect.extend(matchers);

import { TenantLedgerTable, DestructiveConfirmationModal } from '../tenants/TenantLedgerComponents';
import type { Tenant, PaginatedResponse } from '../tenants/TenantLedgerComponents';

const mockTenants: Tenant[] = [
    {
        id: 1, name: 'Acme Corp', email: 'admin@acme.com', status: 'active',
        plan_name: 'Premium', valid_until: '2027-01-01', active_devices: 3, created_at: '2026-01-01'
    },
    {
        id: 2, name: 'Beta Inc', email: 'admin@beta.com', status: 'active',
        plan_name: 'Basic', valid_until: '2026-12-01', active_devices: 1, created_at: '2026-03-15'
    }
];

const mockPaginatedResponse: PaginatedResponse = {
    data: mockTenants,
    current_page: 1,
    last_page: 1,
    total: 2,
    per_page: 50
};

describe('SuperAdminDashboard', () => {
    beforeEach(() => {
        vi.useFakeTimers();
    });

    afterEach(() => {
        vi.restoreAllMocks();
        vi.useRealTimers();
        cleanup();
    });

    it('Re-Auth Modal Intercept: suspend click renders modal, does NOT fire API', async () => {
        const fetchMock = vi.fn().mockResolvedValue(mockPaginatedResponse);
        const suspendMock = vi.fn();

        // Render and wait for initial data load
        await act(async () => {
            render(<TenantLedgerTable fetchTenants={fetchMock} onSuspend={suspendMock} />);
        });

        // Wait for debounce + data load
        await act(async () => {
            vi.advanceTimersByTime(350);
        });

        // Verify tenants rendered
        expect(screen.getByText('Acme Corp')).toBeInTheDocument();

        // Click suspend on Acme Corp
        const suspendBtn = screen.getByTestId('suspend-btn-1');
        fireEvent.click(suspendBtn);

        // Assert the destructive modal is NOW in the DOM
        expect(screen.getByTestId('destructive-modal')).toBeInTheDocument();
        expect(screen.getByText(/Type/i)).toBeInTheDocument();
        expect(screen.getByText('"Acme Corp"')).toBeInTheDocument();

        // Assert the suspend API was NOT called
        expect(suspendMock).not.toHaveBeenCalled();
    });

    it('Debounced Search Efficiency: rapid typing triggers only 1 API call after debounce', async () => {
        const fetchMock = vi.fn().mockResolvedValue(mockPaginatedResponse);
        const suspendMock = vi.fn();

        await act(async () => {
            render(<TenantLedgerTable fetchTenants={fetchMock} onSuspend={suspendMock} />);
        });

        // Wait for initial load (debounce fires with empty string)
        await act(async () => {
            vi.advanceTimersByTime(350);
        });

        // Reset call count after initial load
        fetchMock.mockClear();

        const searchInput = screen.getByTestId('tenant-search-input');

        // Simulate rapid typing of 5 characters within 100ms (way under 300ms debounce)
        await act(async () => {
            fireEvent.change(searchInput, { target: { value: 'A' } });
            vi.advanceTimersByTime(20);
            fireEvent.change(searchInput, { target: { value: 'Ac' } });
            vi.advanceTimersByTime(20);
            fireEvent.change(searchInput, { target: { value: 'Acm' } });
            vi.advanceTimersByTime(20);
            fireEvent.change(searchInput, { target: { value: 'Acme' } });
            vi.advanceTimersByTime(20);
            fireEvent.change(searchInput, { target: { value: 'Acme ' } });
        });

        // At this point, only 100ms have passed — debounce has NOT fired yet
        expect(fetchMock).not.toHaveBeenCalled();

        // Advance past the 300ms debounce threshold
        await act(async () => {
            vi.advanceTimersByTime(300);
        });

        // Assert exactly 1 API call with the final debounced value
        expect(fetchMock).toHaveBeenCalledTimes(1);
        expect(fetchMock).toHaveBeenCalledWith(1, 'Acme ');
    });
});
