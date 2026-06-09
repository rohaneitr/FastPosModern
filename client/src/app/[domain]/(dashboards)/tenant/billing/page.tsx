'use client';

import React, { useState, useEffect } from 'react';
import api from '@/lib/api';
import { useCurrency } from '@/lib/currency';

export default function TenantBillingPage() {
  const { format, currentCurrency, convert } = useCurrency();
  const [plans, setPlans] = useState<any[]>([]);
  const [currentSub, setCurrentSub] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const [processingId, setProcessingId] = useState<number | null>(null);
  const [gateway, setGateway] = useState<'sslcommerz' | 'bkash'>('sslcommerz');

  useEffect(() => {
    fetchData();
  }, []);

  const fetchData = async () => {
    setLoading(true);
    try {
      const [plansRes, subRes] = await Promise.all([
        api.get('/settings/plans'),
        api.get('/settings/subscription')
      ]);
      setPlans(Array.isArray(plansRes.data) ? plansRes.data : []);
      setCurrentSub(subRes.data.subscription);
    } catch (err) {
      console.error(err);
    } finally {
      setLoading(false);
    }
  };

  const handleAction = async (plan: any) => {
    if (processingId) return;
    
    // Determine upgrade or downgrade
    const isDowngrade = currentSub && plan.price < currentSub.plan?.price;
    
    // Add simple confirm for downgrade
    if (isDowngrade && !confirm(`Are you sure you want to downgrade? Ensure you do not exceed the new limits of ${plan.device_limit} devices and ${plan.employee_limit} employees.`)) {
        return;
    }

    setProcessingId(plan.id);

    try {
      const res = await api.post('/payments/initiate', {
        plan_id: plan.id,
        gateway: gateway
      });
      
      if (res.data.payment_url) {
        window.location.href = res.data.payment_url;
      } else {
        alert('Payment URL not received from gateway.');
        setProcessingId(null);
      }
    } catch (error: any) {
      alert(error.response?.data?.message || 'Failed to initiate payment');
      setProcessingId(null);
    }
  };

  return (
    <div className="flex flex-col h-full gap-8 animate-in fade-in duration-500 pb-12">
      <div className="flex flex-col gap-2">
        <h1 className="text-3xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-amber-400 to-orange-500">
          Billing & Subscriptions
        </h1>
        <p className="text-text-muted max-w-2xl">
          Manage your current subscription, upgrade or downgrade your tier, and view your billing cycle.
        </p>
      </div>

      {loading ? (
        <div className="flex justify-center p-12 text-text-muted animate-pulse">Loading subscription details...</div>
      ) : (
        <>
          {currentSub && currentSub.plan && (
            <div className="glass-card p-6 rounded-2xl border border-emerald-500/30 bg-emerald-500/5 relative overflow-hidden">
              <div className="absolute top-0 right-0 bg-emerald-500 text-white text-xs font-bold px-3 py-1 rounded-bl-lg uppercase tracking-wider">Current Active Plan</div>
              <h2 className="text-2xl font-black text-white mb-1">{currentSub.plan.name}</h2>
              <div className="text-sm text-emerald-400 font-medium mb-4 flex items-center gap-2">
                 <span className="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                 Active until {new Date(currentSub.current_period_end).toLocaleDateString()}
              </div>
              <div className="flex gap-6 mt-4">
                <div className="flex flex-col">
                  <span className="text-text-muted text-xs uppercase tracking-wider">Device Limit</span>
                  <span className="font-bold text-lg text-white">{currentSub.plan.device_limit}</span>
                </div>
                <div className="flex flex-col">
                  <span className="text-text-muted text-xs uppercase tracking-wider">Employee Limit</span>
                  <span className="font-bold text-lg text-white">{currentSub.plan.employee_limit}</span>
                </div>
              </div>
            </div>
          )}

          <div className="flex justify-end mb-4">
            <div className="glass-card flex items-center p-1 rounded-xl border border-border">
              <button 
                onClick={() => setGateway('sslcommerz')}
                className={`px-6 py-2 rounded-lg text-sm font-bold transition-all ${gateway === 'sslcommerz' ? 'bg-amber-500 text-white shadow-lg' : 'text-text-muted hover:text-white'}`}
              >
                SSLCommerz
              </button>
              <button 
                onClick={() => setGateway('bkash')}
                className={`px-6 py-2 rounded-lg text-sm font-bold transition-all ${gateway === 'bkash' ? 'bg-[#e2136e] text-white shadow-lg' : 'text-text-muted hover:text-white'}`}
              >
                bKash
              </button>
            </div>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
            {plans.map(plan => {
              const isCurrent = currentSub?.plan_id === plan.id;
              const isDowngrade = currentSub && plan.price < currentSub.plan?.price;
              
              return (
                <div key={plan.id} className={`glass-card rounded-2xl border p-6 flex flex-col ${isCurrent ? 'border-amber-500/50 shadow-[0_0_20px_rgba(245,158,11,0.1)]' : 'border-border'}`}>
                  <h3 className="text-xl font-bold text-white mb-2">{plan.name}</h3>
                  <div className="flex items-baseline gap-1 mb-6">
                    <span className="text-4xl font-black text-white">{format(convert(parseFloat(plan.price), 'USD', currentCurrency.code), currentCurrency.code)}</span>
                    <span className="text-text-muted text-sm">/{plan.interval}</span>
                  </div>
                  
                  <div className="flex flex-col gap-3 mb-8 flex-1">
                    <div className="flex justify-between items-center text-sm border-b border-border/50 pb-2">
                      <span className="text-text-muted">Type</span>
                      <span className="font-semibold text-white capitalize">{plan.plan_type.replace(/_/g, ' ')}</span>
                    </div>
                    <div className="flex justify-between items-center text-sm border-b border-border/50 pb-2">
                      <span className="text-text-muted">Devices Allowed</span>
                      <span className="font-semibold text-white">{plan.device_limit}</span>
                    </div>
                    <div className="flex justify-between items-center text-sm border-b border-border/50 pb-2">
                      <span className="text-text-muted">Users Allowed</span>
                      <span className="font-semibold text-white">{plan.employee_limit}</span>
                    </div>
                  </div>

                  {isCurrent ? (
                    <button disabled className="w-full py-3 rounded-xl font-bold bg-surface/50 text-text-muted border border-border cursor-not-allowed">
                      Current Plan
                    </button>
                  ) : (
                    <button 
                      onClick={() => handleAction(plan)}
                      disabled={processingId !== null}
                      className={`w-full py-3 rounded-xl font-bold transition-all shadow-lg flex justify-center items-center gap-2 ${
                        isDowngrade 
                          ? 'bg-surface hover:bg-surface/80 text-white border border-border' 
                          : 'bg-gradient-to-r from-amber-500 to-orange-600 hover:from-amber-600 hover:to-orange-700 text-white'
                      }`}
                    >
                      {processingId === plan.id ? (
                        <span className="animate-pulse">Processing...</span>
                      ) : (
                        isDowngrade ? 'Downgrade Plan' : 'Upgrade Now'
                      )}
                    </button>
                  )}
                </div>
              );
            })}
          </div>
        </>
      )}
    </div>
  );
}
