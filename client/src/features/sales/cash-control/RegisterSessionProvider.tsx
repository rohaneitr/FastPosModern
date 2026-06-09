'use client';

import React, { createContext, useContext, useEffect, useState, useCallback } from 'react';
import api from '../../../lib/api';
import { cashControlApi } from './api';
import { getOrCreateDeviceFingerprint } from './utils';
import { CashRegisterSession, CashRegisterSetting } from './types';
import { toast } from 'react-hot-toast';
import { VaultInterceptorOverlay } from './VaultInterceptorOverlay';
import { ZReportClosureModal } from './ZReportClosureModal';

import { OfflineConflictAlertBanner } from './OfflineConflictAlertBanner';
import { OfflineConflictResolutionPanel } from './OfflineConflictResolutionPanel';

export type CashControlStage = 'BOOTSTRAPPING' | 'DRAWER_LOCKED' | 'WORKSPACE_ACTIVE' | 'SUSPENDED_COUNTING' | 'OFFLINE_ACTIVE';

interface CashControlContextType {
    stage: CashControlStage;
    activeSession: CashRegisterSession | null;
    settings: CashRegisterSetting | null;
    deviceHash: string | null;
    refreshStatus: () => Promise<void>;
}

const CashControlContext = createContext<CashControlContextType | undefined>(undefined);

export function useCashControl() {
    const context = useContext(CashControlContext);
    if (!context) {
        throw new Error('useCashControl must be used within a RegisterSessionProvider');
    }
    return context;
}

export function RegisterSessionProvider({ children }: { children: React.ReactNode }) {
    const [stage, setStage] = useState<CashControlStage>('BOOTSTRAPPING');
    const [activeSession, setActiveSession] = useState<CashRegisterSession | null>(null);
    const [settings, setSettings] = useState<CashRegisterSetting | null>(null);
    const [deviceHash, setDeviceHash] = useState<string | null>(null);

    const [mounted, setMounted] = useState(false);
    const [isResolutionPanelOpen, setIsResolutionPanelOpen] = useState(false);

    useEffect(() => {
        setMounted(true);
    }, []);

    const refreshStatus = useCallback(async () => {
        try {
            const response = await cashControlApi.fetchRegisterStatus();
            setSettings(response.settings);
            setActiveSession(response.register || null);

            if (!response.settings.pos_enforce_strict_cash_control) {
                setStage('WORKSPACE_ACTIVE');
                return;
            }

            if (!response.is_open) {
                setStage('DRAWER_LOCKED');
            } else if (response.register?.status === 'suspending') {
                setStage('SUSPENDED_COUNTING');
            } else {
                setStage('WORKSPACE_ACTIVE');
            }
        } catch (error: any) {
            console.error('Failed to refresh register status:', error);
            if (!navigator.onLine || error.code === 'ECONNABORTED' || error.message === 'Network Error') {
                setStage((prev) => prev === 'WORKSPACE_ACTIVE' ? 'OFFLINE_ACTIVE' : 'DRAWER_LOCKED');
            } else {
                setStage('DRAWER_LOCKED');
            }
        }
    }, []);

    useEffect(() => {
        if (!mounted) return;

        let isMounted = true;
        const bootSequence = async () => {
            try {
                const hash = await getOrCreateDeviceFingerprint();
                
                if (isMounted) {
                    setDeviceHash(hash);
                    api.defaults.headers.common['X-Device-Hash'] = hash;
                }

                await refreshStatus();
            } catch (error) {
                console.error('Boot sequence failed:', error);
                if (isMounted) {
                    setStage('DRAWER_LOCKED');
                }
            }
        };

        bootSequence();

        const interceptorId = api.interceptors.response.use(
            (response) => response,
            (error) => {
                if (!navigator.onLine || error.code === 'ECONNABORTED' || error.message === 'Network Error') {
                    setStage((prev) => prev === 'WORKSPACE_ACTIVE' ? 'OFFLINE_ACTIVE' : prev);
                } else if (error.response?.status === 422 || error.response?.status === 403) {
                    const msg = error.response?.data?.message || '';
                    const errCode = error.response?.data?.error_code || '';
                    if (errCode === 'MODULE_RESTRICTED') {
                        // SWR global mutate to instantly update auth profile and re-render DOM
                        import('swr').then(({ mutate }) => {
                            mutate('/api/v1/auth/me');
                            toast.error('Module restricted. Your session has been updated.');
                        });
                    } else if (msg.includes('FPM Security: POS checkout blocked')) {
                        setStage('DRAWER_LOCKED');
                        setActiveSession(null);
                        toast.error('Session Expired: This drawer has been locked or terminated remotely.');
                    }
                }
                return Promise.reject(error);
            }
        );

        const handleOffline = () => {
            setStage((prev) => prev === 'WORKSPACE_ACTIVE' ? 'OFFLINE_ACTIVE' : prev);
            toast.error('Network disconnected. Entering Offline Mode.');
        };

        const handleOnline = async () => {
            toast.success('Connection restored. Syncing offline data...');
            await refreshStatus();
            
            try {
                // Dynamic import to avoid SSR issues with Dexie
                const { db, clearOfflineQueue, isolateFailedSale } = await import('./offlineStore');
                const pendingSales = await db.offline_sales_queue.toArray();
                
                if (pendingSales.length > 0) {
                    toast.loading(`Syncing ${pendingSales.length} offline transactions...`, { id: 'sync' });
                    for (const sale of pendingSales) {
                        try {
                            await api.post('/api/v1/checkout', sale.payload, {
                                headers: { 'X-Idempotency-Key': sale.idempotencyKey }
                            });
                            if (sale.id) {
                                await clearOfflineQueue(sale.id);
                            }
                        } catch (err: any) {
                            if (err.response && err.response.status >= 400 && err.response.status < 500) {
                                // Poison Pill! (4xx Client Error)
                                const errorReason = err.response?.data?.message || 'Validation failed during offline sync';
                                await isolateFailedSale(sale, errorReason);
                                toast.error(`Isolated broken offline transaction: ${errorReason}`);
                            } else {
                                // 5xx Server Error or Network Timeout: keep in queue for next retry
                                console.error('Network or server error during sync, queue preserved', err);
                            }
                        }
                    }
                    toast.success('Offline sync cycle complete.', { id: 'sync' });
                }
            } catch (e) {
                console.error('Failed to process offline queue', e);
            }
        };

        window.addEventListener('offline', handleOffline);
        window.addEventListener('online', handleOnline);

        return () => {
            isMounted = false;
            api.interceptors.response.eject(interceptorId);
            window.removeEventListener('offline', handleOffline);
            window.removeEventListener('online', handleOnline);
        };
    }, [mounted, refreshStatus]);

    const contextValue = { stage, activeSession, settings, deviceHash, refreshStatus };

    if (!mounted || stage === 'BOOTSTRAPPING') {
        return (
            <CashControlContext.Provider value={contextValue}>
                <div className="flex h-screen w-full items-center justify-center bg-gray-50/50 backdrop-blur-md dark:bg-gray-900/50">
                    <div className="flex flex-col items-center space-y-4">
                        <div className="h-12 w-12 animate-spin rounded-full border-4 border-indigo-500 border-t-transparent shadow-lg shadow-indigo-500/20"></div>
                        <div className="text-sm font-medium tracking-wide text-indigo-500/80 animate-pulse">
                            Initializing Secure POS Workspace...
                        </div>
                    </div>
                </div>
            </CashControlContext.Provider>
        );
    }

    return (
        <CashControlContext.Provider value={contextValue}>
            <OfflineConflictAlertBanner onOpenPanel={() => setIsResolutionPanelOpen(true)} />
            <OfflineConflictResolutionPanel isOpen={isResolutionPanelOpen} onClose={() => setIsResolutionPanelOpen(false)} />
            
            {stage === 'DRAWER_LOCKED' && <VaultInterceptorOverlay />}
            {stage === 'SUSPENDED_COUNTING' && <ZReportClosureModal />}
            {stage === 'WORKSPACE_ACTIVE' && (
                <div className="pos-workspace-grid h-full w-full">
                    {children}
                </div>
            )}
            {stage === 'OFFLINE_ACTIVE' && (
                <div className="pos-workspace-grid h-full w-full offline-border ring-4 ring-yellow-500 ring-inset relative">
                    <div className="absolute top-0 left-0 right-0 bg-yellow-500 text-black text-center text-xs font-bold py-1 z-50">
                        OFFLINE MODE - TRANSACTIONS WILL BE SYNCED AUTOMATICALLY
                    </div>
                    <div className="pt-6 h-full">
                        {children}
                    </div>
                </div>
            )}
        </CashControlContext.Provider>
    );
}
