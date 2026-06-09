'use client';

import React, { useState, useEffect } from 'react';
import { useRouter, useSearchParams } from 'next/navigation';
import api from '@/lib/api';
import { useCurrency } from '@/lib/currency';

export default function CheckoutPage() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const { format, currentCurrency, convert } = useCurrency();
  const planId = searchParams.get('plan');
  
  const [plan, setPlan] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const [processing, setProcessing] = useState(false);
  const [tenantId, setTenantId] = useState<number | null>(null);
  const [checkoutMode, setCheckoutMode] = useState<'online' | 'offline'>('online');
  const [offlineDetails, setOfflineDetails] = useState({
    payment_method: 'bank_transfer',
    transaction_reference: '',
    notes: ''
  });

  useEffect(() => {
    const fetchPlan = async () => {
      try {
        const [planRes, meRes] = await Promise.all([
          api.get('/settings/plans'),
          api.get('/me')
        ]);
        const plans = planRes.data;
        const selectedPlan = plans.find((p: any) => p.id === Number(planId));
        setPlan(selectedPlan);
        setTenantId(meRes.data.business_id);
      } catch (err) {
        console.error(err);
      } finally {
        setLoading(false);
      }
    };
    if (planId) {
      fetchPlan();
    } else {
      router.push('/business/billing');
    }
  }, [planId, router]);

  const handleSimulatePayment = async () => {
    if (!plan || !tenantId) return;
    setProcessing(true);
    try {
      // Simulate the webhook
      await api.post('/webhooks/payment', {
        tenant_id: tenantId,
        plan_id: plan.id,
        amount: plan.price
      });
      alert('Payment successful! Your subscription has been upgraded.');
      router.push('/business/settings?tab=subscription');
    } catch (err: any) {
      alert(err.response?.data?.message || 'Payment failed');
      setProcessing(false);
    }
  };

  const handleRequestOffline = async () => {
    if (!plan || !tenantId) return;
    setProcessing(true);
    try {
      await api.post('/settings/subscription/request', {
        plan_id: plan.id,
        ...offlineDetails
      });
      alert('Subscription request submitted successfully. Waiting for Super Admin approval.');
      router.push('/business/settings?tab=subscription');
    } catch (err: any) {
      alert(err.response?.data?.message || 'Failed to submit request');
      setProcessing(false);
    }
  };

  if (loading) return <div className="p-8 text-center text-text-muted">Loading secure checkout...</div>;
  if (!plan) return <div className="p-8 text-center text-text-muted">Plan not found</div>;

  return (
    <div className="flex flex-col items-center justify-center min-h-[70vh] animate-in fade-in zoom-in duration-500">
      <div className="glass-card w-full max-w-md p-8 rounded-2xl border border-border relative overflow-hidden">
        {/* Background glow */}
        <div className="absolute -top-24 -right-24 w-48 h-48 bg-emerald-500/20 blur-[50px] rounded-full pointer-events-none" />
        <div className="absolute -bottom-24 -left-24 w-48 h-48 bg-teal-500/20 blur-[50px] rounded-full pointer-events-none" />

        <div className="text-center mb-8 relative z-10">
          <div className="w-16 h-16 bg-emerald-500/10 text-emerald-400 rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-[0_0_20px_rgba(16,185,129,0.2)]">
            <svg className="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" /></svg>
          </div>
          <h1 className="text-2xl font-black text-white">Secure Checkout</h1>
          <p className="text-text-muted text-sm mt-1">Upgrade your business to {plan.name}</p>
        </div>

        <div className="bg-surface/50 border border-border rounded-xl p-5 mb-6 relative z-10">
          <div className="flex justify-between items-center mb-4 pb-4 border-b border-border/50">
            <span className="text-text-muted">Subscription</span>
            <span className="font-bold text-white">{plan.name}</span>
          </div>
          <div className="flex justify-between items-center mb-4 pb-4 border-b border-border/50">
            <span className="text-text-muted">Billing Cycle</span>
            <span className="font-bold text-white capitalize">{plan.interval}</span>
          </div>
          <div className="flex justify-between items-center">
            <span className="text-white font-bold">Total Due</span>
            <span className="text-2xl font-black text-emerald-400">{format(convert(parseFloat(plan.price), 'BDT', currentCurrency.code), currentCurrency.code)}</span>
          </div>
        </div>

        {/* Payment Mode Toggle */}
        <div className="flex bg-background border border-border rounded-lg p-1 mb-6 relative z-10">
          <button 
            onClick={() => setCheckoutMode('online')}
            className={`flex-1 py-2 text-sm font-bold rounded-md transition-all ${checkoutMode === 'online' ? 'bg-emerald-500/20 text-emerald-400 shadow-sm' : 'text-text-muted hover:text-white'}`}
          >
            Pay Now (Online)
          </button>
          <button 
            onClick={() => setCheckoutMode('offline')}
            className={`flex-1 py-2 text-sm font-bold rounded-md transition-all ${checkoutMode === 'offline' ? 'bg-emerald-500/20 text-emerald-400 shadow-sm' : 'text-text-muted hover:text-white'}`}
          >
            Offline Request
          </button>
        </div>

        {checkoutMode === 'offline' && (
          <div className="flex flex-col gap-4 mb-6 relative z-10 animate-in fade-in slide-in-from-top-2">
            <div>
              <label className="text-xs font-medium text-text-muted mb-1.5 block">Payment Method</label>
              <select 
                value={offlineDetails.payment_method}
                onChange={e => setOfflineDetails({...offlineDetails, payment_method: e.target.value})}
                className="w-full bg-background border border-border rounded-xl px-4 py-3 text-sm text-white outline-none focus:border-emerald-500"
              >
                <option value="bank_transfer">Bank Transfer</option>
                <option value="cash">Cash Payment</option>
                <option value="mobile_money">Mobile Money (bKash/Nagad)</option>
              </select>
            </div>
            <div>
              <label className="text-xs font-medium text-text-muted mb-1.5 block">Transaction Reference (Optional)</label>
              <input 
                type="text" 
                placeholder="e.g. TrxID or Bank Receipt No"
                value={offlineDetails.transaction_reference}
                onChange={e => setOfflineDetails({...offlineDetails, transaction_reference: e.target.value})}
                className="w-full bg-background border border-border rounded-xl px-4 py-3 text-sm text-white outline-none focus:border-emerald-500"
              />
            </div>
            <div>
              <label className="text-xs font-medium text-text-muted mb-1.5 block">Additional Notes</label>
              <textarea 
                placeholder="Any message for the admin..."
                value={offlineDetails.notes}
                onChange={e => setOfflineDetails({...offlineDetails, notes: e.target.value})}
                className="w-full bg-background border border-border rounded-xl px-4 py-3 text-sm text-white outline-none focus:border-emerald-500 resize-none h-20"
              />
            </div>
          </div>
        )}

        <div className="relative z-10 flex flex-col gap-3">
          {checkoutMode === 'online' ? (
            <button 
              onClick={handleSimulatePayment}
              disabled={processing}
              className="w-full bg-gradient-to-r from-emerald-500 to-teal-600 hover:from-emerald-400 hover:to-teal-500 text-white py-3.5 rounded-xl font-bold shadow-lg shadow-emerald-500/20 transition-all flex items-center justify-center gap-2 disabled:opacity-50"
            >
              {processing ? (
                <><span className="w-5 h-5 border-2 border-white/30 border-t-white rounded-full animate-spin" /> Processing...</>
              ) : (
                <>💳 Pay {format(convert(parseFloat(plan.price), 'BDT', currentCurrency.code), currentCurrency.code)} &amp; Upgrade</>
              )}
            </button>
          ) : (
            <button 
              onClick={handleRequestOffline}
              disabled={processing}
              className="w-full bg-gradient-to-r from-blue-500 to-indigo-600 hover:from-blue-400 hover:to-indigo-500 text-white py-3.5 rounded-xl font-bold shadow-lg shadow-blue-500/20 transition-all flex items-center justify-center gap-2 disabled:opacity-50"
            >
              {processing ? (
                <><span className="w-5 h-5 border-2 border-white/30 border-t-white rounded-full animate-spin" /> Submitting...</>
              ) : (
                <>📨 Submit Upgrade Request</>
              )}
            </button>
          )}
          
          <button 
            onClick={() => router.back()}
            disabled={processing}
            className="w-full py-3 rounded-xl font-semibold text-text-muted hover:text-white transition-colors"
          >
            Cancel
          </button>
        </div>
        
        <p className="text-center text-xs text-text-muted mt-6 relative z-10">
          {checkoutMode === 'online' 
            ? 'This is a simulated secure checkout environment. No actual funds will be deducted.'
            : 'Your request will be reviewed by the administration. You will be notified upon approval.'}
        </p>
      </div>
    </div>
  );
}
