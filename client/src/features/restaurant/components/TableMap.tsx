'use client';

import React, { useState, useEffect, useCallback } from 'react';
import { useEcho } from '@/hooks/useEcho';
import api from '@/lib/api';

interface Table {
  id: number;
  table_number: string;
  status: 'Free' | 'Occupied' | 'Reserved';
  seats: number;
  current_order_id?: number | null;
}

export function TableMap({ businessId }: { businessId: number }) {
  const [tables, setTables] = useState<Table[]>([]);
  const [loading, setLoading] = useState(true);
  const { echo } = useEcho();

  const fetchTables = useCallback(async () => {
    try {
      const res = await api.get('/restaurant/tables');
      setTables(res.data.data || res.data);
    } catch (e) {

    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchTables();

    if (echo) {
      const channel = echo.private(`business.${businessId}.restaurant`);
      channel.listen('.TableStatusChangedEvent', (e: any) => {
        setTables(prev => prev.map(t => t.id === e.table_id ? { ...t, status: e.status, current_order_id: e.order_id } : t));
      });

      return () => {
        channel.stopListening('.TableStatusChangedEvent');
        echo.leaveChannel(`business.${businessId}.restaurant`);
      };
    }
  }, [businessId, echo, fetchTables]);

  const handleTableClick = (table: Table) => {
    if (table.status === 'Occupied') {
      alert(`Table ${table.table_number} is currently occupied. Merge or checkout first.`);
      return;
    }
    // Proceed to open POS for this table
    alert(`Opening POS for Table ${table.table_number}...`);
  };

  if (loading) {
    return <div className="p-8 text-center text-text-muted">Loading Floor Plan...</div>;
  }

  return (
    <div className="p-6 bg-surface border border-border rounded-2xl shadow-xl">
      <div className="flex justify-between items-center mb-6">
        <div>
          <h2 className="text-2xl font-black text-white">Floor Plan</h2>
          <p className="text-sm text-text-muted">Real-time table status sync</p>
        </div>
        <div className="flex gap-4">
          <div className="flex items-center gap-2"><div className="w-3 h-3 rounded-full bg-emerald-500"></div><span className="text-xs text-white font-medium">Free</span></div>
          <div className="flex items-center gap-2"><div className="w-3 h-3 rounded-full bg-rose-500"></div><span className="text-xs text-white font-medium">Occupied</span></div>
          <div className="flex items-center gap-2"><div className="w-3 h-3 rounded-full bg-amber-500"></div><span className="text-xs text-white font-medium">Reserved</span></div>
        </div>
      </div>

      <div className="grid grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-6">
        {tables.map(table => (
          <button 
            key={table.id}
            onClick={() => handleTableClick(table)}
            className={`
              relative aspect-square rounded-2xl flex flex-col items-center justify-center p-4 transition-all group
              ${table.status === 'Free' ? 'bg-surface/50 border-2 border-emerald-500/50 hover:bg-emerald-500/10 hover:border-emerald-500 shadow-[0_0_15px_rgba(16,185,129,0.1)]' : ''}
              ${table.status === 'Occupied' ? 'bg-rose-500/10 border-2 border-rose-500 shadow-[0_0_15px_rgba(244,63,94,0.2)]' : ''}
              ${table.status === 'Reserved' ? 'bg-amber-500/10 border-2 border-amber-500 shadow-[0_0_15px_rgba(245,158,11,0.2)]' : ''}
            `}
          >
            <div className="text-3xl mb-2 opacity-80">🍽️</div>
            <div className="font-bold text-white text-lg">{table.table_number}</div>
            <div className="text-xs text-text-muted mt-1">{table.seats} Seats</div>
            
            {table.status === 'Occupied' && (
              <div className="absolute top-2 right-2 flex gap-1">
                <span className="relative flex h-3 w-3">
                  <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-rose-400 opacity-75"></span>
                  <span className="relative inline-flex rounded-full h-3 w-3 bg-rose-500"></span>
                </span>
              </div>
            )}
          </button>
        ))}
        {tables.length === 0 && (
          <div className="col-span-full py-12 text-center text-text-muted">
            No tables configured. Please add tables in Restaurant Settings.
          </div>
        )}
      </div>
    </div>
  );
}
