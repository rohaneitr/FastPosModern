/**
 * @vitest-environment jsdom
 */
import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import { expect, describe, it, vi, beforeEach } from 'vitest';
import * as matchers from '@testing-library/jest-dom/matchers';
expect.extend(matchers);

import DashboardPage from '../../../../app/[domain]/(dashboards)/user/dashboard/page';
import * as swr from 'swr';

// Mock SWR and Recharts
vi.mock('swr');
vi.mock('recharts', async () => {
    const OriginalModule = await vi.importActual('recharts');
    return {
        ...OriginalModule,
        ResponsiveContainer: ({ children }: any) => <div className="recharts-responsive-container">{children}</div>,
    };
});

describe('DashboardAnalyticsUI', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('syncs timeline query parameters with SWR fetcher', () => {
        const useSwrMock = vi.spyOn(swr, 'default');
        useSwrMock.mockReturnValue({
            data: {
                kpis: {
                    gross_revenue: 1000,
                    revenue_variance: 5,
                    cogs: 500,
                    cogs_variance: -2,
                    gross_profit: 500,
                    profit_variance: 7,
                    net_margin: 50,
                    margin_variance: 1,
                },
                timeline: []
            },
            error: null,
            isLoading: false,
            mutate: vi.fn(),
            isValidating: false
        });

        render(<DashboardPage />);
        
        // Assert initial 30d
        expect(useSwrMock).toHaveBeenCalledWith('/api/v1/analytics/overview?range=30d', expect.any(Function));

        const select = screen.getByTestId('timeline-filter');
        fireEvent.change(select, { target: { value: 'today' } });

        // Assert query parameter updated
        expect(useSwrMock).toHaveBeenCalledWith('/api/v1/analytics/overview?range=today', expect.any(Function));
    });

    it('enforces structural skeleton lock and defers recharts during loading', () => {
        const useSwrMock = vi.spyOn(swr, 'default');
        useSwrMock.mockReturnValue({
            data: undefined,
            error: null,
            isLoading: true,
            mutate: vi.fn(),
            isValidating: false
        });

        const { container } = render(<DashboardPage />);

        // Assert skeleton UI is present
        const skeletons = container.querySelectorAll('.skeleton-pulse');
        expect(skeletons.length).toBeGreaterThan(0);

        // Assert physical recharts node is deferred/absent
        const rechartsNode = container.querySelector('.recharts-responsive-container');
        expect(rechartsNode).toBeNull();
    });
});
