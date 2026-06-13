import Dexie, { type Table } from 'dexie';
import { Product } from '@/hooks/api/useInventory';

// Types for our Offline Database
export interface OfflineSale {
  client_uuid: string;
  payload: any;
  created_at: number;
  sync_status: 'pending' | 'synced' | 'failed';
  error_message?: string;
}

export interface SyncState {
  id: string; // usually a singleton like 'global'
  last_pulled_at: string | null;
}

export class FastPOSDatabase extends Dexie {
  products!: Table<Product, number>;
  offline_sales!: Table<OfflineSale, string>;
  sync_state!: Table<SyncState, string>;

  constructor() {
    super('FastPOS_Local');
    
    // Define tables and indexes
    // ++id means auto-incrementing primary key (though for products we use server id)
    // for products we just use id as primary key
    this.version(1).stores({
      products: 'id, sku, barcode, name, updated_at',
      offline_sales: 'client_uuid, created_at, sync_status',
      sync_state: 'id',
    });
  }
}

export const db = new FastPOSDatabase();
