import Dexie, { type Table } from 'dexie';

// Define shapes of our core tables
export interface Product {
  id: number;
  name: string;
  sku: string;
  barcode_type?: string;
  category_id?: number | null;
  brand_id?: number | null;
  unit_id?: number | null;
  purchase_price: number;
  selling_price: number;
  alert_quantity: number;
  current_stock: number;
  is_active: boolean;
  version: number;
  updated_at: string;
  // Sync metadata
  sync_status?: 'synced' | 'pending_sync';
}

export interface Purchase {
  id: number;
  contact_id: number;
  reference_no: string;
  purchase_date: string;
  status: string;
  grand_total: number;
  note?: string | null;
  version: number;
  updated_at: string;
  // Sync metadata
  sync_status?: 'synced' | 'pending_sync';
}

export interface Transaction {
  id: number;
  type: string;
  status: string;
  invoice_no: string;
  transaction_date: string;
  total_before_tax: number;
  tax_amount: number;
  discount_amount: number;
  final_total: number;
  version: number;
  updated_at: string;
  // Sync metadata
  sync_status?: 'synced' | 'pending_sync';
}

export class FastPosDB extends Dexie {
  products!: Table<Product, number>;
  purchases!: Table<Purchase, number>;
  transactions!: Table<Transaction, number>;

  constructor() {
    super('FastPosDB');
    
    // Define schema
    // sync_status is indexed to easily query pending changes
    this.version(1).stores({
      products: 'id, sku, version, sync_status',
      purchases: 'id, reference_no, version, sync_status',
      transactions: 'id, invoice_no, version, sync_status',
    });
  }
}

export const db = new FastPosDB();
