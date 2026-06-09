/**
 * @vitest-environment jsdom
 */
import React from 'react';
import { render, screen } from '@testing-library/react';
import { expect, describe, it, vi } from 'vitest';
import * as matchers from '@testing-library/jest-dom/matchers';
expect.extend(matchers);

import CustomerPortalDashboard from '../../../app/[domain]/customer/dashboard/page';
import * as swr from 'swr';
import * as navigation from 'next/navigation';

vi.mock('swr');
vi.mock('next/navigation', () => ({
    useRouter: () => ({ push: vi.fn() })
}));

describe('CustomerPortalDashboard', () => {
    it('renders empty state gracefully without throwing null pointer exceptions', () => {
        // Mock SWR returning empty arrays
        const useSwrMock = vi.spyOn(swr, 'default');
        useSwrMock.mockReturnValue({
            data: {
                kpis: { wallet_balance: '0.00', loyalty_points: '0' },
                recent_invoices: [],
                diagnostic_reports: []
            },
            error: null,
            isLoading: false,
            mutate: vi.fn(),
            isValidating: false
        } as any);

        render(<CustomerPortalDashboard />);

        // Assert empty state is compliant
        const emptyState = screen.getByTestId('empty-medical-state');
        expect(emptyState).toBeInTheDocument();
        expect(emptyState).toHaveTextContent('No medical logs or wallet activity recorded yet.');
    });

    it('renders the cryptographically signed link for Published reports', () => {
        // Mock SWR returning a published report
        const useSwrMock = vi.spyOn(swr, 'default');
        useSwrMock.mockReturnValue({
            data: {
                kpis: { wallet_balance: '0.00', loyalty_points: '0' },
                recent_invoices: [],
                diagnostic_reports: [
                    { id: 999, test_type: 'Pathology', status: 'Published', created_at: '2026-06-08T12:00:00Z' }
                ]
            },
            error: null,
            isLoading: false,
            mutate: vi.fn(),
            isValidating: false
        } as any);

        render(<CustomerPortalDashboard />);

        // Assert anchor payload contains the required security string
        const downloadLink = screen.getByTestId('download-report-999');
        expect(downloadLink).toBeInTheDocument();
        expect(downloadLink).toHaveAttribute('href', expect.stringContaining('signature=crypto_token_mock'));
        expect(downloadLink).toHaveAttribute('href', expect.stringContaining('token=REPORT-999'));
    });
});
