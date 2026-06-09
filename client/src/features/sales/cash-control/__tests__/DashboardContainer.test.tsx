/**
 * @vitest-environment jsdom
 */
import React from 'react';
import { render, screen } from '@testing-library/react';
import { expect, describe, it, vi } from 'vitest';
import * as matchers from '@testing-library/jest-dom/matchers';
expect.extend(matchers);

import DashboardContainer from '../DashboardContainer';
import * as swr from 'swr';
import * as authContext from '../../../../contexts/AuthContext';

vi.mock('swr');
vi.mock('../../../../contexts/AuthContext', () => ({
    useAuth: vi.fn()
}));

vi.mock('recharts', async () => {
    const OriginalModule = await vi.importActual('recharts');
    return {
        ...OriginalModule,
        ResponsiveContainer: ({ children }: any) => <div className="recharts-responsive-container">{children}</div>,
    };
});

describe('DashboardContainer Analytics', () => {
    it('renders global metrics even if a buggy module drops out of the matrix due to circuit breaker', () => {
        // Mock Auth Context showing 2 active modules
        vi.spyOn(authContext, 'useAuth').mockReturnValue({
            user: { 
                id: 1, 
                name: 'Test',
                business: {
                    active_modules: ['pharmacy', 'buggy-module']
                }
            }
        } as any);

        // Mock SWR returning null for the buggy-module
        const useSwrMock = vi.spyOn(swr, 'default');
        useSwrMock.mockReturnValue({
            data: {
                global: { revenue: 14500, profit: 4200 },
                modules: {
                    'pharmacy': { revenue: 6000, volume: 120, color: '#10B981' },
                    'buggy-module': null // Circuit Breaker tripped
                }
            },
            error: null,
            isLoading: false,
            mutate: vi.fn(),
            isValidating: false
        });

        render(<DashboardContainer />);

        // Assert global UI metric survived the module failure
        const globalRevenue = screen.getByTestId('global-revenue');
        expect(globalRevenue).toHaveTextContent('$14,500');

        // Assert the filter pill was dynamically created for both
        expect(screen.getByText('pharmacy Matrix')).toBeInTheDocument();
        expect(screen.getByText('buggy module Matrix')).toBeInTheDocument();
    });
});
