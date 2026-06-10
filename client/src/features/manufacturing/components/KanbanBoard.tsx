'use client';

import React, { useEffect, useState } from 'react';
import { useKanbanStore, OrderStatus, ProductionOrder } from '../store/useKanbanStore';
import { useEcho } from '@/hooks/useEcho';
import api from '@/lib/api';

const COLUMNS: OrderStatus[] = ['Draft', 'Scheduled', 'In-Progress', 'Completed'];

export const KanbanBoard = ({ businessId }: { businessId: number }) => {
    const { orders, setOrders, moveOrderOptimistic, rollbackOrder, reconcileOrderFromSocket, setProcessing } = useKanbanStore();
    const { echo } = useEcho();
    const [draggedOrder, setDraggedOrder] = useState<ProductionOrder | null>(null);

    // Initial Fetch
    useEffect(() => {
        api.get('/manufacturing/orders').then(res => setOrders(res.data.data || res.data)).catch(console.error);
    }, [setOrders]);

    // Socket Reconciler
    useEffect(() => {
        if (!echo) return;
        const channel = echo.private(`business.${businessId}.manufacturing`);
        
        channel.listen('.ProductionOrderUpdatedEvent', (e: any) => {
            reconcileOrderFromSocket(e.order);
        });

        return () => {
            channel.stopListening('.ProductionOrderUpdatedEvent');
            echo.leaveChannel(`business.${businessId}.manufacturing`);
        };
    }, [echo, businessId, reconcileOrderFromSocket]);

    const onDragStart = (e: React.DragEvent, order: ProductionOrder) => {
        if (order.status === 'Completed' || order.isProcessing) {
            e.preventDefault(); // Lock completed or processing cards
            return;
        }
        setDraggedOrder(order);
    };

    const onDrop = async (e: React.DragEvent, targetStatus: OrderStatus) => {
        e.preventDefault();
        if (!draggedOrder) return;
        
        const originalStatus = draggedOrder.status;
        if (originalStatus === targetStatus) return;

        // Optimistic UI Update
        moveOrderOptimistic(draggedOrder.id, targetStatus);

        try {
            // Depending on status, we might need a modal. For simplicity, direct patch for Draft -> Scheduled -> In-Progress
            if (targetStatus === 'Completed') {
                // Handle complex execution modal logic externally, but for Kanban demo:
                await api.post(`/manufacturing/orders/${draggedOrder.id}/complete`);
            } else {
                await api.patch(`/manufacturing/orders/${draggedOrder.id}/status`, { status: targetStatus });
            }
            setProcessing(draggedOrder.id, false);
        } catch (error: any) {
            // 409 Conflict or 422 Unprocessable Entity

            rollbackOrder(draggedOrder.id, originalStatus);
            alert(error.response?.data?.message || 'Failed to update order status. Rolling back.');
        } finally {
            setDraggedOrder(null);
        }
    };

    const getColumnStyles = (status: OrderStatus) => {
        switch(status) {
            case 'Draft': return 'border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50';
            case 'Scheduled': return 'border-blue-200 dark:border-blue-900/50 bg-blue-50 dark:bg-blue-900/20';
            case 'In-Progress': return 'border-amber-200 dark:border-amber-900/50 bg-amber-50 dark:bg-amber-900/20';
            case 'Completed': return 'border-emerald-200 dark:border-emerald-900/50 bg-emerald-50 dark:bg-emerald-900/20';
            default: return '';
        }
    };

    return (
        <div className="flex gap-6 h-full min-h-[700px] overflow-x-auto p-4 custom-scrollbar">
            {COLUMNS.map(columnStatus => (
                <div 
                    key={columnStatus}
                    className={`flex-1 min-w-[300px] rounded-2xl border-2 p-4 flex flex-col gap-4 ${getColumnStyles(columnStatus)}`}
                    onDragOver={(e) => e.preventDefault()}
                    onDrop={(e) => onDrop(e, columnStatus)}
                >
                    <h3 className="font-black text-lg text-gray-800 dark:text-gray-100 uppercase tracking-wider mb-2 flex items-center justify-between">
                        {columnStatus}
                        <span className="bg-white dark:bg-black/40 text-xs px-2 py-1 rounded-full border border-gray-200 dark:border-gray-700">
                            {orders.filter(o => o.status === columnStatus).length}
                        </span>
                    </h3>

                    <div className="flex-1 flex flex-col gap-3">
                        {orders.filter(o => o.status === columnStatus).map(order => (
                            <div 
                                key={order.id}
                                draggable={order.status !== 'Completed' && !order.isProcessing}
                                onDragStart={(e) => onDragStart(e, order)}
                                className={`bg-white dark:bg-gray-900 p-4 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 relative
                                    ${(order.status === 'Completed' || order.isProcessing) ? 'opacity-70 cursor-not-allowed' : 'cursor-grab active:cursor-grabbing hover:shadow-md'}
                                    transition-all duration-200 group
                                `}
                            >
                                {order.isProcessing && (
                                    <div className="absolute inset-0 bg-white/50 dark:bg-black/50 backdrop-blur-[1px] flex items-center justify-center rounded-xl z-10">
                                        <div className="w-5 h-5 border-2 border-indigo-500 border-t-transparent rounded-full animate-spin"></div>
                                    </div>
                                )}
                                
                                <div className="text-xs font-bold text-gray-500 dark:text-gray-400 mb-1">{order.order_number}</div>
                                <div className="font-black text-gray-900 dark:text-white text-lg leading-tight mb-2">{order.product_name}</div>
                                
                                <div className="flex items-center justify-between mt-4">
                                    <span className="bg-gray-100 dark:bg-gray-800 text-gray-800 dark:text-gray-300 text-xs font-bold px-2 py-1 rounded">
                                        Qty: {order.quantity}
                                    </span>
                                    {order.status === 'Completed' && (
                                        <span className="text-emerald-500 font-bold text-xs flex items-center gap-1">
                                            ✓ Yielded
                                        </span>
                                    )}
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            ))}
        </div>
    );
}
