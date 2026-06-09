'use client';

import React, { useState, useEffect } from 'react';
import api from '@/lib/api';

export default function PharmacyPage() {
  const [medicines, setMedicines] = useState([]);
  const [loading, setLoading] = useState(true);
  const [searchTerm, setSearchTerm] = useState('');
  
  const [currentPage, setCurrentPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const [totalItems, setTotalItems] = useState(0);

  useEffect(() => {
    const delayDebounceFn = setTimeout(() => {
      if (currentPage !== 1) setCurrentPage(1);
      else fetchMedicines();
    }, 500);
    return () => clearTimeout(delayDebounceFn);
  }, [searchTerm]);

  useEffect(() => {
    fetchMedicines();
  }, [currentPage]);

  const fetchMedicines = async () => {
    setLoading(true);
    try {
      const searchParam = searchTerm ? `&search=${encodeURIComponent(searchTerm)}` : '';
      const res = await api.get(`/products?page=${currentPage}${searchParam}`);
      
      setMedicines(res.data.data || res.data || []);
      if (res.data.last_page) {
        setTotalPages(res.data.last_page);
        setTotalItems(res.data.total);
      }
    } catch (e) {
      console.error(e);
    } finally {
      setLoading(false);
    }
  };

  const filteredMedicines = medicines; // Filtering is handled by API now

  return (
    <div className="flex flex-col gap-8 animate-in fade-in duration-500 pb-12 relative">
      <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
          <h1 className="text-4xl font-extrabold bg-clip-text text-transparent bg-gradient-to-r from-emerald-400 to-teal-500 tracking-tight">
            Pharmacy Inventory
          </h1>
          <p className="text-text-muted mt-2 text-sm max-w-xl leading-relaxed">
            Manage medicines, generic names, prescriptions, and stock levels explicitly designed for pharmaceutical distribution.
          </p>
        </div>
        <button className="flex-1 md:flex-none bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-400 hover:to-teal-400 text-white px-6 py-2.5 rounded-xl shadow-[0_0_20px_rgba(16,185,129,0.3)] font-bold transition-all flex items-center justify-center gap-2 transform hover:scale-105 active:scale-95">
          <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2.5} d="M12 4v16m8-8H4" /></svg>
          Add Medicine
        </button>
      </div>

      <div className="glass-card rounded-2xl border border-border p-6 relative overflow-hidden shadow-xl">
        <div className="flex gap-4 mb-6">
          <div className="relative w-full md:w-96">
            <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
              <svg className="w-5 h-5 text-text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
            </div>
            <input 
              type="text" 
              placeholder="Search by brand or generic name..." 
              value={searchTerm}
              onChange={e => setSearchTerm(e.target.value)}
              className="w-full bg-background border border-border rounded-xl pl-10 pr-4 py-2.5 text-white outline-none focus:border-emerald-500/50 focus:ring-2 focus:ring-emerald-500/20 transition-all placeholder:text-text-muted"
            />
          </div>
        </div>

        {loading ? (
          <div className="text-center py-16">
            <div className="inline-block animate-spin w-8 h-8 border-4 border-emerald-500 border-t-transparent rounded-full mb-4"></div>
            <div className="text-text-muted font-medium">Loading medical inventory...</div>
          </div>
        ) : (
          <div className="overflow-x-auto custom-scrollbar">
            <table className="w-full text-left border-collapse min-w-[800px]">
              <thead>
                <tr className="border-b border-border/50 text-text-muted text-sm uppercase tracking-wider">
                  <th className="pb-4 px-2 font-bold">Medicine Name</th>
                  <th className="pb-4 px-2 font-bold">Generic Name</th>
                  <th className="pb-4 px-2 font-bold">SKU</th>
                  <th className="pb-4 px-2 font-bold">Price</th>
                  <th className="pb-4 px-2 font-bold text-right">Status</th>
                </tr>
              </thead>
              <tbody>
                {filteredMedicines.length === 0 ? (
                  <tr>
                    <td colSpan={5} className="py-12 text-center">
                      <span className="text-4xl block mb-3 opacity-50">💊</span>
                      <p className="text-text-muted font-medium text-lg">No medicines found in inventory.</p>
                      <p className="text-sm text-text-muted/70 mt-1">Run the Parquet ingestion script or add medicines manually.</p>
                    </td>
                  </tr>
                ) : (
                  filteredMedicines.map((med: any) => (
                    <tr key={med.id} className="border-b border-border/30 hover:bg-surface/30 transition-colors group">
                      <td className="py-4 px-2 font-bold text-white flex items-center gap-3">
                        <div className="w-10 h-10 rounded-lg bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-500">
                          💊
                        </div>
                        {med.name}
                      </td>
                      <td className="py-4 px-2 text-sm text-text-muted font-medium">
                        {med.generic_name ? (
                          <span className="bg-surface border border-border px-2 py-1 rounded text-xs">{med.generic_name}</span>
                        ) : 'N/A'}
                      </td>
                      <td className="py-4 px-2 text-sm font-mono text-text-muted/80">{med.sku}</td>
                      <td className="py-4 px-2 font-bold text-emerald-400">৳{med.sell_price_inc_tax}</td>
                      <td className="py-4 px-2 text-right">
                        <span className="px-3 py-1 rounded-full text-xs font-bold bg-success/20 text-success border border-success/30 inline-block">
                          Active
                        </span>
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>
        )}

        {/* Pagination Footer */}
        {totalPages > 1 && !loading && (
          <div className="bg-surface/50 border-t border-border px-6 py-4 flex items-center justify-between mt-4 rounded-xl">
            <span className="text-sm text-text-muted font-medium">
              Showing page <span className="text-white">{currentPage}</span> of <span className="text-white">{totalPages}</span> 
              <span className="mx-2 opacity-50">|</span> 
              Total records: <span className="text-white">{totalItems}</span>
            </span>
            <div className="flex gap-2">
              <button 
                disabled={currentPage === 1}
                onClick={() => setCurrentPage(p => p - 1)}
                className="px-4 py-2 bg-background border border-border rounded-lg text-sm font-medium hover:bg-surface disabled:opacity-50 disabled:cursor-not-allowed transition-colors text-white"
              >
                Previous
              </button>
              <button 
                disabled={currentPage === totalPages}
                onClick={() => setCurrentPage(p => p + 1)}
                className="px-4 py-2 bg-background border border-border rounded-lg text-sm font-medium hover:bg-surface disabled:opacity-50 disabled:cursor-not-allowed transition-colors text-white"
              >
                Next
              </button>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
