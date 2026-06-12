/**
 * @vitest-environment jsdom
 */
import React from 'react';
import { render, screen, fireEvent, act, cleanup } from '@testing-library/react';
import { expect, describe, it, vi, beforeEach, afterEach } from 'vitest';
import * as matchers from '@testing-library/jest-dom/matchers';
expect.extend(matchers);

import { KdsDashboardContainer } from '../KdsDashboardContainer';

describe('KdsDashboardInterface', () => {
    let audioPlayMock: any;

    beforeEach(() => {
        audioPlayMock = vi.fn().mockResolvedValue(undefined);
        global.Audio = class {
            play() { return audioPlayMock(); }
        } as any;

        vi.useFakeTimers();
        // Default base time
        vi.setSystemTime(new Date('2026-06-08T12:05:00Z'));
    });

    afterEach(() => {
        vi.restoreAllMocks();
        vi.useRealTimers();
        cleanup();
    });

    it('renders empty state initially', () => {
        render(<KdsDashboardContainer businessId={1} initialTickets={[]} />);
        expect(screen.getByText('Kitchen is clear')).toBeInTheDocument();
    });

    it('WebSocket Injection Test: dynamically paints a new card node upon event without manual refresh', async () => {
        render(<KdsDashboardContainer businessId={1} initialTickets={[]} />);
        
        expect(screen.queryByText('Table 4')).not.toBeInTheDocument();

        // Simulate WebSocket pushing a KotTicketEmitted event
        const mockPayload = {
            session_id: 101,
            ticket_number: 'KOT-20260608-1',
            table_number: 'Table 4',
            items: [
                { name: 'Burger', qty: 2, modifier: 'No Onions' },
                { name: 'Fries', qty: 1 }
            ]
        };

        act(() => {
            const event = new CustomEvent('mock-kot-ticket-emitted', { detail: mockPayload });
            window.dispatchEvent(event);
        });

        // Assert DOM instantly paints new card node
        expect(screen.getByText('Table 4')).toBeInTheDocument();
        expect(screen.getByText('KOT-20260608-1')).toBeInTheDocument();
        expect(screen.getByText('Burger')).toBeInTheDocument();
        expect(screen.getByText('x2')).toBeInTheDocument();
        expect(screen.getByText('No Onions')).toBeInTheDocument();
        
        // Assert audio alert was triggered
        expect(audioPlayMock).toHaveBeenCalled();
    });

    it('Elapsed Time Monotonic Tick: correctly counts and formats elapsed time', () => {
        const fiveMinutesAgo = new Date('2026-06-08T12:00:00Z').toISOString();
        
        const initialTickets: any[] = [{
            id: 1,
            session_id: 101,
            ticket_number: 'KOT-1',
            table_number: 'T-1',
            status: 'Pending',
            items: [],
            created_at: fiveMinutesAgo
        }];

        render(<KdsDashboardContainer businessId={1} initialTickets={initialTickets} />);
        
        const timerElement = screen.getByTestId('elapsed-time');
        
        // At 12:05:00, 12:00:00 was exactly 5m ago
        expect(timerElement).toHaveTextContent('5m ago');

        // Advance time by 60 seconds (to 12:06:00)
        act(() => {
            vi.advanceTimersByTime(60000);
        });

        // Timer should update to 6m ago
        expect(timerElement).toHaveTextContent('6m ago');
    });

    it('Stateful Action Transitions: optimistic status update on click', () => {
        const initialTickets: any[] = [{
            id: 1,
            session_id: 101,
            ticket_number: 'KOT-1',
            table_number: 'T-1',
            status: 'Pending',
            items: [],
            created_at: new Date().toISOString()
        }];

        render(<KdsDashboardContainer businessId={1} initialTickets={initialTickets} />);
        
        expect(screen.getByText('PENDING')).toBeInTheDocument();
        
        const startButton = screen.getByText(/Start/i);
        fireEvent.click(startButton);

        // Optimistic update changes status to Preparing instantly
        expect(screen.getByText('PREPARING')).toBeInTheDocument();
        expect(screen.queryByText('PENDING')).not.toBeInTheDocument();
        
        // Button changes from Start to Ready
        expect(screen.getByText(/Ready/i)).toBeInTheDocument();
    });
});
