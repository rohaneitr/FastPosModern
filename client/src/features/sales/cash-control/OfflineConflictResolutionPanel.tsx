'use client';

import React, { useState } from 'react';
import { useLiveQuery } from 'dexie-react-hooks';
import { db, resolveFailedSale, OfflineFailedSale } from './offlineStore';
import api from '../../../lib/api';
import { toast } from 'react-hot-toast';

function deepSortObjectKeys(obj: any): any {
    if (typeof obj !== 'object' || obj === null) return obj;
    if (Array.isArray(obj)) return obj.map(deepSortObjectKeys);
    
    return Object.keys(obj)
        .sort()
        .reduce((result: Record<string, any>, key: string) => {
            result[key] = deepSortObjectKeys(obj[key]);
            return result;
        }, {});
}

export function isPayloadMutated(originalPayload: any, editedPayloadStr: string): boolean {
    try {
        const editedPayload = JSON.parse(editedPayloadStr);
        const originalNormalized = JSON.stringify(deepSortObjectKeys(originalPayload));
        const editedNormalized = JSON.stringify(deepSortObjectKeys(editedPayload));
        
        return originalNormalized !== editedNormalized;
    } catch {
        return true; // Failsafe: Invalid JSON syntax counts as an active mutation
    }
}

interface OfflineConflictResolutionPanelProps {
    isOpen: boolean;
    onClose: () => void;
}

export function OfflineConflictResolutionPanel({ isOpen, onClose }: OfflineConflictResolutionPanelProps) {
    const failedSyncs = useLiveQuery(() => db.failed_offline_syncs.toArray(), []);
    const [selectedRow, setSelectedRow] = useState<OfflineFailedSale | null>(null);
    const [editorValue, setEditorValue] = useState<string>('');
    const [isRetrying, setIsRetrying] = useState(false);

    if (!isOpen) return null;

    const handleSelectRow = (row: OfflineFailedSale) => {
        setSelectedRow(row);
        setEditorValue(JSON.stringify(row.payload, null, 2));
    };

    const handleForceRetry = async () => {
        if (!selectedRow) return;

        let finalPayload;
        try {
            finalPayload = JSON.parse(editorValue);
        } catch (e) {
            toast.error('Invalid JSON syntax in payload editor.');
            return;
        }

        setIsRetrying(true);
        const mutated = isPayloadMutated(selectedRow.payload, editorValue);
        const targetIdempotencyKey = mutated ? window.crypto.randomUUID() : selectedRow.idempotencyKey;

        try {
            await api.post('/api/v1/checkout', finalPayload, {
                headers: { 'X-Idempotency-Key': targetIdempotencyKey }
            });
            await resolveFailedSale(selectedRow.id as number);
            toast.success('Conflict resolved! Transaction synced successfully.');
            setSelectedRow(null);
            
            if (failedSyncs && failedSyncs.length <= 1) {
                onClose(); // Last one resolved
            }
        } catch (err: any) {
            const errorReason = err.response?.data?.message || 'Retry failed.';
            toast.error(`Retry failed: ${errorReason}`);
            await db.failed_offline_syncs.update(selectedRow.id as number, { errorReason });
        } finally {
            setIsRetrying(false);
        }
    };

    return (
        <div className="fixed inset-0 z-[100] flex justify-end bg-gray-900/40 backdrop-blur-sm">
            <div className="w-full max-w-2xl bg-white dark:bg-gray-800 shadow-2xl h-full flex flex-col border-l border-gray-200 dark:border-gray-700">
                <div className="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center bg-gray-50 dark:bg-gray-900">
                    <h2 className="text-lg font-bold text-gray-900 dark:text-white">Offline Sync Conflicts</h2>
                    <button onClick={onClose} className="text-gray-500 hover:text-gray-700 dark:hover:text-gray-300">
                        <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <div className="flex-1 overflow-y-auto p-6 space-y-6">
                    {/* Data Grid View */}
                    {!selectedRow ? (
                        <div className="space-y-4">
                            {failedSyncs?.length === 0 ? (
                                <div className="text-center text-gray-500 py-10">No conflicts found.</div>
                            ) : (
                                failedSyncs?.map((row) => (
                                    <div key={row.id} 
                                         onClick={() => handleSelectRow(row)}
                                         className="p-4 border border-red-200 dark:border-red-900/50 bg-red-50 dark:bg-red-900/10 rounded-xl cursor-pointer hover:bg-red-100 dark:hover:bg-red-900/20 transition-colors">
                                        <div className="flex justify-between items-start mb-2">
                                            <div className="text-xs font-mono text-gray-500">{row.idempotencyKey}</div>
                                            <div className="text-xs text-gray-400">{new Date(row.timestamp).toLocaleString()}</div>
                                        </div>
                                        <div className="text-sm font-semibold text-red-700 dark:text-red-400">
                                            {row.errorReason}
                                        </div>
                                    </div>
                                ))
                            )}
                        </div>
                    ) : (
                        /* Editor View */
                        <div className="flex flex-col h-full space-y-4">
                            <button onClick={() => setSelectedRow(null)} className="text-sm text-indigo-600 dark:text-indigo-400 hover:underline flex items-center">
                                ← Back to List
                            </button>
                            <div className="bg-red-50 dark:bg-red-900/10 border border-red-200 dark:border-red-900/50 p-4 rounded-lg">
                                <h3 className="text-sm font-bold text-red-800 dark:text-red-300 mb-1">Error Reason</h3>
                                <p className="text-sm text-red-700 dark:text-red-400">{selectedRow.errorReason}</p>
                            </div>
                            
                            <div className="flex-1 flex flex-col">
                                <label className="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Raw JSON Payload</label>
                                <textarea
                                    value={editorValue}
                                    onChange={(e) => setEditorValue(e.target.value)}
                                    className="flex-1 w-full bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-gray-100 p-4 rounded-lg border border-gray-300 dark:border-gray-700 font-mono text-sm focus:ring-2 focus:ring-indigo-500"
                                />
                            </div>

                            <button
                                onClick={handleForceRetry}
                                disabled={isRetrying}
                                className="w-full py-3 bg-indigo-600 text-white rounded-lg font-semibold hover:bg-indigo-700 transition-colors disabled:opacity-50"
                            >
                                {isRetrying ? 'Syncing...' : 'Force Retry Sync'}
                            </button>
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}
