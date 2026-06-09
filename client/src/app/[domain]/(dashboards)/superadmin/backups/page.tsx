'use client';

import React, { useState, useEffect } from 'react';
import api from '@/lib/api';

export default function BackupsPage() {
  const [backups, setBackups] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
  const [running, setRunning] = useState(false);

  useEffect(() => {
    fetchBackups();
  }, []);

  const fetchBackups = async () => {
    setLoading(true);
    try {
      const res = await api.get('/superadmin/backups');
      setBackups(res.data);
    } catch (e) {
      console.error('Failed to fetch backups', e);
    } finally {
      setLoading(false);
    }
  };

  const runBackup = async () => {
    setRunning(true);
    try {
      await api.post('/superadmin/backups/run');
      alert('Backup process queued successfully. Check back in a few minutes.');
      setTimeout(fetchBackups, 5000); // refresh after 5s just in case it was fast
    } catch (e: any) {
      alert(e.response?.data?.message || 'Failed to start backup');
    } finally {
      setRunning(false);
    }
  };

  const downloadBackup = async (fileName: string) => {
    try {
      const res = await api.post('/superadmin/backups/download', { file_name: fileName }, { responseType: 'blob' });
      const url = window.URL.createObjectURL(new Blob([res.data]));
      const link = document.createElement('a');
      link.href = url;
      link.setAttribute('download', fileName);
      document.body.appendChild(link);
      link.click();
      link.parentNode?.removeChild(link);
    } catch (e) {
      alert('Failed to download backup');
    }
  };

  return (
    <div className="flex flex-col h-full gap-8 animate-in fade-in duration-500 pb-12">
      <div className="flex justify-between items-center">
        <div>
          <h1 className="text-3xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-blue-400 to-indigo-500">
            Disaster Recovery & Backups
          </h1>
          <p className="text-text-muted mt-1">Manage platform database and file backups.</p>
        </div>
        <button 
          onClick={runBackup} 
          disabled={running}
          className="bg-emerald-500 hover:bg-emerald-600 text-white px-6 py-2.5 rounded-xl font-bold transition-all shadow-lg shadow-emerald-500/20 disabled:opacity-50 flex items-center gap-2"
        >
          {running ? 'Running...' : '▶️ Run Manual Backup Now'}
        </button>
      </div>

      <div className="glass-card rounded-xl overflow-hidden border border-border">
        <div className="w-full overflow-x-auto">
          <table className="w-full text-left text-sm">
            <thead className="bg-surface/50 border-b border-border">
              <tr>
                <th className="p-4 font-semibold text-text-muted">Backup File</th>
                <th className="p-4 font-semibold text-text-muted">Size</th>
                <th className="p-4 font-semibold text-text-muted">Generated At</th>
                <th className="p-4 font-semibold text-text-muted text-right">Action</th>
              </tr>
            </thead>
            <tbody>
              {loading ? (
                <tr><td colSpan={4} className="p-8 text-center text-text-muted">Loading backups...</td></tr>
              ) : backups.length === 0 ? (
                <tr><td colSpan={4} className="p-8 text-center text-text-muted">No backups found. Run a manual backup to get started.</td></tr>
              ) : backups.map((b, i) => (
                <tr key={i} className="border-b border-border/50 hover:bg-white/5 transition-colors">
                  <td className="p-4 font-mono text-emerald-400">
                    <span className="text-lg mr-2">📦</span>{b.file_name}
                  </td>
                  <td className="p-4 font-bold">{b.file_size}</td>
                  <td className="p-4 text-text-muted">{b.last_modified}</td>
                  <td className="p-4 text-right">
                    <button 
                      onClick={() => downloadBackup(b.file_name)}
                      className="bg-indigo-500/20 text-indigo-400 border border-indigo-500/30 hover:bg-indigo-500/30 px-4 py-1.5 rounded-lg font-bold transition-all flex items-center gap-2 inline-flex"
                    >
                      📥 Download
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
}
