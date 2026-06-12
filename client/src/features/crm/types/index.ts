/**
 * CRM / Customers — Domain Types
 * @feature crm/customers
 */
import * as z from 'zod';

// ── Zod Schema ─────────────────────────────────────────────────────────────

export const customerSchema = z.object({
  type:        z.enum(['customer', 'both']),
  first_name:  z.string().min(1, 'First name is required').max(255),
  middle_name: z.string().max(255).optional().nullable(),
  last_name:   z.string().max(255).optional().nullable(),
  email:       z.string().email('Invalid email').max(255).optional().or(z.literal('')),
  mobile:      z.string().max(255).optional().nullable(),
  city:        z.string().max(255).optional().nullable(),
  state:       z.string().max(255).optional().nullable(),
  country:     z.string().max(255).optional().nullable(),
});

export type CustomerFormValues = z.infer<typeof customerSchema>;

// ── Domain Interfaces ──────────────────────────────────────────────────────

export interface Customer {
  id:         number;
  name:       string;
  mobile:     string | null;
  email:      string | null;
  city:       string | null;
  state:      string | null;
  country:    string | null;
  created_at: string;
}

export const CUSTOMER_FORM_DEFAULTS: CustomerFormValues = {
  type:        'customer',
  first_name:  '',
  middle_name: '',
  last_name:   '',
  email:       '',
  mobile:      '',
  city:        '',
  state:       '',
  country:     '',
};
