import Dexie, { Table } from 'dexie';

export interface OfflineSale {
    id?: number;
    idempotencyKey: string;
    payload: any;
    timestamp: number;
}

export interface OfflineFailedSale extends OfflineSale {
    errorReason: string;
}

export class OfflineDatabase extends Dexie {
    offline_sales_queue!: Table<OfflineSale>;
    failed_offline_syncs!: Table<OfflineFailedSale>;
    catalog_cache!: Table<{ id: string; data: any }>;
    status_cache!: Table<{ id: string; data: any }>;

    constructor() {
        super('FastPOSOfflineStorage');
        this.version(2).stores({
            offline_sales_queue: '++id, idempotencyKey, timestamp',
            failed_offline_syncs: '++id, idempotencyKey, timestamp',
            catalog_cache: 'id',
            status_cache: 'id'
        });
    }
}

export const db = new OfflineDatabase();

export async function queueOfflineSale(payload: any, idempotencyKey: string) {
    return db.offline_sales_queue.add({
        idempotencyKey,
        payload,
        timestamp: Date.now()
    });
}

export async function clearOfflineQueue(id: number) {
    return db.offline_sales_queue.delete(id);
}

export async function isolateFailedSale(sale: OfflineSale, errorReason: string) {
    await db.transaction('rw', db.offline_sales_queue, db.failed_offline_syncs, async () => {
        if (sale.id) {
            await db.offline_sales_queue.delete(sale.id);
        }
        await db.failed_offline_syncs.add({
            ...sale,
            id: undefined, // Let it auto-increment
            errorReason
        });
    });
}

export async function resolveFailedSale(id: number) {
    return db.failed_offline_syncs.delete(id);
}
