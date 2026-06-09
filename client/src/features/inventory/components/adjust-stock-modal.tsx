'use client';

import React, { useState, useEffect } from 'react';
import { Modal } from '@/components/ui/modal';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';

interface AdjustStockModalProps {
  open: boolean;
  onClose: () => void;
  product: any | null;
  locations: any[];
  onSubmit: (payload: any) => Promise<void>;
}

export function AdjustStockModal({ open, onClose, product, locations, onSubmit }: AdjustStockModalProps) {
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [form, setForm] = useState({
    location_id: '',
    adjustment_type: 'decrease',
    quantity: '',
    reason: '',
  });

  useEffect(() => {
    if (open && product) {
      setForm({
        location_id: product.location_id || (locations.length > 0 ? locations[0].id : ''),
        adjustment_type: 'decrease',
        quantity: '',
        reason: '',
      });
    }
  }, [open, product, locations]);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!product || !form.location_id || !form.quantity) return;
    
    setIsSubmitting(true);
    try {
      await onSubmit({
        product_id: product.id,
        location_id: form.location_id,
        adjustment_type: form.adjustment_type,
        quantity: parseFloat(form.quantity),
        reason: form.reason,
      });
      onClose();
    } finally {
      setIsSubmitting(false);
    }
  };

  if (!product) return null;

  return (
    <Modal open={open} onClose={onClose} title="Adjust Stock" maxWidth="lg">
      <form onSubmit={handleSubmit} className="flex flex-col gap-4 mt-2">
        <div>
          <label className="block text-sm text-text-muted mb-1">Product</label>
          <Input
            value={`${product.product_name} (${product.sku || 'N/A'})`}
            readOnly
            className="bg-background/50 text-text-muted"
          />
        </div>

        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label className="block text-sm text-text-muted mb-1">Location</label>
            <select
              value={form.location_id}
              onChange={(e) => setForm({ ...form, location_id: e.target.value })}
              className="w-full bg-background border border-border rounded-lg p-2.5 text-white focus:ring-2 focus:ring-emerald-500/50 outline-none transition-all"
              required
            >
              <option value="" disabled>Select Location...</option>
              {locations.map((loc: any) => (
                <option key={loc.id} value={loc.id}>{loc.name}</option>
              ))}
            </select>
          </div>
          <div>
            <label className="block text-sm text-text-muted mb-1">Adjustment Type</label>
            <select
              value={form.adjustment_type}
              onChange={(e) => setForm({ ...form, adjustment_type: e.target.value })}
              className="w-full bg-background border border-border rounded-lg p-2.5 text-white focus:ring-2 focus:ring-emerald-500/50 outline-none transition-all"
            >
              <option value="decrease">Decrease (-)</option>
              <option value="increase">Increase (+)</option>
            </select>
          </div>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label className="block text-sm text-text-muted mb-1">Quantity to Adjust</label>
            <Input
              type="number"
              value={form.quantity}
              onChange={(e) => setForm({ ...form, quantity: e.target.value })}
              placeholder="0.00"
              className="font-mono"
              required
              min="0.01"
              step="0.01"
            />
          </div>
          <div>
            <label className="block text-sm text-text-muted mb-1">Current Qty</label>
            <Input
              value={product.qty_available || '0.00'}
              readOnly
              className="bg-background/50 font-mono text-text-muted"
            />
          </div>
        </div>

        <div>
          <label className="block text-sm text-text-muted mb-1">Reason / Note</label>
          <textarea
            value={form.reason}
            onChange={(e) => setForm({ ...form, reason: e.target.value })}
            className="w-full bg-background border border-border rounded-lg p-2.5 text-white h-24 focus:ring-2 focus:ring-emerald-500/50 outline-none transition-all resize-none"
            placeholder="e.g. Found damaged during stock check"
            required
          />
        </div>

        <div className="mt-4 flex justify-end gap-3">
          <Button variant="secondary" onClick={onClose} type="button">
            Cancel
          </Button>
          <Button type="submit" loading={isSubmitting}>
            Save Adjustment
          </Button>
        </div>
      </form>
    </Modal>
  );
}
