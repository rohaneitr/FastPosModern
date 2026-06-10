'use client';


import React, { useState, useEffect } from 'react';
import api from '@/lib/api';
import { useCurrency } from '@/lib/currency';
import { useRouter } from 'next/navigation';

export default function BillingPage() {
  const router = useRouter();
  const { format, currentCurrency, convert } = useCurrency();
  const [subscriptionInfo, setSubscriptionInfo] = useState<any>(null);
  const [plans, setPlans] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
  const [subscribing, setSubscribing] = useState<number | null>(null);

  useEffect(() => {
    fetchData();
  }, []);

  const fetchData = async () => {
    setLoading(true);
    try {
      const [subRes, plansRes] = await Promise.all([
        api.get('/settings/subscription'),
        api.get('/settings/plans')
      ]);
      setSubscriptionInfo(subRes.data);
      setPlans(plansRes.data);
    } catch (err) {
    } finally {
      setLoading(false);
    }
  };

  const handleSubscribe = (planId: number) => {
    router.push(`/business/billing/checkout?plan=${planId}`);
  };

  if (loading) return <div className="p-8 text-center text-text-muted">Loading billing information...</div>;

  const currentPlanId = subscriptionInfo?.subscription?.plan_id;
  const status = subscriptionInfo?.subscription?.status || 'none';
  const isActive = subscriptionInfo?.is_active;

  return (
    <div className="flex flex-col gap-8 animate-in fade-in duration-500 pb-12">
      <div>
        <h1 className="text-3xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-emerald-400 to-cyan-500">
          Billing & Subscription
        </h1>
        <p className="text-text-muted mt-1">Manage your plan, features, and billing methods.</p>
      </div>

      {/* Current Status */}
      <div className="glass-card p-6 rounded-xl border border-border">
        <h2 className="text-xl font-bold text-white mb-4">Current Plan</h2>
        <div className="flex items-center gap-4">
          <div className="flex-1">
            <p className="text-text-muted text-sm mb-1">Status</p>
            <div className="flex items-center gap-2">
              <span className={`px-2.5 py-1 rounded-full text-xs font-bold uppercase ${isActive ? 'bg-emerald-500/20 text-emerald-400' : 'bg-rose-500/20 text-rose-400'}`}>
                {status}
              </span>
              {!isActive && <span className="text-rose-400 text-sm font-medium">Your access is currently restricted. Please upgrade.</span>}
            </div>
          </div>
          {subscriptionInfo?.subscription && (
            <div className="flex-1">
              <p className="text-text-muted text-sm mb-1">Current Period Ends</p>
              <p className="text-white font-medium">
                {new Date(subscriptionInfo.subscription.current_period_end).toLocaleDateString()}
              </p>
            </div>
          )}
        </div>
        
        {(status === 'past_due' || (!isActive && status !== 'none')) && (
          <div className="mt-6 pt-6 border-t border-border">
            <button
              onClick={async () => {
                try {
                  const res = await api.get('/settings/subscription/billing-portal');
                  if (res.data?.url) window.open(res.data.url, '_blank');
                } catch (err: any) {
                  if (err.response?.status === 422) {
                    alert('This account is using an offline/mock subscription. Please contact SuperAdmin to renew or enable Stripe.');
                  } else {
                    alert('Could not open billing portal. Please contact support.');
                  }
                }
              }}
              className="bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-400 hover:to-orange-400 text-white px-6 py-2.5 rounded-xl font-bold transition-all shadow-lg shadow-amber-500/20 flex items-center gap-2"
            >
              💳 Update Billing &amp; Renew
            </button>
          </div>
        )}
      </div>

      {/* Available Plans */}
      <div>
        <h2 className="text-2xl font-bold text-white mb-6">Upgrade Plan</h2>
        <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
          {plans.map((plan: any) => {
            const isCurrent = plan.id === currentPlanId;
            let features = [];
            if (Array.isArray(plan.features)) features = plan.features;
            else if (typeof plan.features === 'string') {
              try { features = JSON.parse(plan.features); } catch(e) {}
            }

            return (
              <div key={plan.id} className={`glass-card rounded-xl border flex flex-col p-6 transition-all ${isCurrent ? 'border-emerald-500 shadow-[0_0_15px_rgba(16,185,129,0.1)]' : 'border-border hover:border-emerald-500/50'}`}>
                {isCurrent && <div className="text-xs font-bold text-emerald-500 uppercase tracking-wider mb-2">Current Plan</div>}
                <h3 className="text-2xl font-bold text-white mb-2">{plan.name}</h3>
                <div className="flex items-baseline gap-1 mb-6">
                  <span className="text-4xl font-black text-white">{format(convert(parseFloat(plan.price), 'BDT', currentCurrency.code), currentCurrency.code)}</span>
                  <span className="text-text-muted text-sm">/{plan.interval}</span>
                </div>
                
                <div className="flex flex-col gap-3 mb-8 flex-1">
                  <div className="flex items-center gap-2 text-sm text-text-muted">
                    <span className="text-emerald-500">✓</span>
                    <span>Up to {plan.employee_limit >= 999 ? 'Unlimited' : plan.employee_limit} Users</span>
                  </div>
                  <div className="flex items-center gap-2 text-sm text-text-muted">
                    <span className="text-emerald-500">✓</span>
                    <span>Up to {plan.device_limit >= 999 ? 'Unlimited' : plan.device_limit} Devices</span>
                  </div>
                  {features.map((feature: string) => (
                    <div key={feature} className="flex items-center gap-2 text-sm text-text-muted">
                      <span className="text-emerald-500">✓</span>
                      <span className="capitalize">{feature.replace('_', ' ')} Module</span>
                    </div>
                  ))}
                </div>

                <button 
                  onClick={() => handleSubscribe(plan.id)}
                  disabled={isCurrent || subscribing === plan.id}
                  className={`w-full py-3 rounded-xl font-bold transition-all ${isCurrent ? 'bg-surface border border-border text-text-muted cursor-not-allowed' : 'bg-gradient-to-r from-emerald-500 to-teal-600 hover:from-emerald-400 hover:to-teal-500 text-white shadow-lg disabled:opacity-50'}`}
                >
                  {subscribing === plan.id ? 'Processing...' : isCurrent ? 'Active Plan' : 'Upgrade Now'}
                </button>
              </div>
            );
          })}
        </div>
      </div>
    </div>
  );
}
