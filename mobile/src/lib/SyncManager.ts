import AsyncStorage from '@react-native-async-storage/async-storage';
import axios from 'axios';

const API_URL = 'http://localhost:8000/api/v1/mobile/sync';
const getToken = async () => await AsyncStorage.getItem('auth_token');

// ── Local Product Cache (AsyncStorage-based until SQLite is added) ──────────

const PRODUCTS_CACHE_KEY = 'fastpos_products_cache';
const OFFLINE_TX_KEY = 'fastpos_offline_transactions';

async function getLocalProducts(): Promise<any[]> {
  const raw = await AsyncStorage.getItem(PRODUCTS_CACHE_KEY);
  return raw ? JSON.parse(raw) : [];
}

async function saveLocalProducts(products: any[]): Promise<void> {
  await AsyncStorage.setItem(PRODUCTS_CACHE_KEY, JSON.stringify(products));
}

async function getOfflineTransactions(): Promise<any[]> {
  const raw = await AsyncStorage.getItem(OFFLINE_TX_KEY);
  return raw ? JSON.parse(raw) : [];
}

async function saveOfflineTransactions(txns: any[]): Promise<void> {
  await AsyncStorage.setItem(OFFLINE_TX_KEY, JSON.stringify(txns));
}

// ── SyncManager ─────────────────────────────────────────────────────────────

export class SyncManager {

  /**
   * PULL Data from Server
   * Fetches only products updated since the last sync timestamp.
   * Merges into local cache — inserts new, updates existing, removes deleted.
   */
  static async pullProducts(): Promise<any[]> {
    try {
      const token = await getToken();
      if (!token) throw new Error('Not authenticated');

      const lastSync = await AsyncStorage.getItem('last_product_sync');

      const response = await axios.get(`${API_URL}/products`, {
        headers: { Authorization: `Bearer ${token}` },
        params: lastSync ? { updated_since: lastSync } : {}
      });

      const { data: serverProducts, sync_timestamp } = response.data;

      // Merge server delta into local cache
      const localProducts = await getLocalProducts();
      const localMap = new Map(localProducts.map((p: any) => [p.id, p]));

      for (const product of serverProducts) {
        if (product.deleted_at) {
          // Product was soft-deleted on server — remove locally
          localMap.delete(product.id);
        } else {
          // Insert or update
          localMap.set(product.id, product);
        }
      }

      const mergedProducts = Array.from(localMap.values());
      await saveLocalProducts(mergedProducts);

      // Store the exact server timestamp for the next pull
      if (sync_timestamp) {
        await AsyncStorage.setItem('last_product_sync', sync_timestamp);
      }

      console.log(`Pulled ${serverProducts.length} deltas. Local cache now has ${mergedProducts.length} products.`);
      return mergedProducts;

    } catch (error) {
      console.error('Pull failed, returning cached products:', error);
      // Return cached products so the app works offline
      return await getLocalProducts();
    }
  }

  /**
   * Store a transaction locally for later sync.
   * Called when the device is offline or as a write-through buffer.
   */
  static async storeOfflineTransaction(transaction: any): Promise<void> {
    const existing = await getOfflineTransactions();
    existing.push({
      ...transaction,
      _offline_id: `offline_${Date.now()}_${Math.random().toString(36).slice(2, 8)}`,
      _created_at: new Date().toISOString(),
      _synced: false,
    });
    await saveOfflineTransactions(existing);
    console.log(`Stored offline transaction. Queue size: ${existing.length}`);
  }

  /**
   * PUSH Data to Server
   * Pushes all unsynced offline transactions to the backend.
   * Marks them as synced on success, keeps failed ones for retry.
   */
  static async pushOfflineTransactions(): Promise<{ synced_count: number; failed: any[]; remaining: number }> {
    try {
      const allTxns = await getOfflineTransactions();
      const unsynced = allTxns.filter((t: any) => !t._synced);

      if (unsynced.length === 0) return { synced_count: 0, failed: [], remaining: 0 };

      const token = await getToken();
      if (!token) throw new Error('Not authenticated');

      const response = await axios.post(`${API_URL}/push`, {
        transactions: unsynced
      }, {
        headers: { Authorization: `Bearer ${token}` }
      });

      const { synced_count, failed, sync_timestamp } = response.data;

      // Mark successfully synced transactions
      const failedInvoices = new Set((failed || []).map((f: any) => f.invoice_no));
      const updatedTxns = allTxns.map((t: any) => {
        if (!t._synced && !failedInvoices.has(t.invoice_no)) {
          return { ...t, _synced: true, _synced_at: sync_timestamp };
        }
        return t;
      });

      // Remove synced transactions older than 7 days to save storage
      const sevenDaysAgo = Date.now() - 7 * 24 * 60 * 60 * 1000;
      const cleaned = updatedTxns.filter((t: any) => {
        if (t._synced && new Date(t._synced_at).getTime() < sevenDaysAgo) return false;
        return true;
      });

      await saveOfflineTransactions(cleaned);

      const remaining = cleaned.filter((t: any) => !t._synced).length;
      console.log(`Pushed ${synced_count} transactions. Failed: ${(failed || []).length}. Remaining in queue: ${remaining}`);

      return { synced_count, failed: failed || [], remaining };
    } catch (error) {
      console.error('Push failed:', error);
      const remaining = (await getOfflineTransactions()).filter((t: any) => !t._synced).length;
      return { synced_count: 0, failed: [], remaining };
    }
  }

  /**
   * Get the count of pending offline transactions (for UI badge display).
   */
  static async getPendingCount(): Promise<number> {
    const txns = await getOfflineTransactions();
    return txns.filter((t: any) => !t._synced).length;
  }

  /**
   * Full bidirectional sync: pull then push.
   */
  static async fullSync(): Promise<{ products: any[]; pushResult: any }> {
    const products = await SyncManager.pullProducts();
    const pushResult = await SyncManager.pushOfflineTransactions();
    return { products, pushResult };
  }
}
