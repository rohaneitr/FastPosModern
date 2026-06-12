'use client';

import React, { useState, useEffect } from 'react';
import api from '@/lib/api';
import toast from 'react-hot-toast';
import { useCartStore } from '@/store/useCartStore';

interface SerialSelectionModalProps {
  item: any;
  onClose: () => void;
}

export function SerialSelectionModal({ item, onClose }: SerialSelectionModalProps) {
  const [availableSerials, setAvailableSerials] = useState<string[]>([]);
  const [isFetching, setIsFetching] = useState(false);
  const { updateItemField } = useCartStore();

  useEffect(() => {
    if (item) {
      setIsFetching(true);
      api.get(`/products/${item.product_id || item.id}/serials`)
        .then(res => setAvailableSerials(res.data))
        .catch(() => toast.error('Failed to fetch available serials'))
        .finally(() => setIsFetching(false));
    }
  }, [item]);

  if (!item) return null;

  const currentSerials = item.serial_numbers || [];

  const toggleSerialNumber = (serial: string) => {
    let newSerials = [...currentSerials];
    
    if (currentSerials.includes(serial)) {
      newSerials = newSerials.filter((s: string) => s !== serial);
    } else {
      if (newSerials.length >= item.quantity) {
        toast.error(`You can only select ${item.quantity} serial(s).`);
        return;
      }
      newSerials.push(serial);
    }
    
    updateItemField(item.id, 'serial_numbers', newSerials);
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm animate-in fade-in p-4">
      <div className="bg-surface border border-border rounded-2xl p-6 w-full max-w-md shadow-2xl animate-in zoom-in-95">
        <div className="flex justify-between items-center mb-6">
          <div>
            <h2 className="text-xl font-bold text-white">Select Serial Numbers</h2>
            <p className="text-text-muted text-sm mt-1">{item.name} - Need {item.quantity}</p>
          </div>
          <button onClick={onClose} className="text-text-muted hover:text-white transition-colors text-xl">✕</button>
        </div>
        
        <div className="bg-background/50 rounded-xl p-4 border border-border mb-6 max-h-[300px] overflow-y-auto">
          {isFetching ? (
            <div className="flex justify-center items-center py-8">
              <span className="w-6 h-6 border-2 border-primary border-t-transparent rounded-full animate-spin"></span>
            </div>
          ) : availableSerials.length === 0 ? (
            <div className="text-center text-warning py-4">
              ⚠️ No available serial numbers found in stock. You must receive stock with serial numbers first.
            </div>
          ) : (
            <div className="grid grid-cols-1 md:grid-cols-2 gap-2">
              {availableSerials.map((serial) => {
                const isSelected = currentSerials.includes(serial);
                return (
                  <button
                    key={serial}
                    onClick={() => toggleSerialNumber(serial)}
                    className={`p-2 rounded-lg text-sm font-mono transition-colors border
                      ${isSelected 
                        ? 'bg-primary text-white border-primary shadow-sm shadow-primary/20' 
                        : 'bg-background border-border text-text-muted hover:bg-surface hover:text-white'
                      }`}
                  >
                    {serial}
                  </button>
                );
              })}
            </div>
          )}
        </div>

        <button 
          onClick={onClose} 
          className="w-full font-bold py-3 bg-primary hover:bg-primary-hover text-white rounded-xl transition-all shadow-lg"
        >
          Done ({currentSerials.length}/{item.quantity} Selected)
        </button>
      </div>
    </div>
  );
}
