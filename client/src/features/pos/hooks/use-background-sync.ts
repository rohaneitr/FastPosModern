import { useEffect, useState } from 'react';
import { db } from '@/lib/db/db';
import api from '@/lib/api';
import toast from 'react-hot-toast';

export function useBackgroundSync() {
  const [isOnline, setIsOnline] = useState(typeof navigator !== 'undefined' ? navigator.onLine : true);
  const [isSyncing, setIsSyncing] = useState(false);
  const [pendingCount, setPendingCount] = useState(0);

  useEffect(() => {
    // Update count periodically or rely on dexie hooks if we want reactive UI
    const checkCount = async () => {
      const count = await db.offline_sales_queue.where('status').equals('pending_sync').count();
      setPendingCount(count);
    };
    checkCount();
    const interval = setInterval(checkCount, 5000);
    return () => clearInterval(interval);
  }, []);

  useEffect(() => {
    const handleOnline = async () => {
      setIsOnline(true);
      
      const pendingTx = await db.offline_sales_queue.where('status').equals('pending_sync').toArray();
      if (pendingTx.length === 0) return;

      setIsSyncing(true);
      
      try {
        const payload = pendingTx.map(tx => ({
          uuid: tx.uuid,
          payload: tx.payload
        }));

        const res = await api.post('/sync/offline-transactions', { transactions: payload });
        
        const { successes = [], failures = [] } = res.data;

        for (const uuid of successes) {
          const tx = await db.offline_sales_queue.where('uuid').equals(uuid).first();
          if (tx && tx.id) {
            await db.offline_sales_queue.update(tx.id, { status: 'synced' });
          }
        }

        for (const failure of failures) {
          const tx = await db.offline_sales_queue.where('uuid').equals(failure.uuid).first();
          if (tx && tx.id) {
            await db.offline_sales_queue.update(tx.id, { 
              status: 'failed', 
              error_message: failure.error 
            });
            toast.error(`Offline Sync Error: ${failure.error}`, { duration: 8000 });
          }
        }

        if (successes.length > 0) {
          toast.success(`Successfully synced ${successes.length} offline transactions!`);
        }
      } catch (err: any) {
        console.error('Offline Sync Failed', err);
      } finally {
        setIsSyncing(false);
        const count = await db.offline_sales_queue.where('status').equals('pending_sync').count();
        setPendingCount(count);
      }
    };

    const handleOffline = () => setIsOnline(false);

    window.addEventListener('online', handleOnline);
    window.addEventListener('offline', handleOffline);

    // Initial check just in case we booted up online with pending TX
    if (navigator.onLine) {
      handleOnline();
    }

    return () => {
      window.removeEventListener('online', handleOnline);
      window.removeEventListener('offline', handleOffline);
    };
  }, []);

  return { isOnline, isSyncing, pendingCount };
}
