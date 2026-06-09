import React, { useEffect, useState } from 'react';
import toast, { Toaster } from 'react-hot-toast';

export const NotificationCenter: React.FC = () => {
    const [businessId, setBusinessId] = useState<number | null>(null);

    // Mocking the extraction of active tenant context for the channel subscription
    useEffect(() => {
        // In a real application, this would fetch the active user's business context from Zustand or AuthContext.
        // We simulate retrieving the active business ID.
        setBusinessId(1); 
    }, []);

    useEffect(() => {
        if (!businessId) return;

        // Ensure window.Echo is available (from laravel-echo and pusher-js setup)
        if (typeof window !== 'undefined' && (window as any).Echo) {
            const channelName = `business.${businessId}`;
            const channel = (window as any).Echo.private(channelName);

            channel.listen('.import.completed', (e: any) => {
                if (e.status === 'completed') {
                    toast.success(
                        <div className="flex flex-col">
                            <span className="font-bold">Import Finished Successfully</span>
                            <span className="text-sm">Batch ID: #{e.import_id} is completely ingested.</span>
                        </div>,
                        {
                            duration: 8000,
                            icon: '🚀',
                            style: {
                                background: '#10B981',
                                color: '#fff',
                            },
                        }
                    );
                } else if (e.status === 'partial_success' || e.status === 'failed') {
                    toast.error(
                        <div className="flex flex-col">
                            <span className="font-bold">Import Finished with Errors</span>
                            <span className="text-sm">Batch ID: #{e.import_id} contains malformed rows. Review needed!</span>
                        </div>,
                        {
                            duration: 10000,
                            icon: '⚠️',
                            style: {
                                background: '#EF4444',
                                color: '#fff',
                            },
                        }
                    );
                }
            });

            return () => {
                (window as any).Echo.leave(channelName);
            };
        }
    }, [businessId]);

    return (
        <>
            <Toaster position="top-right" reverseOrder={false} />
            {/* The Notification Center runs silently in the background layout to capture broad events */}
        </>
    );
};
