'use client';

import React, { useState, useEffect } from 'react';
import { Modal } from '@/components/ui/modal';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';

interface TransferStockModalProps {
  open: boolean;
  onClose: () => void;
  product: any | null;
  locations: any[];
  onSubmit: (payload: any) => Promise<void>;
}

export function TransferStockModal({ open, onClose, product, locations, onSubmit }: TransferStockModalProps) {
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [form, setForm] = useState({
    from_location_id: '',
    to_location_id: '',
    quantity: '',
    note: '',
  });

  useEffect(() => {
    if (open && product) {
      setForm({
        from_location_id: product.location_id || (locations.length > 0 ? locations[0].id : ''),
        to_location_id: '',
        quantity: '',
        note: '',
      });
    }
  }, [open, product, locations]);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!product || !form.from_location_id || !form.to_location_id || !form.quantity) return;
    
    if (form.from_location_id === form.to_location_id) {
      alert("Destination location cannot be the same as the origin location.");
      return;
    }

    setIsSubmitting(true);
    try {
      await onSubmit({
        product_id: product.id,
        from_location_id: form.from_location_id,
        to_location_id: form.to_location_id,
        quantity: parseFloat(form.quantity),
        note: form.note,
      });
      onClose();
    } finally {
      setIsSubmitting(false);
    }
  };

  if (!product) return null;

  return (
    <Modal open={open} onClose={onClose} title="Transfer Stock" maxWidth="lg">
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
            <label className="block text-sm text-text-muted mb-1">From Location</label>
            <select
              value={form.from_location_id}
              onChange={(e) => setForm({ ...form, from_location_id: e.target.value })}
              className="w-full bg-background border border-border rounded-lg p-2.5 text-white focus:ring-2 focus:ring-emerald-500/50 outline-none transition-all"
              required
            >
              <option value="" disabled>Select Origin...</option>
              {locations.map((loc: any) => (
                <option key={loc.id} value={loc.id}>{loc.name}</option>
              ))}
            </select>
          </div>
          <div>
            <label className="block text-sm text-text-muted mb-1">To Location</label>
            <select
              value={form.to_location_id}
              onChange={(e) => setForm({ ...form, to_location_id: e.target.value })}
              className="w-full bg-background border border-border rounded-lg p-2.5 text-white focus:ring-2 focus:ring-emerald-500/50 outline-none transition-all"
              required
            >
              <option value="" disabled>Select Destination...</option>
              {locations.map((loc: any) => (
                <option key={loc.id} value={loc.id}>{loc.name}</option>
              ))}
            </select>
          </div>
        </div>

        <div>
          <label className="block text-sm text-text-muted mb-1">Transfer Quantity</label>
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
          <label className="block text-sm text-text-muted mb-1">Shipping Note / Reference</label>
          <Input
            value={form.note}
            onChange={(e) => setForm({ ...form, note: e.target.value })}
            placeholder="Optional reference"
          />
        </div>

        <div className="mt-4 flex justify-end gap-3">
          <Button variant="secondary" onClick={onClose} type="button">
            Cancel
          </Button>
          <Button type="submit" loading={isSubmitting} className="bg-blue-600 hover:bg-blue-700 shadow-blue-500/20">
            Initiate Transfer
          </Button>
        </div>
      </form>
    </Modal>
  );
}
