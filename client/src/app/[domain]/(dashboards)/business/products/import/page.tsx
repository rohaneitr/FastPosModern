'use client';

import React, { useState, useRef } from 'react';
import { useRouter } from 'next/navigation';
import api from '@/lib/api';

export default function ImportProductsPage() {
  const router = useRouter();
  const [file, setFile] = useState<File | null>(null);
  const [loading, setLoading] = useState(false);
  const [dragActive, setDragActive] = useState(false);
  const [result, setResult] = useState<{message: string, count?: number, error?: string} | null>(null);
  const fileInputRef = useRef<HTMLInputElement>(null);

  const handleDrag = (e: React.DragEvent) => {
    e.preventDefault();
    e.stopPropagation();
    if (e.type === "dragenter" || e.type === "dragover") {
      setDragActive(true);
    } else if (e.type === "dragleave") {
      setDragActive(false);
    }
  };

  const handleDrop = (e: React.DragEvent) => {
    e.preventDefault();
    e.stopPropagation();
    setDragActive(false);
    if (e.dataTransfer.files && e.dataTransfer.files[0]) {
      const droppedFile = e.dataTransfer.files[0];
      if (droppedFile.name.endsWith('.csv') || droppedFile.name.endsWith('.txt')) {
        setFile(droppedFile);
      } else {
        alert('Please drop a valid .csv file');
      }
    }
  };

  const handleFileSelect = (e: React.ChangeEvent<HTMLInputElement>) => {
    if (e.target.files && e.target.files[0]) {
      setFile(e.target.files[0]);
    }
  };

  const downloadSample = () => {
    const csvContent = "Name,SKU,Purchase Price,Sell Price,Qty\nPremium Headphones,HD-001,50.00,120.00,25\nWireless Mouse,WM-02,15.00,35.00,100";
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.setAttribute('href', url);
    link.setAttribute('download', 'sample_products.csv');
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
  };

  const handleImport = async () => {
    if (!file) return;
    setLoading(true);
    setResult(null);

    const formData = new FormData();
    formData.append('file', file);
    
    // In a real app we'd let user select location, but we'll default to 1 or pull from their locations list
    // For simplicity, passing 1. The backend assumes the user has access.
    formData.append('location_id', '1');

    try {
      const res = await api.post('/import/products', formData, {
        headers: {
          'Content-Type': 'multipart/form-data',
        }
      });
      setResult({ message: res.data.message, count: res.data.count });
      setFile(null);
    } catch (err: any) {
      setResult({ 
        message: 'Import failed', 
        error: err.response?.data?.message || err.message 
      });
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="flex flex-col gap-8 animate-in fade-in duration-500 pb-12 max-w-4xl mx-auto mt-8">
      
      <div className="flex justify-between items-end">
        <div>
          <h1 className="text-3xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-emerald-400 to-cyan-500">
            Bulk Product Import
          </h1>
          <p className="text-text-muted mt-1">Easily migrate your entire inventory via CSV.</p>
        </div>
        <button 
          onClick={downloadSample}
          className="px-4 py-2 bg-surface hover:bg-surface/80 border border-border rounded-xl text-sm font-semibold transition-colors flex items-center gap-2 text-white"
        >
          <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
          Download Sample CSV
        </button>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-3 gap-8">
        
        {/* Instructions */}
        <div className="md:col-span-1 flex flex-col gap-4">
          <div className="glass-card p-5 rounded-2xl border border-border">
            <h2 className="font-bold text-white mb-3 text-lg">Format Guidelines</h2>
            <ul className="text-sm text-text-muted space-y-2">
              <li><strong className="text-emerald-400">Name</strong> (Required): The product name.</li>
              <li><strong className="text-emerald-400">SKU</strong> (Optional): Generated automatically if left blank.</li>
              <li><strong className="text-emerald-400">Purchase Price</strong> (Required): The cost to you.</li>
              <li><strong className="text-emerald-400">Sell Price</strong> (Required): Final retail price.</li>
              <li><strong className="text-emerald-400">Qty</strong> (Required): Current stock level.</li>
            </ul>
            <div className="mt-4 p-3 bg-amber-500/10 border border-amber-500/30 rounded-xl">
              <p className="text-xs text-amber-200">
                <strong>Notice:</strong> For strict hardware integrity, bulk imported items are flagged without serials by default. You can assign serials later.
              </p>
            </div>
          </div>
        </div>

        {/* Upload Area */}
        <div className="md:col-span-2">
          
          {result?.count !== undefined && (
            <div className="mb-6 p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30 flex items-center gap-4 animate-in slide-in-from-top-4">
              <div className="w-10 h-10 bg-emerald-500/20 rounded-full flex items-center justify-center text-emerald-400 text-xl">🎉</div>
              <div>
                <h3 className="font-bold text-emerald-400">Import Successful</h3>
                <p className="text-sm text-emerald-200/80">{result.message}</p>
              </div>
            </div>
          )}

          {result?.error && (
            <div className="mb-6 p-4 rounded-xl bg-rose-500/10 border border-rose-500/30 animate-in slide-in-from-top-4">
              <h3 className="font-bold text-rose-400">Import Failed</h3>
              <p className="text-sm text-rose-200/80 mt-1">{result.error}</p>
            </div>
          )}

          <div 
            className={`glass-card rounded-2xl border-2 border-dashed transition-all p-12 flex flex-col items-center justify-center text-center cursor-pointer relative overflow-hidden
              ${dragActive ? 'border-emerald-500 bg-emerald-500/5' : 'border-border hover:border-emerald-500/30 hover:bg-surface/50'}
              ${file ? 'border-emerald-500/50 bg-emerald-500/5' : ''}
            `}
            onDragEnter={handleDrag}
            onDragLeave={handleDrag}
            onDragOver={handleDrag}
            onDrop={handleDrop}
            onClick={() => !loading && fileInputRef.current?.click()}
          >
            <input 
              ref={fileInputRef}
              type="file" 
              accept=".csv"
              onChange={handleFileSelect}
              className="hidden"
            />
            
            {file ? (
              <div className="flex flex-col items-center animate-in zoom-in-95 duration-300">
                <div className="w-16 h-16 bg-emerald-500/20 text-emerald-400 rounded-2xl flex items-center justify-center mb-4">
                  <svg className="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                </div>
                <h3 className="text-xl font-bold text-white">{file.name}</h3>
                <p className="text-sm text-text-muted mt-1">{(file.size / 1024).toFixed(2)} KB</p>
              </div>
            ) : (
              <div className="flex flex-col items-center">
                <div className="w-16 h-16 bg-surface/80 text-text-muted rounded-2xl flex items-center justify-center mb-4 border border-border">
                  <svg className="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" /></svg>
                </div>
                <h3 className="text-lg font-bold text-white mb-1">Drag & Drop your CSV file here</h3>
                <p className="text-sm text-text-muted">or click to browse from your computer</p>
              </div>
            )}
          </div>

          <div className="mt-6 flex justify-end gap-3">
            <button 
              onClick={() => router.back()}
              className="px-6 py-3 rounded-xl font-semibold text-text-muted hover:text-white transition-colors"
            >
              Cancel
            </button>
            <button 
              onClick={handleImport}
              disabled={!file || loading}
              className="bg-gradient-to-r from-emerald-500 to-teal-600 hover:from-emerald-400 hover:to-teal-500 text-white px-8 py-3 rounded-xl font-bold shadow-lg shadow-emerald-500/20 transition-all disabled:opacity-50 flex items-center gap-2"
            >
              {loading ? (
                <><span className="w-5 h-5 border-2 border-white/30 border-t-white rounded-full animate-spin" /> Importing...</>
              ) : 'Start Import'}
            </button>
          </div>

        </div>
      </div>
    </div>
  );
}
