'use client';

import React, { useState, useEffect } from 'react';
import api from '@/lib/api';

interface Device {
  id: number;
  device_name: string;
  os: string;
  browser: string;
  ip_address: string;
  last_login: string;
  status: string;
}

export default function DevicesPage() {
  const [devices, setDevices] = useState<Device[]>([]);
  const [loading, setLoading] = useState(true);
  const [confirmModal, setConfirmModal] = useState<{ isOpen: boolean; deviceId: number | null }>({ isOpen: false, deviceId: null });

  useEffect(() => {
    fetchDevices();
  }, []);

  const fetchDevices = async () => {
    setLoading(true);
    try {
      const res = await api.get('/devices');
      if (res.data) setDevices(res.data);
    } catch (err) {
      console.warn('Failed to fetch devices', err);
    } finally {
      setLoading(false);
    }
  };

  const handleBlock = async () => {
    if (!confirmModal.deviceId) return;
    
    // Optimistic UI
    const targetId = confirmModal.deviceId;
    setDevices(prev => prev.map(d => d.id === targetId ? { ...d, status: 'blocked' } : d));
    setConfirmModal({ isOpen: false, deviceId: null });

    try {
      await api.put(`/devices/${targetId}/block`);
    } catch (err) {
      console.error(err);
      fetchDevices(); // revert on failure
    }
  };

  const handleRemove = async (id: number) => {
    if (!confirm('Are you sure you want to completely remove this device from history?')) return;

    // Optimistic UI
    setDevices(prev => prev.filter(d => d.id !== id));

    try {
      await api.delete(`/devices/${id}`);
    } catch (err) {
      console.error(err);
      fetchDevices(); // revert
    }
  };

  // Basic heuristic to identify "current" device based on the user agent
  // In a real scenario, the backend might return `is_current_device` flag.
  // We'll just rely on parsing userAgent from navigator here, though imperfect.
  const currentUA = navigator.userAgent;

  return (
    <div className="flex flex-col h-full gap-8 animate-in fade-in duration-500 pb-12 max-w-6xl mx-auto w-full">
      <div className="flex justify-between items-center">
        <div>
          <h1 className="text-3xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-teal-400 to-emerald-500">
            Device Registry
          </h1>
          <p className="text-text-muted mt-1">Monitor and manage devices accessing your account.</p>
        </div>
        <button onClick={fetchDevices} className="p-2 rounded-xl bg-surface hover:bg-surface/80 border border-border transition-colors">
          <svg className={`w-5 h-5 text-text-muted ${loading ? 'animate-spin' : ''}`} fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
        </button>
      </div>

      <div className="glass-card rounded-2xl overflow-hidden border border-border shadow-2xl">
        {loading && devices.length === 0 ? (
          <div className="p-12 text-center text-text-muted flex flex-col items-center">
            <div className="w-8 h-8 border-4 border-emerald-500 border-t-transparent rounded-full animate-spin mb-4"></div>
            Loading devices...
          </div>
        ) : (
          <div className="w-full overflow-x-auto">
            <table className="w-full text-left text-sm whitespace-nowrap">
              <thead className="bg-background/50 border-b border-border">
                <tr>
                  <th className="p-5 font-semibold text-text-muted">Device / OS</th>
                  <th className="p-5 font-semibold text-text-muted">Browser</th>
                  <th className="p-5 font-semibold text-text-muted">IP Address</th>
                  <th className="p-5 font-semibold text-text-muted">Last Active</th>
                  <th className="p-5 font-semibold text-text-muted">Status</th>
                  <th className="p-5 font-semibold text-text-muted text-right">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-border/50">
                {devices.map(device => {
                  // Rough check for "current" device
                  const isCurrent = currentUA.includes(device.os) && currentUA.includes(device.browser);
                  
                  return (
                    <tr key={device.id} className="hover:bg-white/5 transition-colors group">
                      <td className="p-5">
                        <div className="flex items-center gap-3">
                          <div className={`w-10 h-10 rounded-full flex items-center justify-center shrink-0 ${device.status === 'blocked' ? 'bg-danger/10 text-danger' : 'bg-emerald-500/10 text-emerald-500'}`}>
                            {device.os.toLowerCase().includes('windows') ? '🪟' : device.os.toLowerCase().includes('mac') ? '🍎' : device.os.toLowerCase().includes('linux') ? '🐧' : '📱'}
                          </div>
                          <div>
                            <p className="font-bold text-white flex items-center gap-2">
                              {device.device_name || 'Unknown Device'}
                              {isCurrent && <span className="bg-emerald-500/20 text-emerald-400 text-[10px] uppercase font-black px-2 py-0.5 rounded border border-emerald-500/30">Current</span>}
                            </p>
                            <p className="text-xs text-text-muted mt-0.5">{device.os}</p>
                          </div>
                        </div>
                      </td>
                      <td className="p-5 text-text-muted">{device.browser}</td>
                      <td className="p-5 font-mono text-xs opacity-80">{device.ip_address || 'Unknown'}</td>
                      <td className="p-5 text-text-muted">
                        {device.last_login ? new Date(device.last_login).toLocaleString() : 'N/A'}
                      </td>
                      <td className="p-5">
                        <span className={`px-3 py-1 rounded-full text-xs font-bold border ${device.status === 'blocked' ? 'bg-danger/20 text-danger border-danger/30' : 'bg-emerald-500/10 text-emerald-500 border-emerald-500/20'}`}>
                          {device.status.toUpperCase()}
                        </span>
                      </td>
                      <td className="p-5 text-right">
                        <div className="flex items-center justify-end gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                          {device.status !== 'blocked' && !isCurrent && (
                            <button 
                              onClick={() => setConfirmModal({ isOpen: true, deviceId: device.id })}
                              className="px-3 py-1.5 rounded-lg bg-danger/10 hover:bg-danger/20 text-danger border border-danger/20 text-xs font-bold transition-colors"
                            >
                              Block
                            </button>
                          )}
                          {!isCurrent && (
                            <button 
                              onClick={() => handleRemove(device.id)}
                              className="px-3 py-1.5 rounded-lg bg-surface hover:bg-white/10 text-text-muted hover:text-white border border-border text-xs font-medium transition-colors"
                            >
                              Remove
                            </button>
                          )}
                        </div>
                      </td>
                    </tr>
                  );
                })}
                {devices.length === 0 && !loading && (
                  <tr>
                    <td colSpan={6} className="p-8 text-center text-text-muted">No devices found.</td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>
        )}
      </div>

      {/* Confirmation Modal */}
      {confirmModal.isOpen && (
        <div className="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-50 p-4 animate-in fade-in duration-200">
          <div className="bg-surface border border-danger/30 rounded-2xl w-full max-w-md shadow-2xl p-6">
            <h3 className="text-xl font-bold text-white flex items-center gap-2 mb-2">
              <span className="text-danger">⚠️</span> Block Device?
            </h3>
            <p className="text-sm text-text-muted mb-6">
              This action will instantly revoke access for this device. The user will be logged out and will not be able to log back in using this specific device footprint until an admin unblocks it (or it is removed).
            </p>
            <div className="flex justify-end gap-3">
              <button 
                onClick={() => setConfirmModal({ isOpen: false, deviceId: null })}
                className="px-5 py-2 rounded-xl text-text-muted hover:text-white transition-colors font-medium"
              >
                Cancel
              </button>
              <button 
                onClick={handleBlock}
                className="px-5 py-2 rounded-xl bg-danger hover:bg-danger/80 text-white font-bold transition-all active:scale-95 shadow-lg shadow-danger/20"
              >
                Yes, Block Device
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
