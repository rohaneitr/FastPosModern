'use client';

import React, { useState, useEffect } from 'react';
import api from '@/lib/api';
import toast from 'react-hot-toast';

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
      setBackups(res.data || []);
    } catch (err: any) {
      toast.error('Failed to fetch backups: ' + (err.response?.data?.message || err.message));
    } finally {
      setLoading(false);
    }
  };

  const handleRunBackup = async () => {
    setRunning(true);
    try {
      await api.post('/superadmin/backups/run');
      toast.success('Backup queued successfully. It will appear here shortly.');
      setTimeout(fetchBackups, 3000);
    } catch (err: any) {
      toast.error('Failed to run backup: ' + (err.response?.data?.message || err.message));
    } finally {
      setRunning(false);
    }
  };

  const handleDownload = async (fileName: string) => {
    try {
      toast.success('Starting download...');
      const res = await api.post('/superadmin/backups/download', { file_name: fileName }, { responseType: 'blob' });
      const url = window.URL.createObjectURL(new Blob([res.data]));
      const link = document.createElement('a');
      link.href = url;
      link.setAttribute('download', fileName);
      document.body.appendChild(link);
      link.click();
      link.remove();
    } catch (err: any) {
      toast.error('Failed to download backup');
    }
  };

  const handleRestore = async (fileName: string) => {
    if (!confirm('Are you sure you want to RESTORE this backup? The system will go into maintenance mode.')) return;
    try {
      await api.post('/superadmin/backups/restore', { file_name: fileName });
      toast.success('Database restore queued successfully.');
    } catch (err: any) {
      toast.error('Failed to restore backup: ' + (err.response?.data?.message || err.message));
    }
  };

  return (
    <div className="flex flex-col gap-8 animate-in fade-in duration-500 pb-12">
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h1 className="text-3xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-emerald-400 to-teal-600">
            System Backups
          </h1>
          <p className="text-text-muted text-sm mt-1">
            Manage global PostgreSQL database backups and restoration points.
          </p>
        </div>
        <button
          onClick={handleRunBackup}
          disabled={running}
          className="bg-emerald-500 hover:bg-emerald-600 text-white px-6 py-2.5 rounded-xl font-bold shadow-lg shadow-emerald-500/20 transition-all flex items-center gap-2"
        >
          {running ? 'Running...' : 'Run Manual Backup'}
        </button>
      </div>

      <div className="bg-surface border border-border rounded-2xl overflow-hidden">
        <table className="w-full text-left whitespace-nowrap">
          <thead className="bg-background/50 border-b border-border">
            <tr>
              <th className="px-6 py-4 font-semibold text-text-muted">File Name</th>
              <th className="px-6 py-4 font-semibold text-text-muted">Size</th>
              <th className="px-6 py-4 font-semibold text-text-muted">Created At</th>
              <th className="px-6 py-4 font-semibold text-text-muted text-right">Actions</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-border">
            {loading ? (
              <tr>
                <td colSpan={4} className="px-6 py-8 text-center text-text-muted animate-pulse">Loading backups...</td>
              </tr>
            ) : backups.length === 0 ? (
              <tr>
                <td colSpan={4} className="px-6 py-16 text-center text-text-muted">No backups found.</td>
              </tr>
            ) : (
              backups.map((backup, idx) => (
                <tr key={idx} className="hover:bg-surface/50 transition-colors">
                  <td className="px-6 py-4 text-white font-medium">{backup.file_name}</td>
                  <td className="px-6 py-4 text-text-muted">{backup.file_size}</td>
                  <td className="px-6 py-4 text-text-muted">{backup.last_modified}</td>
                  <td className="px-6 py-4 text-right flex justify-end gap-2">
                    <button onClick={() => handleDownload(backup.file_name)} className="text-emerald-400 hover:text-emerald-300 font-bold px-3 py-1.5 bg-emerald-500/10 rounded-lg">Download</button>
                    <button onClick={() => handleRestore(backup.file_name)} className="text-rose-400 hover:text-rose-300 font-bold px-3 py-1.5 bg-rose-500/10 rounded-lg">Restore</button>
                  </td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>
    </div>
  );
}
