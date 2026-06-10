'use client';

import React, { useState, useEffect } from 'react';
import { useCartStore } from '@/store/useCartStore';
import { useCurrency } from '@/lib/currency';
import { useTranslation } from '@/lib/i18n';
import api from '@/lib/api';
import { useEntitlements } from '@/hooks/useEntitlements';
import dynamic from 'next/dynamic';

const PrescriptionModal = dynamic(
  () => import('@/features/pharmacy/components/PrescriptionModal').then(mod => mod.PrescriptionModal),
  { ssr: false }
);

interface CartPanelProps {
  contacts: any[];
  onLoadQuote: () => void;
  convertQuotationId: number | null;
  onClearQuote: () => void;
  hasPharmacyModule: boolean;
  onSerialRequired: (item: any) => void;
  onCheckout: (params: any) => void;
  isCheckingOut: boolean;
}

export function CartPanel({
  contacts,
  onLoadQuote,
  convertQuotationId,
  onClearQuote,
  hasPharmacyModule,
  onSerialRequired,
  onCheckout,
  isCheckingOut
}: CartPanelProps) {
  const { t } = useTranslation();
  const { format } = useCurrency();
  const { items, taxRate, getCartTotal, removeItem, updateQuantity, updateItemField, clearCart, hasHydrated } = useCartStore();
  const { hasModule } = useEntitlements();
  const showAdvancedTracking = hasModule('serial_tracking') || hasModule('advanced_inventory');

  const [isClient, setIsClient] = useState(false);
  useEffect(() => {
    setIsClient(true);
  }, []);

  const safeItems = isClient && hasHydrated ? items : [];

  const [contactId, setContactId] = useState('');
  const [paymentMethod, setPaymentMethod] = useState<'cash' | 'bkash' | 'sslcommerz' | 'card' | 'advance'>('cash');
  const [amountPaid, setAmountPaid] = useState('');
  const [customerPhone, setCustomerPhone] = useState('');
  const [sendSms, setSendSms] = useState(false);
  const [contactSummary, setContactSummary] = useState<any>(null);

  useEffect(() => {
    if (contactId) {
      api.get(`/contacts/${contactId}/ledger/summary`)
        .then(res => setContactSummary(res.data))
        .catch(() => setContactSummary(null));
    } else {
      setContactSummary(null);
    }
  }, [contactId]);

  const subtotal = safeItems.reduce((sum, item) => sum + (Number(item.price) * Number(item.quantity) * Number(item.fractional_ratio || 1)), 0);
  const taxAmount = subtotal * Number(taxRate);
  const total = subtotal + taxAmount;

  const [showRxModal, setShowRxModal] = useState(false);
  const [rxPayload, setRxPayload] = useState<any>(null);

  const handleCheckout = () => {
    // Pharmacy Rx Shield Check
    const requiresRx = safeItems.some(item => item.is_rx_required);
    if (requiresRx && !rxPayload) {
      setShowRxModal(true);
      return;
    }

    onCheckout({
      paymentMethod,
      contactId,
      amountPaid,
      convertQuotationId,
      sendSms,
      customerPhone,
      total,
      subtotal,
      taxAmount,
      prescription_doctor: rxPayload?.doctor,
      prescription_patient: rxPayload?.patient,
      prescription_file: rxPayload?.file ? "base64_encoded_or_s3_url_placeholder" : null, // Assuming multipart handling is managed elsewhere or simplified here
      prescription_notes: rxPayload?.notes
    });
  };

  return (
    <div className="w-[400px] glass-card rounded-xl flex flex-col h-full">
      {/* Header */}
      <div className="p-4 border-b border-border flex flex-col gap-2">
        <div className="flex justify-between items-center">
          <h2 className="font-semibold text-lg flex items-center gap-2">
            Current Sale 
            <span className="bg-primary/20 text-primary text-xs px-2 py-0.5 rounded-full">{safeItems.length}</span>
          </h2>
          <div className="flex gap-2">
            <button 
              onClick={onLoadQuote}
              className="text-xs bg-emerald-500/10 text-emerald-400 hover:bg-emerald-500/20 px-2 py-1 rounded transition-colors font-bold"
            >
              Load Quote
            </button>
            <button 
              onClick={clearCart}
              className="text-xs text-danger hover:bg-danger/10 px-2 py-1 rounded transition-colors"
            >
              Clear
            </button>
          </div>
        </div>
        {convertQuotationId && (
          <div className="bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 px-3 py-1.5 rounded-lg text-xs font-bold flex justify-between items-center mt-1">
            <span>Converting Quotation #{convertQuotationId}</span>
            <button onClick={onClearQuote} className="text-emerald-500 hover:text-emerald-300">✕</button>
          </div>
        )}
      </div>

      {/* Cart Items */}
      <div className="flex-1 p-4 overflow-y-auto flex flex-col gap-3">
        {safeItems.length === 0 ? (
          <div className="flex-1 flex flex-col items-center justify-center text-text-muted">
            <div className="text-4xl mb-2 opacity-50">🛒</div>
            <p>Your cart is empty</p>
            <p className="text-xs mt-1 opacity-70">Click a product to add it</p>
          </div>
        ) : (
          safeItems.map((item) => (
            <div key={item.id} className="bg-surface/80 border border-border rounded-lg p-3 flex flex-col gap-2">
              <div className="flex justify-between items-start">
                <div className="font-medium pr-2 flex flex-col w-full">
                  <span className="truncate">{item.name} {hasPharmacyModule && (item.is_medicine || item.generic_name) ? '💊' : ''}</span>
                  {hasPharmacyModule && (item.is_medicine || item.generic_name) && (
                    <span className="text-[10px] text-text-muted line-clamp-1">{item.generic_name}</span>
                  )}
                </div>
                <button onClick={() => removeItem(item.id)} className="text-text-muted hover:text-danger p-1">✕</button>
              </div>
              <div className="flex justify-between items-center mt-1">
                <div className="flex items-center gap-2">
                  <button onClick={() => updateQuantity(item.id, item.quantity - 1)} className="w-7 h-7 rounded bg-surface border border-border flex items-center justify-center hover:bg-white/5 transition-colors">-</button>
                  <input 
                    type="number" 
                    value={item.quantity} 
                    onChange={(e) => updateQuantity(item.id, parseFloat(e.target.value) || 1)}
                    className="w-12 text-center bg-transparent border-b border-border outline-none font-mono text-sm no-spinners"
                  />
                  <button onClick={() => updateQuantity(item.id, item.quantity + 1)} className="w-7 h-7 rounded bg-surface border border-border flex items-center justify-center hover:bg-white/5 transition-colors">+</button>
                </div>
                <div className="font-semibold text-primary">{format(Number(item.price) * item.quantity * (item.fractional_ratio || 1))}</div>
              </div>
              {hasPharmacyModule && (item.is_medicine || item.generic_name || item.is_rx_required) && (
                <div className="mt-1 mb-1">
                  <input 
                    type="text" 
                    placeholder="Dosage Instructions (e.g. 1-0-1 after meal)" 
                    value={item.dosage_instructions || ''}
                    onChange={(e) => updateItemField(item.id, 'dosage_instructions', e.target.value)}
                    className="w-full bg-background/50 border border-border rounded px-2 py-1 text-[11px] text-white outline-none focus:border-rose-500/50 transition-colors"
                  />
                </div>
              )}
              {showAdvancedTracking && (item.enable_sr_no || item.enable_imei || item.enable_warranty) && (
                <div className="mt-2 pt-2 border-t border-border/50 flex flex-col justify-between items-start gap-2">
                  <span className="text-[11px] text-text-muted flex gap-2 w-full">
                    {item.enable_sr_no && <span>Serials: {(item.serial_numbers || []).length}/{item.quantity}</span>}
                    {item.enable_imei && <span>IMEIs: {(item.imei_numbers || []).length}/{item.quantity}</span>}
                    {item.enable_warranty && <span>Warranty: {item.warranty_duration || 'N/A'}</span>}
                  </span>
                  {(item.enable_sr_no || item.enable_imei) && (
                    <button onClick={() => onSerialRequired(item)} className="text-[11px] text-fuchsia-400 hover:text-fuchsia-300 font-bold bg-fuchsia-500/10 px-2 py-1 rounded w-full flex justify-center items-center">
                       {item.enable_sr_no ? 'Select Serials' : 'Select IMEIs'}
                    </button>
                  )}
                </div>
              )}
            </div>
          ))
        )}
      </div>

      {/* Checkout Section */}
      <div className="p-4 border-t border-border bg-surface/50 rounded-b-xl flex flex-col gap-3">
        <select 
          value={contactId} 
          onChange={(e) => setContactId(e.target.value)}
          className="w-full bg-background border border-border rounded-lg px-3 py-2 text-sm text-white outline-none focus:border-primary transition-colors"
        >
          <option value="">Walk-in Customer</option>
          {contacts.map((c: any) => (
            <option key={c.id} value={c.id}>{c.name} {c.phone ? `(${c.phone})` : ''}</option>
          ))}
        </select>

        {contactId && contactSummary && (
          <div className="bg-background/50 border border-border rounded-lg p-2 flex justify-between items-center text-xs">
            <span className="text-text-muted">Customer Balance:</span>
            <span className={`font-mono font-bold ${contactSummary.balance_type === 'payable' ? 'text-rose-400' : 'text-emerald-400'}`}>
               {format(contactSummary.balance_amount)} {contactSummary.balance_type === 'payable' ? 'Due' : 'Credit'}
            </span>
          </div>
        )}

        {!contactId && (
          <input 
            type="text" 
            placeholder="Customer Phone (Optional)" 
            value={customerPhone}
            onChange={(e) => setCustomerPhone(e.target.value)}
            className="w-full bg-background border border-border rounded-lg px-3 py-2 text-sm outline-none focus:border-primary transition-colors"
          />
        )}

        <div className="grid grid-cols-2 gap-2 mt-1">
          <select 
            value={paymentMethod} 
            onChange={(e) => setPaymentMethod(e.target.value as any)}
            className="w-full bg-background border border-border rounded-lg px-3 py-2 text-sm text-white outline-none focus:border-primary transition-colors"
          >
            <option value="cash">Cash 💵</option>
            <option value="card">Card 💳</option>
            <option value="bkash">bKash 📱</option>
            <option value="sslcommerz">Online Gateway 🌐</option>
            {contactId && <option value="advance">Wallet / Advance 💼</option>}
          </select>
          <input 
            type="number" 
            placeholder="Amount Paid" 
            value={amountPaid}
            onChange={(e) => setAmountPaid(e.target.value)}
            className="w-full bg-background border border-border rounded-lg px-3 py-2 text-sm font-mono outline-none focus:border-primary transition-colors"
          />
        </div>
        
        <div className="flex items-center gap-2 px-1">
           <input type="checkbox" id="sendSms" checked={sendSms} onChange={e => setSendSms(e.target.checked)} className="rounded border-border bg-background text-primary focus:ring-primary/20" />
           <label htmlFor="sendSms" className="text-xs text-text-muted cursor-pointer">Send SMS Invoice</label>
        </div>

        <div className="border-t border-border mt-1 pt-3 flex flex-col gap-1">
          <div className="flex justify-between text-sm">
            <span className="text-text-muted">Subtotal</span>
            <span className="font-mono">{format(subtotal)}</span>
          </div>
          <div className="flex justify-between text-sm">
            <span className="text-text-muted">Tax ({(Number(taxRate) * 100).toFixed(1)}%)</span>
            <span className="font-mono">{format(taxAmount)}</span>
          </div>
          <div className="flex justify-between text-xl font-bold mt-2 text-white">
            <span>Total</span>
            <span className="text-primary font-mono">{format(total)}</span>
          </div>
        </div>

        <button 
          onClick={handleCheckout}
          disabled={isCheckingOut || safeItems.length === 0}
          className="w-full mt-2 bg-gradient-to-r from-primary to-primary-hover hover:opacity-90 text-white font-bold py-3.5 rounded-xl shadow-[0_0_20px_rgba(59,130,246,0.3)] disabled:opacity-50 transition-all flex justify-center items-center gap-2"
        >
          {isCheckingOut ? (
            <>
              <span className="w-5 h-5 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
              Processing...
            </>
          ) : `Pay ${format(total)}`}
        </button>
      </div>

      {showRxModal && (
        <PrescriptionModal 
          onClose={() => setShowRxModal(false)}
          onSubmit={(data) => {
            setRxPayload(data);
            setShowRxModal(false);
          }}
        />
      )}
    </div>
  );
}
