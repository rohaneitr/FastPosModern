'use client';

import React, { useState, useEffect, useCallback } from 'react';
import { Clock, Play, CheckCircle, ChefHat } from 'lucide-react';
// import Echo from 'laravel-echo'; // Assuming Echo is available in the project

interface KotItem {
    name: string;
    qty: number;
    modifier?: string;
}

export interface KotTicket {
    id: number;
    session_id: number;
    ticket_number: string;
    table_number: string;
    status: 'Pending' | 'Preparing' | 'Ready' | 'Served';
    items: KotItem[];
    created_at: string;
}

interface KdsDashboardProps {
    initialTickets?: KotTicket[];
    businessId: number;
}

const formatElapsedTime = (createdAt: string) => {
    const start = new Date(createdAt).getTime();
    const now = new Date().getTime();
    const diffInSeconds = Math.floor((now - start) / 1000);
    
    if (diffInSeconds < 60) return `${diffInSeconds}s ago`;
    const minutes = Math.floor(diffInSeconds / 60);
    if (minutes < 60) return `${minutes}m ago`;
    const hours = Math.floor(minutes / 60);
    return `${hours}h ${minutes % 60}m ago`;
};

const ElapsedTimer: React.FC<{ createdAt: string }> = ({ createdAt }) => {
    const [timeStr, setTimeStr] = useState(formatElapsedTime(createdAt));

    useEffect(() => {
        const interval = setInterval(() => {
            setTimeStr(formatElapsedTime(createdAt));
        }, 1000); // Update every second for active feel

        return () => clearInterval(interval);
    }, [createdAt]);

    return (
        <span className="flex items-center gap-1 text-sm font-medium" data-testid="elapsed-time">
            <Clock size={14} />
            {timeStr}
        </span>
    );
};

export const KdsDashboardContainer: React.FC<KdsDashboardProps> = ({ initialTickets = [], businessId }) => {
    const [tickets, setTickets] = useState<KotTicket[]>(initialTickets);

    useEffect(() => {
        // In a real app, this would use window.Echo from a global provider
        // const channel = window.Echo.private(`business.${businessId}.kitchen`);
        // channel.listen('KotTicketEmitted', (e: any) => {
        //     handleNewTicket(e.payload);
        // });

        // Mocking for testing purposes when window is available
        const handleCustomEvent = (e: Event) => {
            const customEvent = e as CustomEvent;
            handleNewTicket(customEvent.detail);
        };

        window.addEventListener('mock-kot-ticket-emitted', handleCustomEvent);

        return () => {
            // channel.stopListening('KotTicketEmitted');
            window.removeEventListener('mock-kot-ticket-emitted', handleCustomEvent);
        };
    }, [businessId]);

    const handleNewTicket = useCallback((payload: any) => {
        const newTicket: KotTicket = {
            id: Date.now(), // Generate a temporary ID if not provided
            session_id: payload.session_id,
            ticket_number: payload.ticket_number,
            table_number: payload.table_number || 'T-??', // Should be passed in payload
            status: 'Pending',
            items: payload.items,
            created_at: new Date().toISOString()
        };

        setTickets(prev => [newTicket, ...prev]);

        // Play audio alert
        try {
            const audio = new Audio('/assets/sounds/kitchen-alert.mp3');
            audio.play().catch(e => console.warn('Audio play blocked by browser policy:', e));
        } catch (error) {
            console.error('Failed to play audio:', error);
        }
    }, []);

    const updateTicketStatus = async (id: number, newStatus: 'Preparing' | 'Ready') => {
        // Optimistic update
        setTickets(prev => 
            prev.map(t => t.id === id ? { ...t, status: newStatus } : t)
        );

        try {
            // API call would go here
            // await fetch(`/api/v1/restaurant/kot/${id}/status`, {
            //     method: 'PATCH',
            //     headers: { 'Content-Type': 'application/json' },
            //     body: JSON.stringify({ status: newStatus })
            // });
        } catch (error) {
            console.error('Failed to update status:', error);
            // Revert on failure (omitted for brevity)
        }
    };

    const getStatusStyles = (status: string) => {
        switch (status) {
            case 'Pending':
                return 'border-red-500/50 bg-red-500/5 animate-pulse-subtle';
            case 'Preparing':
                return 'border-amber-500/50 bg-amber-500/5';
            case 'Ready':
                return 'border-emerald-500/50 bg-emerald-500/5';
            default:
                return 'border-gray-200 dark:border-white/10';
        }
    };

    return (
        <div className="p-6 h-full min-h-screen bg-gray-50 dark:bg-gray-900/40">
            <header className="mb-8 flex items-center justify-between">
                <div>
                    <h1 className="text-3xl font-black flex items-center gap-3 tracking-tight text-gray-900 dark:text-white">
                        <ChefHat className="text-indigo-500" size={32} />
                        Kitchen Display System
                    </h1>
                    <p className="text-gray-500 dark:text-gray-400 mt-1">Real-time KOT synchronization</p>
                </div>
            </header>

            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                {tickets.filter(t => t.status !== 'Served').map(ticket => (
                    <div 
                        key={ticket.id} 
                        data-testid={`kot-card-${ticket.id}`}
                        className={`backdrop-blur-xl border-2 rounded-2xl p-5 flex flex-col shadow-sm transition-all duration-300 ${getStatusStyles(ticket.status)}`}
                    >
                        <div className="flex justify-between items-start mb-4 pb-4 border-b border-gray-200 dark:border-gray-700/50">
                            <div>
                                <h3 className="text-2xl font-black text-gray-900 dark:text-white leading-none">
                                    {ticket.table_number}
                                </h3>
                                <p className="text-xs text-gray-500 dark:text-gray-400 mt-1 uppercase tracking-wider font-semibold">
                                    {ticket.ticket_number}
                                </p>
                            </div>
                            <div className={`px-3 py-1 rounded-full text-xs font-bold ${
                                ticket.status === 'Pending' ? 'bg-red-500 text-white animate-pulse' : 
                                ticket.status === 'Preparing' ? 'bg-amber-500 text-white' : 
                                'bg-emerald-500 text-white'
                            }`}>
                                {ticket.status.toUpperCase()}
                            </div>
                        </div>

                        <div className="flex-grow overflow-y-auto mb-4 min-h-[150px]">
                            <ul className="space-y-3">
                                {ticket.items.map((item, idx) => (
                                    <li key={idx} className="flex flex-col border-l-2 border-gray-300 dark:border-gray-600 pl-3">
                                        <div className="flex justify-between items-start">
                                            <span className="font-semibold text-gray-900 dark:text-gray-100 text-lg leading-tight">
                                                {item.name}
                                            </span>
                                            <span className="font-black text-indigo-600 dark:text-indigo-400 text-lg">
                                                x{item.qty}
                                            </span>
                                        </div>
                                        {item.modifier && (
                                            <span className="text-sm font-medium text-amber-600 dark:text-amber-500 mt-1 bg-amber-50 dark:bg-amber-500/10 inline-block px-2 py-0.5 rounded">
                                                {item.modifier}
                                            </span>
                                        )}
                                    </li>
                                ))}
                            </ul>
                        </div>

                        <div className="mt-auto pt-4 border-t border-gray-200 dark:border-gray-700/50 flex items-center justify-between">
                            <div className={ticket.status === 'Pending' ? 'text-red-500' : 'text-gray-500 dark:text-gray-400'}>
                                <ElapsedTimer createdAt={ticket.created_at} />
                            </div>
                            
                            <div className="flex gap-2">
                                {ticket.status === 'Pending' && (
                                    <button 
                                        onClick={() => updateTicketStatus(ticket.id, 'Preparing')}
                                        className="flex items-center gap-1 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg font-bold text-sm transition-colors shadow-lg shadow-indigo-500/30"
                                    >
                                        <Play size={16} /> Start
                                    </button>
                                )}
                                {ticket.status === 'Preparing' && (
                                    <button 
                                        onClick={() => updateTicketStatus(ticket.id, 'Ready')}
                                        className="flex items-center gap-1 bg-emerald-500 hover:bg-emerald-600 text-white px-4 py-2 rounded-lg font-bold text-sm transition-colors shadow-lg shadow-emerald-500/30"
                                    >
                                        <CheckCircle size={16} /> Ready
                                    </button>
                                )}
                            </div>
                        </div>
                    </div>
                ))}
                
                {tickets.filter(t => t.status !== 'Served').length === 0 && (
                    <div className="col-span-full flex flex-col items-center justify-center p-12 text-gray-400 dark:text-gray-500 border-2 border-dashed border-gray-300 dark:border-gray-700 rounded-3xl backdrop-blur-sm">
                        <ChefHat size={64} className="mb-4 opacity-50" />
                        <h3 className="text-xl font-bold">Kitchen is clear</h3>
                        <p>Waiting for new orders...</p>
                    </div>
                )}
            </div>
            
            <style jsx global>{`
                .animate-pulse-subtle {
                    animation: pulse-subtle 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
                }
                @keyframes pulse-subtle {
                    0%, 100% { opacity: 1; border-color: rgba(239, 68, 68, 0.3); box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
                    50% { opacity: .95; border-color: rgba(239, 68, 68, 0.8); box-shadow: 0 0 15px 0 rgba(239, 68, 68, 0.2); }
                }
            `}</style>
        </div>
    );
};
