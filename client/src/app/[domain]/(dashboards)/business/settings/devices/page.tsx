'use client';

import React, { useState, useEffect } from 'react';
import api from '@/lib/api';
import { ShieldAlert, Monitor, CheckCircle, XCircle } from 'lucide-react';

export default function DevicesPage() {
  const [devices, setDevices] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    fetchDevices();
  }, []);

  const fetchDevices = async () => {
    try {
      setLoading(true);
      const res = await api.get('/devices');
      setDevices(res.data);
      setError(null);
    } catch (err: any) {
      setError(err.response?.data?.message || 'Failed to fetch devices');
    } finally {
      setLoading(false);
    }
  };

  const revokeDevice = async (id: number) => {
    if (!confirm('Are you sure you want to revoke this device? This will log the device out and free up a license seat.')) return;
    
    try {
      await api.post(`/devices/${id}/revoke`);
      fetchDevices();
    } catch (err: any) {
      alert(err.response?.data?.message || 'Failed to revoke device');
    }
  };

  if (loading) return <div className="p-8 text-center text-text-muted">Loading devices...</div>;
  if (error) return <div className="p-8 text-center text-rose-500">{error}</div>;

  const activeDevices = devices.filter(d => d.status === 'active').length;

  return (
    <div className="p-8 max-w-6xl mx-auto">
      <div className="flex justify-between items-center mb-8">
        <div>
          <h1 className="text-3xl font-bold text-white mb-2 flex items-center gap-2">
            <Monitor className="w-8 h-8 text-primary" />
            Device Management
          </h1>
          <p className="text-text-muted">Manage your connected POS terminals and hardware bindings.</p>
        </div>
        <div className="bg-surface border border-border p-4 rounded-xl flex items-center gap-4">
          <div className="text-right">
            <p className="text-xs text-text-muted uppercase tracking-wider font-bold">Active Devices</p>
            <p className="text-2xl font-mono text-white">{activeDevices}</p>
          </div>
        </div>
      </div>

      <div className="bg-surface border border-border rounded-xl overflow-hidden shadow-2xl">
        <div className="w-full overflow-x-auto">
<table className="w-full text-left whitespace-nowrap">
  <thead className="bg-surface/50 border-b border-border">
    <tr>
      <th className="px-6 py-4 font-semibold text-text-muted capitalize">name</th>
              <th className="px-6 py-4 font-semibold text-text-muted capitalize">details</th>
              <th className="px-6 py-4 font-semibold text-text-muted capitalize">status</th>
              <th className="px-6 py-4 font-semibold text-text-muted text-right">Actions</th>
    </tr>
  </thead>
  <tbody className="divide-y divide-border">
    {(devices || [])?.length > 0 ? (
      (devices || []).map((item, index) => (
      <tr key={item.id || index} className="hover:bg-surface/30 transition-colors group">
        <td className="px-6 py-4 text-white font-medium">{item.device_name || item.hardware_hash}</td>
                <td className="px-6 py-4 text-white font-medium">{item.last_ip} / {item.user_agent}</td>
                <td className="px-6 py-4 text-white font-medium">{item.status}</td>
                <td className="px-6 py-4 text-right"><button onClick={() => revokeDevice(item.id)} className="text-rose-500 hover:text-rose-400 font-medium text-sm">Revoke</button></td>
      </tr>
    ))) : (
      <tr>
        <td colSpan={10} className="px-6 py-8 text-center text-text-muted">No records found.</td>
      </tr>
    )}
  </tbody>
</table>
</div>
      </div>
    </div>
  );
}
