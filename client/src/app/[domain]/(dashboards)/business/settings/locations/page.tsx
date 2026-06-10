'use client';

import React, { useState } from 'react';
import useSWR from 'swr';
import api from '@/lib/api';
import { usePosSounds } from '@/hooks/usePosSounds';
import { Plus, MapPin, Loader2, Trash2 } from 'lucide-react';

const fetcher = (url: string) => api.get(url).then(res => res.data.data || res.data);

export default function LocationsSettingsPage() {
  const { data: locations, error, mutate } = useSWR('/locations', fetcher);
  const { playTaskSuccess } = usePosSounds();
  const [loading, setLoading] = useState(false);

  const [form, setForm] = useState({ name: '', address: '', city: '', state: '', zip_code: '', country: '', phone: '' });

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);
    try {
      await api.post('/locations', form);
      playTaskSuccess();
      mutate();
      setForm({ name: '', address: '', city: '', state: '', zip_code: '', country: '', phone: '' });
      alert('Location created successfully!');
    } catch (err: any) {
      alert(err.response?.data?.message || 'Failed to create location');
    } finally {
      setLoading(false);
    }
  };

  const handleDelete = async (id: number) => {
    if (!confirm('Are you sure you want to delete this location?')) return;
    try {
      await api.delete(`/locations/${id}`);
      playTaskSuccess();
      mutate();
    } catch (err: any) {
      alert(err.response?.data?.message || 'Failed to delete location');
    }
  };

  if (!locations && !error) return <div className="flex items-center justify-center p-12"><Loader2 className="animate-spin text-primary" /></div>;

  return (
    <div className="flex flex-col gap-6 max-w-5xl mx-auto">
      <div>
        <h1 className="text-3xl font-bold text-white flex items-center gap-3"><MapPin className="text-primary" /> Branches & Locations</h1>
        <p className="text-text-muted mt-1">Manage your store branches, warehouses, and operating locations.</p>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <div className="lg:col-span-1">
          <form onSubmit={handleSubmit} className="glass-card p-6 rounded-2xl flex flex-col gap-4">
            <h3 className="text-lg font-bold text-white mb-2">Add New Location</h3>
            
            <input required placeholder="Branch Name (e.g. Main Store)" value={form.name} onChange={e => setForm({...form, name: e.target.value})} className="bg-background border border-border rounded-xl px-4 py-3 text-white outline-none focus:border-primary/50" />
            <input placeholder="Phone" value={form.phone} onChange={e => setForm({...form, phone: e.target.value})} className="bg-background border border-border rounded-xl px-4 py-3 text-white outline-none focus:border-primary/50" />
            <input placeholder="Address" value={form.address} onChange={e => setForm({...form, address: e.target.value})} className="bg-background border border-border rounded-xl px-4 py-3 text-white outline-none focus:border-primary/50" />
            
            <div className="grid grid-cols-2 gap-4">
              <input placeholder="City" value={form.city} onChange={e => setForm({...form, city: e.target.value})} className="bg-background border border-border rounded-xl px-4 py-3 text-white outline-none focus:border-primary/50" />
              <input placeholder="State" value={form.state} onChange={e => setForm({...form, state: e.target.value})} className="bg-background border border-border rounded-xl px-4 py-3 text-white outline-none focus:border-primary/50" />
            </div>

            <button type="submit" disabled={loading} className="bg-primary hover:bg-primary/90 text-white py-3 rounded-xl font-bold flex items-center justify-center gap-2 mt-2 transition-all">
              {loading ? <Loader2 className="animate-spin w-5 h-5" /> : <><Plus className="w-5 h-5" /> Create Branch</>}
            </button>
          </form>
        </div>

        <div className="lg:col-span-2">
          <div className="glass-card rounded-2xl border border-border overflow-hidden">
            <div className="p-4 border-b border-border/50 bg-white/5"><h3 className="font-bold text-white">Active Branches</h3></div>
            <div className="p-4 flex flex-col gap-3">
              {locations?.length === 0 ? (
                <p className="text-text-muted text-center py-8">No locations added yet.</p>
              ) : (
                locations?.map((loc: any) => (
                  <div key={loc.id} className="flex justify-between items-center bg-background border border-border p-4 rounded-xl">
                    <div>
                      <h4 className="text-white font-bold">{loc.name}</h4>
                      <p className="text-sm text-text-muted">{loc.address} {loc.city && `, ${loc.city}`}</p>
                    </div>
                    <button onClick={() => handleDelete(loc.id)} className="text-red-400 hover:bg-red-400/10 p-2 rounded-lg transition-colors">
                      <Trash2 className="w-5 h-5" />
                    </button>
                  </div>
                ))
              )}
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
