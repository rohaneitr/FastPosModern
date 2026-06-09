'use client';

import React, { useEffect, useState } from 'react';
import { useLiveQuery } from 'dexie-react-hooks';
import { db } from './offlineStore';

interface OfflineConflictAlertBannerProps {
    onOpenPanel: () => void;
}

export function OfflineConflictAlertBanner({ onOpenPanel }: OfflineConflictAlertBannerProps) {
    const [mounted, setMounted] = useState(false);

    useEffect(() => {
        setMounted(true);
    }, []);

    const failedSyncCount = useLiveQuery(
        () => db.failed_offline_syncs.count(),
        [],
        0
    );

    if (!mounted || failedSyncCount === 0) return null;

    return (
        <div className="fixed top-4 left-1/2 -translate-x-1/2 z-50 animate-bounce">
            <div className="flex items-center space-x-3 rounded-full bg-red-500/90 px-6 py-2 shadow-lg shadow-red-500/20 backdrop-blur-md border border-red-400">
                <span className="flex h-2 w-2 rounded-full bg-white animate-ping"></span>
                <span className="text-sm font-semibold text-white">
                    {failedSyncCount} Offline Sync Conflict{failedSyncCount > 1 ? 's' : ''} Detected
                </span>
                <button
                    onClick={onOpenPanel}
                    className="ml-4 rounded-full bg-white/20 px-3 py-1 text-xs font-bold text-white hover:bg-white/30 transition-colors"
                >
                    Resolve Now
                </button>
            </div>
        </div>
    );
}
