/**
 * Inventory — Domain Types
 *
 * Single source of truth for inventory-related shapes.
 * Eliminates `any` usage across hook and components.
 *
 * @feature inventory
 */
import * as z from 'zod';

// ── Zod Schemas (co-located with types for single import) ─────────────────

export const transferSchema = z.object({
  product_id:      z.number({ message: 'Product is required' }).min(1, 'Select a product'),
  from_location_id: z.number({ message: 'Source branch is required' }).min(1, 'Select source'),
  to_location_id:  z.number({ message: 'Destination branch is required' }).min(1, 'Select destination'),
  quantity:        z.number({ message: 'Quantity is required' })
    .positive('Quantity must be greater than 0')
    .min(0.01, 'Minimum transfer amount is 0.01'),
  note: z.string().optional(),
}).refine(
  data => data.from_location_id !== data.to_location_id,
  { message: 'Source and destination cannot be the same branch', path: ['to_location_id'] }
);

export type TransferFormValues = z.infer<typeof transferSchema>;

// ── Domain Interfaces ──────────────────────────────────────────────────────

export interface Location {
  id:   number;
  name: string;
}

export interface ProductStock {
  id:             number;
  name:           string;
  sku:            string;
  category:       { name: string } | null;
  stock_quantity: number;
  location_id:    number;
  location_name:  string;
}

/** Threshold below which stock is flagged as low (displayed in red). */
export const LOW_STOCK_THRESHOLD = 10;
