import Dexie, { Table } from 'dexie';

export interface LocalProduct {
  id: number;
  name: string;
  sku: string | null;
  price: string | number;
  sell_price_inc_tax: string | number;
  image: string | null;
  enable_sr_no: boolean;
  enable_imei: boolean;
  enable_warranty: boolean;
  warranty_duration: string | null;
  is_medicine: boolean;
  generic_name: string | null;
  is_rx_required: boolean;
  closest_expiry?: string | null;
  expiry_date?: string | null;
  unit_conversion_ratio: number | null;
  category_id?: number | null;
}

export interface OfflineTransaction {
  id?: number; // local auto-increment
  uuid: string;
  payload: any;
  status: 'pending_sync' | 'synced' | 'failed';
  error_message?: string;
  created_at: number;
}

export class FastPosDB extends Dexie {
  products!: Table<LocalProduct, number>;
  offline_sales_queue!: Table<OfflineTransaction, number>;

  constructor() {
    super('FastPosDB');
    this.version(1).stores({
      products: 'id, name, sku, category_id',
      offline_sales_queue: '++id, uuid, status, created_at'
    });
  }
}

export const db = new FastPosDB();
