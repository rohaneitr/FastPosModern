'use client';

import React, { useState, useEffect } from 'react';
import api from '@/lib/api';
import { usePosSounds } from '@/hooks/usePosSounds';

export default function TenantBrandingPage() {
  const { playTaskSuccess } = usePosSounds();
  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);

  const [dashboardLogoFile, setDashboardLogoFile] = useState<File | null>(null);
  const [dashboardLogoPreview, setDashboardLogoPreview] = useState<string>('');

  const [invoiceLogoFile, setInvoiceLogoFile] = useState<File | null>(null);
  const [invoiceLogoPreview, setInvoiceLogoPreview] = useState<string>('');

  useEffect(() => {
    // Basic setup, could fetch current logos if API returned them here
    setLoading(false);
  }, []);

  const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>, type: 'dashboard' | 'invoice') => {
    const file = e.target.files?.[0];
    if (file) {
      if (!file.type.startsWith('image/')) {
        alert('Please select an image file');
        return;
      }
      const url = URL.createObjectURL(file);
      if (type === 'dashboard') {
        setDashboardLogoFile(file);
        setDashboardLogoPreview(url);
      } else {
        setInvoiceLogoFile(file);
        setInvoiceLogoPreview(url);
      }
    }
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setSubmitting(true);

    try {
      const formData = new FormData();
      if (dashboardLogoFile) formData.append('dashboard_logo', dashboardLogoFile);
      if (invoiceLogoFile) formData.append('invoice_logo', invoiceLogoFile);

      await api.post('/business/branding', formData, {
        headers: { 'Content-Type': 'multipart/form-data' }
      });
      
      playTaskSuccess();
      alert('Tenant branding updated successfully!');
    } catch (err: any) {
      console.error(err);
      alert(err.response?.data?.message || 'Failed to update branding');
    } finally {
      setSubmitting(false);
    }
  };

  if (loading) return <div className="p-8 text-white">Loading...</div>;

  return (
    <div className="max-w-4xl mx-auto py-8 animate-in fade-in duration-500">
      <h1 className="text-3xl font-bold text-white mb-2">Tenant Branding</h1>
      <p className="text-text-muted mb-8">Customize the look and feel of your workspace and invoices.</p>

      <form onSubmit={handleSubmit} className="flex flex-col gap-8">
        <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
          
          {/* Dashboard Logo Dropzone */}
          <div className="bg-surface border border-border rounded-2xl shadow-xl overflow-hidden p-6 flex flex-col">
            <h3 className="text-lg font-bold text-white mb-1">Dashboard Logo</h3>
            <p className="text-sm text-text-muted mb-4">Appears in the top-left of the sidebar navigation.</p>
            
            <label className="flex-1 min-h-[200px] border-2 border-dashed border-border hover:border-primary/50 bg-background rounded-xl flex flex-col items-center justify-center cursor-pointer transition-colors p-4 group relative">
              <input type="file" className="hidden" accept="image/*" onChange={e => handleFileChange(e, 'dashboard')} />
              {dashboardLogoPreview ? (
                <img src={dashboardLogoPreview} alt="Dashboard Preview" className="max-w-full max-h-[150px] object-contain group-hover:opacity-50 transition-opacity" />
              ) : (
                <div className="text-center text-text-muted group-hover:text-primary transition-colors">
                  <svg className="w-10 h-10 mx-auto mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                  <span className="text-sm font-medium">Click to upload or drag & drop</span>
                </div>
              )}
            </label>
          </div>

          {/* Invoice Logo Dropzone */}
          <div className="bg-surface border border-border rounded-2xl shadow-xl overflow-hidden p-6 flex flex-col">
            <h3 className="text-lg font-bold text-white mb-1">Invoice Logo</h3>
            <p className="text-sm text-text-muted mb-4">Printed at the top of customer receipts and invoices.</p>
            
            <label className="flex-1 min-h-[200px] border-2 border-dashed border-border hover:border-primary/50 bg-background rounded-xl flex flex-col items-center justify-center cursor-pointer transition-colors p-4 group relative">
              <input type="file" className="hidden" accept="image/*" onChange={e => handleFileChange(e, 'invoice')} />
              {invoiceLogoPreview ? (
                <img src={invoiceLogoPreview} alt="Invoice Preview" className="max-w-full max-h-[150px] object-contain group-hover:opacity-50 transition-opacity" />
              ) : (
                <div className="text-center text-text-muted group-hover:text-primary transition-colors">
                  <svg className="w-10 h-10 mx-auto mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                  <span className="text-sm font-medium">Click to upload or drag & drop</span>
                </div>
              )}
            </label>
          </div>

        </div>

        <div className="flex justify-end">
          <button 
            type="submit" 
            disabled={submitting || (!dashboardLogoFile && !invoiceLogoFile)}
            className="bg-primary hover:brightness-110 text-white px-8 py-3 rounded-xl font-bold transition-all duration-150 active:scale-[0.97] shadow-lg disabled:opacity-50"
          >
            {submitting ? 'Uploading...' : 'Save Branding Assets'}
          </button>
        </div>
      </form>
    </div>
  );
}
