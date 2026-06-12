import React from 'react';
import { KdsDashboardContainer } from './KdsDashboardContainer';

export default function KdsPage({ params }: { params: { domain: string } }) {
    // In a real application, you might fetch initial active tickets server-side here
    // based on the business ID associated with the domain.
    // For this implementation, we will pass an empty array and let the container handle real-time logic.

    return (
        <main className="min-h-screen">
            <KdsDashboardContainer businessId={1} initialTickets={[]} />
        </main>
    );
}
