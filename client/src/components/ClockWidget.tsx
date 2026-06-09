'use client';

import React, { useState, useEffect } from 'react';
import api from '@/lib/api';

export default function ClockWidget() {
  const [clockedIn, setClockedIn] = useState(false);
  const [loading, setLoading] = useState(false);
  const [time, setTime] = useState('');

  useEffect(() => {
    // Clock tick
    const timer = setInterval(() => {
      setTime(new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' }));
    }, 1000);

    // Initial check (mocking state or fetching from API if we had a status endpoint)
    // For now, we rely on local storage or just let it be a stateless trigger.
    const status = localStorage.getItem('fastpos_clock_status');
    if (status === 'in') setClockedIn(true);

    return () => clearInterval(timer);
  }, []);

  const handleToggle = async () => {
    setLoading(true);
    try {
      if (clockedIn) {
        await api.post('/hr/attendance/clock-out');
        setClockedIn(false);
        localStorage.setItem('fastpos_clock_status', 'out');
        alert('Clocked out successfully!');
      } else {
        await api.post('/hr/attendance/clock-in');
        setClockedIn(true);
        localStorage.setItem('fastpos_clock_status', 'in');
        alert('Clocked in successfully!');
      }
    } catch (e: any) {
      alert(e.response?.data?.message || 'Failed to update attendance');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="flex items-center gap-3 bg-surface/50 border border-white/10 rounded-xl px-4 py-1.5 shadow-inner backdrop-blur-md">
      <div className="font-mono text-sm font-bold text-white/80">{time || '--:--:--'}</div>
      <div className="w-px h-4 bg-white/20"></div>
      <button 
        onClick={handleToggle}
        disabled={loading}
        className={`flex items-center gap-2 text-xs font-bold px-3 py-1.5 rounded-lg transition-all shadow-lg ${
          clockedIn 
            ? 'bg-rose-500/20 text-rose-400 border border-rose-500/30 hover:bg-rose-500/30' 
            : 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 hover:bg-emerald-500/30'
        }`}
      >
        <span className="relative flex h-2 w-2">
          {clockedIn && <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-rose-400 opacity-75"></span>}
          <span className={`relative inline-flex rounded-full h-2 w-2 ${clockedIn ? 'bg-rose-500' : 'bg-emerald-500'}`}></span>
        </span>
        {loading ? 'WAIT...' : (clockedIn ? 'CLOCK OUT' : 'CLOCK IN')}
      </button>
    </div>
  );
}
