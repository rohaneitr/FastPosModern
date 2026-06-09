'use client';

import React, { useState, useEffect, useRef } from 'react';
import api from '@/lib/api';

export default function WarrantyRMAPage() {
  const [serialQuery, setSerialQuery] = useState('');
  const [isLoading, setIsLoading] = useState(false);
  const [result, setResult] = useState<any>(null);
  const [error, setError] = useState<string | null>(null);
  
  const [tickets, setTickets] = useState<any[]>([]);
  const [showModal, setShowModal] = useState(false);
  const [complaint, setComplaint] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);

  const [toast, setToast] = useState<{message: string, type: 'success'|'error'} | null>(null);

  const showToast = (message: string, type: 'success'|'error') => {
    setToast({ message, type });
    setTimeout(() => setToast(null), 4000);
  };

  useEffect(() => {
    fetchTickets();
  }, []);

  const fetchTickets = async () => {
    try {
      const res = await api.get('/rma');
      setTickets(res.data);
    } catch (e) {
      console.error(e);
    }
  };

  const handleCheck = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!serialQuery.trim()) return;
    
    setIsLoading(true);
    setError(null);
    setResult(null);

    try {
      const res = await api.get(`/products/warranty-check?serial=${encodeURIComponent(serialQuery.trim())}`);
      setResult(res.data);
    } catch (err: any) {
      if (err.response?.data?.status === 'INVALID') {
        setResult(err.response.data);
      } else {
        setError(err.response?.data?.message || 'Failed to check warranty. Serial number might not exist.');
      }
    } finally {
      setIsLoading(false);
    }
  };

  const handleCreateTicket = async () => {
    if (!complaint.trim()) return showToast('Please enter complaint details', 'error');
    setIsSubmitting(true);
    try {
      await api.post('/rma', {
        transaction_id: result.transaction_id,
        product_id: result.product_id,
        serial_number: result.serial_number,
        complaint_details: complaint,
      });
      showToast('RMA Ticket Created Successfully!', 'success');
      setShowModal(false);
      setComplaint('');
      setResult(null);
      setSerialQuery('');
      fetchTickets();
    } catch (err: any) {
      showToast(err.response?.data?.message || 'Failed to create ticket', 'error');
    } finally {
      setIsSubmitting(false);
    }
  };

  const updateStatus = async (id: number, status: string) => {
    try {
      await api.put(`/rma/${id}/status`, { status });
      showToast('Status updated. Customer notified if applicable.', 'success');
      fetchTickets();
    } catch (err: any) {
      showToast(err.response?.data?.message || 'Failed to update status', 'error');
    }
  };

  return (
    <div className="flex flex-col min-h-screen p-4 gap-8 animate-in fade-in duration-500 overflow-y-auto">
      {toast && (
        <div className={`fixed top-8 left-1/2 -translate-x-1/2 z-50 px-6 py-3 rounded-full shadow-2xl font-semibold flex items-center gap-3 animate-in slide-in-from-top-10 backdrop-blur-md border
          ${toast.type === 'success' ? 'bg-emerald-500/20 text-emerald-300 border-emerald-500/50' : 'bg-red-500/20 text-red-300 border-red-500/50'}`}>
          {toast.type === 'success' ? '✓' : '✕'} {toast.message}
        </div>
      )}

      {/* Top Warranty Checker Area */}
      <div className="flex flex-col items-center justify-center w-full max-w-4xl mx-auto">
        <div className="text-center mb-8">
          <h1 className="text-4xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-blue-400 to-emerald-400">
            Warranty &amp; RMA Engine
          </h1>
          <p className="text-text-muted mt-3 text-lg">Scan or enter an IMEI / Serial Number to verify lifecycle and process claims.</p>
        </div>

        <form onSubmit={handleCheck} className="w-full flex gap-4 mb-8 relative group">
          <div className="absolute inset-0 bg-primary/20 blur-xl rounded-full opacity-0 group-hover:opacity-100 transition-opacity duration-500 pointer-events-none"></div>
          <input 
            type="text" 
            value={serialQuery} 
            onChange={(e) => setSerialQuery(e.target.value)}
            placeholder="Scan Serial Number / IMEI"
            className="flex-1 bg-surface/80 border border-border/50 rounded-2xl px-6 py-4 text-white text-xl font-mono outline-none focus:ring-2 focus:ring-primary shadow-2xl relative z-10 backdrop-blur-sm"
            autoFocus
          />
          <button 
            type="submit" 
            disabled={isLoading || !serialQuery.trim()}
            className="bg-primary hover:bg-primary-hover text-white font-bold px-8 py-4 rounded-2xl shadow-lg transition-all disabled:opacity-50 relative z-10 flex items-center gap-2 text-lg"
          >
            {isLoading ? (
              <span className="w-6 h-6 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
            ) : 'Check'}
          </button>
        </form>

        {error && (
          <div className="w-full bg-danger/10 border border-danger/30 text-danger-light p-6 rounded-2xl text-center shadow-lg animate-in slide-in-from-bottom-4">
            <span className="text-3xl mb-2 block">🚫</span>
            <p className="font-semibold text-lg">{error}</p>
          </div>
        )}

        {result && (
          <div className="w-full bg-surface/60 border border-border/50 rounded-3xl p-8 shadow-2xl animate-in slide-in-from-bottom-8 backdrop-blur-md relative overflow-hidden">
            <div className={`absolute -top-20 -right-20 w-40 h-40 blur-[80px] rounded-full pointer-events-none ${result.status === 'VALID' ? 'bg-emerald-500/30' : 'bg-rose-500/30'}`}></div>
            
            <div className="flex flex-col items-center mb-8 pb-8 border-b border-border/50 text-center relative z-10">
              <span className={`text-6xl mb-4 ${
                result.status === 'VALID' ? 'text-emerald-400' 
                : result.status === 'EXPIRED' ? 'text-rose-400' 
                : 'text-amber-400'
              }`}>
                {result.status === 'VALID' ? '✅' : result.status === 'EXPIRED' ? '❌' : '⚠️'}
              </span>
              <h2 className={`text-3xl font-bold tracking-wider ${
                result.status === 'VALID' ? 'text-emerald-400' 
                : result.status === 'EXPIRED' ? 'text-rose-400' 
                : 'text-amber-400'
              }`}>
                {result.status === 'VALID' ? 'IN WARRANTY' : result.status === 'EXPIRED' ? 'OUT OF WARRANTY' : 'INVALID'}
              </h2>
              {result.status === 'INVALID' && (
                <p className="text-text-muted mt-2">{result.message}</p>
              )}
            </div>

            {result.status !== 'INVALID' && (
              <div className="grid grid-cols-1 md:grid-cols-2 gap-8 relative z-10">
                <div>
                  <h3 className="text-xs uppercase tracking-wider text-text-muted font-bold mb-1">Product Details</h3>
                  <p className="text-xl font-semibold text-white mb-6">{result.product_name}</p>

                  <h3 className="text-xs uppercase tracking-wider text-text-muted font-bold mb-1">Customer</h3>
                  <p className="text-xl font-semibold text-white truncate">{result.customer_name}</p>
                </div>

                <div className="space-y-6">
                  <div>
                    <h3 className="text-xs uppercase tracking-wider text-text-muted font-bold mb-1">Invoice Reference</h3>
                    <p className="font-mono text-lg text-blue-300 bg-blue-500/10 inline-block px-3 py-1 rounded-lg border border-blue-500/20">{result.invoice_no}</p>
                  </div>
                  
                  <div>
                    <h3 className="text-xs uppercase tracking-wider text-text-muted font-bold mb-1">Purchase Date</h3>
                    <p className="text-white font-medium">{new Date(result.sale_date).toLocaleDateString(undefined, { weekday: 'short', year: 'numeric', month: 'long', day: 'numeric' })}</p>
                  </div>

                  <div>
                    <h3 className="text-xs uppercase tracking-wider text-text-muted font-bold mb-1">Coverage Valid Until</h3>
                    <p className={`text-lg font-bold ${result.status === 'VALID' ? 'text-emerald-400' : 'text-rose-400'}`}>
                      {new Date(result.warranty_end).toLocaleDateString(undefined, { year: 'numeric', month: 'long', day: 'numeric' })}
                    </p>
                    {result.status === 'VALID' && (
                      <p className="text-xs text-emerald-500/80 mt-1 font-semibold">{result.days_remaining} Days Remaining</p>
                    )}
                  </div>
                </div>
              </div>
            )}

            {(result.status === 'VALID' || result.status === 'EXPIRED') && (
              <button 
                onClick={() => setShowModal(true)}
                className="mt-8 w-full bg-blue-600 hover:bg-blue-500 text-white font-bold py-4 rounded-xl transition-colors shadow-lg shadow-blue-600/20 text-lg relative z-10"
              >
                Initiate Warranty Claim
              </button>
            )}
          </div>
        )}
      </div>

      {/* Active RMA Tracking Table */}
      <div className="w-full max-w-6xl mx-auto glass-card rounded-2xl border border-border flex flex-col mt-4">
        <div className="p-5 border-b border-border flex justify-between items-center bg-surface/30">
          <h2 className="text-2xl font-bold text-white">Active RMA Tickets</h2>
          <button onClick={fetchTickets} className="text-text-muted hover:text-white transition-colors bg-surface/50 px-4 py-2 rounded-lg">🔄 Refresh</button>
        </div>
        
        <div className="overflow-x-auto">
          <table className="w-full text-left border-collapse">
            <thead>
              <tr className="border-b border-border bg-surface/50">
                <th className="p-4 text-xs font-semibold text-text-muted uppercase tracking-wider">Ticket / Date</th>
                <th className="p-4 text-xs font-semibold text-text-muted uppercase tracking-wider">Product / Serial</th>
                <th className="p-4 text-xs font-semibold text-text-muted uppercase tracking-wider">Customer</th>
                <th className="p-4 text-xs font-semibold text-text-muted uppercase tracking-wider">Complaint</th>
                <th className="p-4 text-xs font-semibold text-text-muted uppercase tracking-wider">Status</th>
              </tr>
            </thead>
            <tbody>
              {tickets.length === 0 ? (
                <tr><td colSpan={5} className="p-8 text-center text-text-muted text-lg">No active RMA tickets found.</td></tr>
              ) : tickets.map(ticket => (
                <tr key={ticket.id} className="border-b border-border/50 hover:bg-surface/30 transition-colors">
                  <td className="p-4">
                    <div className="font-bold text-white">RMA-{ticket.id.toString().padStart(4, '0')}</div>
                    <div className="text-xs text-text-muted">{new Date(ticket.created_at).toLocaleDateString()}</div>
                  </td>
                  <td className="p-4">
                    <div className="font-medium text-white max-w-[200px] truncate" title={ticket.product_name}>{ticket.product_name}</div>
                    <div className="text-xs font-mono text-emerald-400 mt-0.5">{ticket.serial_number}</div>
                  </td>
                  <td className="p-4">
                    <div className="text-white">{ticket.contact_name || `${ticket.first_name || ''} ${ticket.last_name || ''}`.trim() || 'Walk-in'}</div>
                    {ticket.contact_mobile && <div className="text-xs text-text-muted">📞 {ticket.contact_mobile}</div>}
                  </td>
                  <td className="p-4 max-w-[300px]">
                    <p className="text-sm text-text-muted line-clamp-2" title={ticket.complaint_details}>{ticket.complaint_details}</p>
                  </td>
                  <td className="p-4">
                    <select 
                      value={ticket.status} 
                      onChange={(e) => updateStatus(ticket.id, e.target.value)}
                      className={`text-sm font-bold rounded-lg px-3 py-1.5 border outline-none cursor-pointer w-full max-w-[200px] ${
                        ticket.status === 'Received from Customer' ? 'bg-purple-500/20 text-purple-400 border-purple-500/50' :
                        ticket.status === 'Sent to Supplier' ? 'bg-blue-500/20 text-blue-400 border-blue-500/50' :
                        ticket.status === 'Fixed/Replaced' ? 'bg-emerald-500/20 text-emerald-400 border-emerald-500/50' :
                        'bg-gray-500/20 text-gray-400 border-gray-500/50'
                      }`}
                    >
                      <option value="Received from Customer">Received from Customer</option>
                      <option value="Sent to Supplier">Sent to Supplier</option>
                      <option value="Fixed/Replaced">Fixed/Replaced</option>
                      <option value="Delivered to Customer">Delivered to Customer</option>
                    </select>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>

      {/* RMA Creation Modal */}
      {showModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm animate-in fade-in p-4">
          <div className="bg-surface border border-border rounded-2xl p-6 w-full max-w-lg shadow-2xl animate-in zoom-in-95">
            <div className="flex justify-between items-center mb-6">
              <h2 className="text-xl font-bold text-white">Initiate Warranty Claim</h2>
              <button onClick={() => setShowModal(false)} className="text-text-muted hover:text-white transition-colors text-xl">✕</button>
            </div>
            
            <div className="bg-background/50 rounded-xl p-4 border border-border mb-6">
              <p className="text-sm text-text-muted mb-1">Product: <span className="font-bold text-white">{result?.product_name}</span></p>
              <p className="text-sm text-text-muted mb-1">Serial: <span className="font-mono text-emerald-400">{result?.serial_number}</span></p>
              <p className="text-sm text-text-muted">Customer: <span className="font-medium text-white">{result?.customer_name}</span></p>
            </div>

            <div className="mb-6">
              <label className="block text-sm font-bold text-text-muted uppercase tracking-wider mb-2">Customer Complaint Details <span className="text-rose-500">*</span></label>
              <textarea 
                rows={4}
                value={complaint}
                onChange={e => setComplaint(e.target.value)}
                placeholder="E.g., Display flickering after 2 hours of use..."
                className="w-full bg-background border border-border rounded-xl p-3 text-white outline-none focus:border-primary transition-colors resize-none"
                required
              />
            </div>

            <div className="flex gap-3">
              <button type="button" onClick={() => setShowModal(false)} className="flex-1 py-3 bg-background border border-border rounded-xl font-medium text-text-muted hover:text-white transition-colors">Cancel</button>
              <button 
                onClick={handleCreateTicket} 
                disabled={isSubmitting || !complaint.trim()} 
                className="flex-[2] bg-primary hover:bg-primary-hover text-white rounded-xl font-bold transition-all disabled:opacity-50 flex items-center justify-center gap-2 shadow-lg shadow-primary/20"
              >
                {isSubmitting ? <span className="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"/> : null}
                Create RMA Ticket
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
