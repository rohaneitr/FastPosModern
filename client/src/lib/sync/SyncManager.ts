import Dexie from 'dexie';
import { db, FastPosDB } from './db';
import api from '@/lib/api';

class SyncManagerService {
  private isSyncing = false;
  private readonly SYNC_KEY = 'fastpos_last_sync_timestamp';

  /**
   * Initialize auto-sync listeners
   */
  public initAutoSync() {
    if (typeof window !== 'undefined') {
      window.addEventListener('online', () => {
        console.log('[SyncManager] Connection restored. Triggering sync...');
        this.sync();
      });

      // Optional: Background interval sync (every 5 minutes)
      setInterval(() => {
        if (navigator.onLine) {
          this.sync();
        }
      }, 5 * 60 * 1000);
    }
  }

  /**
   * Main sync orchestrator
   */
  public async sync(): Promise<void> {
    if (this.isSyncing || typeof navigator !== 'undefined' && !navigator.onLine) {
      return;
    }

    this.isSyncing = true;
    try {
      // 1. Push local changes first
      await this.push();

      // 2. Pull server changes
      await this.pull();
    } catch (error) {
      console.error('[SyncManager] Sync failed:', error);
    } finally {
      this.isSyncing = false;
    }
  }

  /**
   * Delta Pull Client
   */
  public async pull(): Promise<void> {
    try {
      let since = localStorage.getItem(this.SYNC_KEY) || '2000-01-01T00:00:00.000Z';

      const response = await api.get(`/sync/pull?since=${encodeURIComponent(since)}`);
      
      if (response.data.success) {
        const { data: incomingData, timestamp } = response.data;
        
        await db.transaction('rw', db.products, db.purchases, db.transactions, async () => {
          // Process Products
          if (incomingData.products && incomingData.products.length > 0) {
            for (const item of incomingData.products) {
              await db.products.put({ ...item, sync_status: 'synced' });
            }
          }

          // Process Purchases
          if (incomingData.purchases && incomingData.purchases.length > 0) {
            for (const item of incomingData.purchases) {
              await db.purchases.put({ ...item, sync_status: 'synced' });
            }
          }

          // Process Transactions
          if (incomingData.transactions && incomingData.transactions.length > 0) {
            for (const item of incomingData.transactions) {
              await db.transactions.put({ ...item, sync_status: 'synced' });
            }
          }
        });

        // Update the last sync timestamp
        localStorage.setItem(this.SYNC_KEY, timestamp);
        console.log(`[SyncManager] Pull sync completed. Updated ${Object.values(incomingData).flat().length} records.`);
      }
    } catch (error) {
      console.error('[SyncManager] Pull sync failed:', error);
      throw error;
    }
  }

  /**
   * Push Sync (Conflict Resolution)
   */
  public async push(): Promise<void> {
    try {
      const pendingProducts = await db.products.where('sync_status').equals('pending_sync').toArray();
      const pendingPurchases = await db.purchases.where('sync_status').equals('pending_sync').toArray();
      const pendingTransactions = await db.transactions.where('sync_status').equals('pending_sync').toArray();

      if (pendingProducts.length === 0 && pendingPurchases.length === 0 && pendingTransactions.length === 0) {
        return; // Nothing to push
      }

      const syncData = {
        products: pendingProducts.map(p => this.stripSyncMetadata(p)),
        purchases: pendingPurchases.map(p => this.stripSyncMetadata(p)),
        transactions: pendingTransactions.map(t => this.stripSyncMetadata(t))
      };

      const response = await api.post('/sync/push', { sync_data: syncData });

      if (response.data.success) {
        const { successful_updates, conflicts } = response.data;

        await db.transaction('rw', db.products, db.purchases, db.transactions, async () => {
          // 1. Mark successful updates as synced
          for (const entity of Object.keys(successful_updates)) {
            const table = db[entity as keyof FastPosDB] as Dexie.Table;
            const ids = successful_updates[entity];
            for (const id of ids) {
              await table.update(id, { sync_status: 'synced' });
            }
          }

          // 2. Handle conflicts (Server Wins Policy)
          // Overwrite local record with the server's record
          for (const entity of Object.keys(conflicts)) {
            const table = db[entity as keyof FastPosDB] as Dexie.Table;
            const entityConflicts = conflicts[entity];
            
            for (const conflict of entityConflicts) {
              const serverRecord = conflict.server_record;
              console.warn(`[SyncManager] Conflict resolved (Server Wins) for ${entity} #${serverRecord.id}`);
              await table.put({ ...serverRecord, sync_status: 'synced' });
            }
          }
        });

        console.log('[SyncManager] Push sync completed.');
      }
    } catch (error) {
      console.error('[SyncManager] Push sync failed:', error);
      throw error;
    }
  }

  /**
   * Helper to remove local-only metadata before sending to server
   */
  private stripSyncMetadata(record: any): any {
    const { sync_status, ...rest } = record;
    return rest;
  }
}

export const SyncManager = new SyncManagerService();
