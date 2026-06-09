'use client';

import React, { useState, useEffect } from 'react';
import api from '@/lib/api';

export default function PayrollGeneratorPage() {
  const [payrolls, setPayrolls] = useState<any[]>([]);
  const [employees, setEmployees] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
  
  // Generator State
  const [showGenerator, setShowGenerator] = useState(false);
  const [genForm, setGenForm] = useState({
    user_id: '', month: new Date().toISOString().slice(0, 7), bonus_commission: '0', deductions_fines: '0'
  });
  const [submitting, setSubmitting] = useState(false);

  // Payslip State
  const [selectedPayslip, setSelectedPayslip] = useState<any>(null);

  useEffect(() => {
    fetchData();
  }, []);

  const fetchData = async () => {
    setLoading(true);
    try {
      const [payRes, empRes] = await Promise.all([
        api.get('/hr/payrolls'),
        api.get('/hr/employees')
      ]);
      if (payRes.data) setPayrolls(payRes.data);
      if (empRes.data) setEmployees(empRes.data);
    } catch (e) {
      console.error(e);
    } finally {
      setLoading(false);
    }
  };

  const handleGenerate = async (e: React.FormEvent) => {
    e.preventDefault();
    setSubmitting(true);
    try {
      await api.post('/hr/payrolls/generate', {
        user_id: genForm.user_id,
        month: genForm.month,
        bonus_commission: parseFloat(genForm.bonus_commission),
        deductions_fines: parseFloat(genForm.deductions_fines)
      });
      setShowGenerator(false);
      fetchData();
      alert('Payroll generated successfully!');
    } catch (err: any) {
      alert(err.response?.data?.message || 'Failed to generate payroll');
    } finally {
      setSubmitting(false);
    }
  };

  const handlePay = async (id: number) => {
    if (!confirm('Mark as paid and deduct from global expense ledger?')) return;
    try {
      await api.post(`/hr/payrolls/${id}/pay`, { payment_method: 'Cash' });
      fetchData();
      alert('Payroll marked as paid & expense recorded.');
    } catch (e: any) {
      alert(e.response?.data?.message || 'Failed to mark as paid');
    }
  };

  const printPayslip = () => {
    window.print();
  };

  return (
    <div className="flex flex-col h-full gap-8 animate-in fade-in duration-500 pb-12">
      <div className="flex justify-between items-center hide-on-print">
        <div>
          <h1 className="text-3xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-emerald-400 to-teal-500">
            Smart Payroll Generator
          </h1>
          <p className="text-text-muted mt-1">Auto-calculate salaries based on attendance & expenses.</p>
        </div>
        <button 
          onClick={() => setShowGenerator(true)}
          className="bg-gradient-to-r from-emerald-500 to-teal-600 hover:from-emerald-600 hover:to-teal-700 text-white px-6 py-2.5 rounded-xl shadow-lg shadow-emerald-500/25 font-bold transition-all">
          + Generate Payroll
        </button>
      </div>

      <div className="glass-card rounded-xl overflow-hidden border border-border hide-on-print">
        <div className="w-full overflow-x-auto">
          <table className="w-full text-left text-sm">
            <thead className="bg-surface/50 border-b border-border">
              <tr>
                <th className="p-4 font-semibold text-text-muted">Month / Ref</th>
                <th className="p-4 font-semibold text-text-muted">Employee</th>
                <th className="p-4 font-semibold text-text-muted">Days (W/P)</th>
                <th className="p-4 font-semibold text-text-muted text-right">Gross Salary</th>
                <th className="p-4 font-semibold text-text-muted text-right">Net Salary</th>
                <th className="p-4 font-semibold text-text-muted text-center">Status</th>
                <th className="p-4 font-semibold text-text-muted text-center">Action</th>
              </tr>
            </thead>
            <tbody>
              {loading ? (
                <tr><td colSpan={7} className="p-8 text-center text-text-muted">Loading payrolls...</td></tr>
              ) : payrolls.length === 0 ? (
                <tr><td colSpan={7} className="p-8 text-center text-text-muted">No payrolls generated yet.</td></tr>
              ) : payrolls.map(pay => (
                <tr key={pay.id} className="border-b border-border/50 hover:bg-white/5 transition-colors">
                  <td className="p-4">
                    <div className="font-bold text-emerald-400 text-lg">{pay.month}</div>
                    <div className="text-xs font-mono text-text-muted">{pay.reference_no}</div>
                  </td>
                  <td className="p-4 font-bold text-white">{pay.user?.first_name} {pay.user?.last_name}</td>
                  <td className="p-4 font-mono text-text-muted">
                    {pay.total_working_days} / <span className="text-white">{pay.present_days}</span>
                  </td>
                  <td className="p-4 text-right text-text-muted">${parseFloat(pay.gross_salary).toFixed(2)}</td>
                  <td className="p-4 text-right font-bold text-lg">${parseFloat(pay.net_salary).toFixed(2)}</td>
                  <td className="p-4 text-center">
                    <span className={`px-2 py-1 rounded-full text-xs font-bold uppercase ${pay.payment_status === 'paid' ? 'bg-success/20 text-success' : 'bg-warning/20 text-warning'}`}>
                      {pay.payment_status}
                    </span>
                  </td>
                  <td className="p-4 text-center flex flex-col gap-1 items-center justify-center">
                    {pay.payment_status === 'due' && (
                      <button onClick={() => handlePay(pay.id)} className="text-xs bg-emerald-500/20 text-emerald-400 hover:bg-emerald-500 hover:text-white border border-emerald-500/30 px-3 py-1 rounded transition-all">
                        Mark Paid
                      </button>
                    )}
                    <button onClick={() => setSelectedPayslip(pay)} className="text-xs text-text-muted hover:text-white underline">
                      View Slip
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>

      {/* Generator Modal */}
      {showGenerator && (
        <div className="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-black/80 backdrop-blur-md animate-in fade-in hide-on-print">
          <div className="bg-surface border border-border w-full max-w-lg rounded-2xl shadow-2xl p-6 relative">
            <h2 className="text-2xl font-bold text-white mb-6">Generate Payroll</h2>
            <form onSubmit={handleGenerate} className="flex flex-col gap-4">
              <div className="flex flex-col gap-1.5">
                <label className="text-sm font-medium text-text-muted">Employee *</label>
                <select required value={genForm.user_id} onChange={e => setGenForm({...genForm, user_id: e.target.value})} className="bg-background border border-border rounded-lg px-4 py-2.5 text-white outline-none focus:border-emerald-500/50">
                  <option value="">Select Staff Member</option>
                  {employees.map(emp => (
                    <option key={emp.id} value={emp.id}>{emp.first_name} {emp.last_name} ({emp.role})</option>
                  ))}
                </select>
              </div>
              <div className="flex flex-col gap-1.5">
                <label className="text-sm font-medium text-text-muted">Payroll Month *</label>
                <input type="month" required value={genForm.month} onChange={e => setGenForm({...genForm, month: e.target.value})} className="bg-background border border-border rounded-lg px-4 py-2.5 text-white outline-none focus:border-emerald-500/50 [color-scheme:dark]" />
              </div>
              <div className="grid grid-cols-2 gap-4">
                <div className="flex flex-col gap-1.5">
                  <label className="text-sm font-medium text-emerald-400">Bonus / Commission (+)</label>
                  <input type="number" step="0.01" value={genForm.bonus_commission} onChange={e => setGenForm({...genForm, bonus_commission: e.target.value})} className="bg-background border border-emerald-500/30 rounded-lg px-4 py-2 text-emerald-400 outline-none focus:border-emerald-500" />
                </div>
                <div className="flex flex-col gap-1.5">
                  <label className="text-sm font-medium text-rose-400">Deductions / Fines (-)</label>
                  <input type="number" step="0.01" value={genForm.deductions_fines} onChange={e => setGenForm({...genForm, deductions_fines: e.target.value})} className="bg-background border border-rose-500/30 rounded-lg px-4 py-2 text-rose-400 outline-none focus:border-rose-500" />
                </div>
              </div>
              <div className="bg-emerald-500/10 p-4 rounded-xl border border-emerald-500/20 mt-2">
                <p className="text-xs text-text-muted mb-1">Calculation Logic</p>
                <p className="text-sm text-white font-mono">Gross = (Base / Working Days) * Present Days</p>
                <p className="text-sm text-white font-mono">Net = Gross + Bonus - Deductions</p>
              </div>
              <div className="flex justify-end gap-3 mt-4">
                <button type="button" onClick={() => setShowGenerator(false)} className="px-5 py-2.5 rounded-lg text-text-muted hover:text-white font-medium">Cancel</button>
                <button type="submit" disabled={submitting} className="bg-emerald-500 hover:bg-emerald-600 text-white px-6 py-2.5 rounded-lg font-bold shadow-lg transition-all disabled:opacity-50">
                  {submitting ? 'Calculating...' : 'Generate Pay Slip'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* A4 Payslip Modal / Print View */}
      {selectedPayslip && (
        <div className="fixed inset-0 z-[70] flex items-center justify-center p-4 bg-black/80 backdrop-blur-md animate-in fade-in hide-on-print">
          <div className="bg-white text-black w-full max-w-2xl min-h-[A4] rounded-sm shadow-2xl p-8 md:p-12 relative print:shadow-none print:w-full print:max-w-none">
            <button onClick={() => setSelectedPayslip(null)} className="absolute top-4 right-4 text-gray-400 hover:text-black hide-on-print text-2xl">&times;</button>
            <button onClick={printPayslip} className="absolute top-4 right-12 text-blue-600 font-bold hover:underline hide-on-print text-sm">🖨️ Print</button>
            
            <div className="border-b-2 border-black pb-4 mb-8 flex justify-between items-end">
              <div>
                <h1 className="text-3xl font-black uppercase tracking-tighter">Payslip</h1>
                <p className="text-sm text-gray-500 font-medium">FastPOS Enterprise</p>
              </div>
              <div className="text-right font-mono text-sm">
                <div>Ref: {selectedPayslip.reference_no}</div>
                <div>Month: <span className="font-bold">{selectedPayslip.month}</span></div>
                <div>Date: {new Date(selectedPayslip.created_at).toLocaleDateString()}</div>
              </div>
            </div>

            <div className="grid grid-cols-2 gap-8 mb-8 border-b border-gray-200 pb-8">
              <div>
                <h3 className="text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Employee Details</h3>
                <div className="font-bold text-xl">{selectedPayslip.user?.first_name} {selectedPayslip.user?.last_name}</div>
                <div className="text-sm text-gray-600">{selectedPayslip.user?.email}</div>
              </div>
              <div className="bg-gray-50 p-4 rounded text-sm font-mono">
                <div className="flex justify-between border-b border-gray-200 pb-1 mb-1"><span>Base Salary:</span> <span>${parseFloat(selectedPayslip.base_salary).toFixed(2)}</span></div>
                <div className="flex justify-between border-b border-gray-200 pb-1 mb-1"><span>Total Days:</span> <span>{selectedPayslip.total_working_days}</span></div>
                <div className="flex justify-between"><span>Present Days:</span> <span className="font-bold">{selectedPayslip.present_days}</span></div>
              </div>
            </div>

            <table className="w-full text-left mb-8">
              <thead>
                <tr className="border-b-2 border-black text-sm uppercase tracking-wider">
                  <th className="py-2">Description</th>
                  <th className="py-2 text-right">Earnings (+)</th>
                  <th className="py-2 text-right">Deductions (-)</th>
                </tr>
              </thead>
              <tbody className="font-mono text-sm">
                <tr className="border-b border-gray-200">
                  <td className="py-3 font-medium">Basic Gross (Prorated)</td>
                  <td className="py-3 text-right">${parseFloat(selectedPayslip.gross_salary).toFixed(2)}</td>
                  <td className="py-3 text-right">-</td>
                </tr>
                <tr className="border-b border-gray-200">
                  <td className="py-3 font-medium">Bonus / Commission</td>
                  <td className="py-3 text-right">${parseFloat(selectedPayslip.bonus_commission).toFixed(2)}</td>
                  <td className="py-3 text-right">-</td>
                </tr>
                <tr className="border-b border-gray-200">
                  <td className="py-3 font-medium">Fines / Absences</td>
                  <td className="py-3 text-right">-</td>
                  <td className="py-3 text-right">${parseFloat(selectedPayslip.deductions_fines).toFixed(2)}</td>
                </tr>
              </tbody>
            </table>

            <div className="flex justify-end mb-12">
              <div className="w-1/2 bg-gray-100 p-4 rounded-lg">
                <div className="flex justify-between items-center text-xl font-black uppercase tracking-wider">
                  <span>Net Pay:</span>
                  <span>${parseFloat(selectedPayslip.net_salary).toFixed(2)}</span>
                </div>
              </div>
            </div>

            <div className="flex justify-between items-end border-t border-gray-300 pt-16">
              <div className="text-center">
                <div className="border-t border-black w-48 mx-auto pt-2 text-sm font-medium">Employer Signature</div>
              </div>
              <div className="text-center">
                <div className="border-t border-black w-48 mx-auto pt-2 text-sm font-medium">Employee Signature</div>
              </div>
            </div>
            
            <style dangerouslySetInnerHTML={{__html: `
              @media print {
                body * { visibility: hidden; }
                .hide-on-print { display: none !important; }
                .print\\:shadow-none { box-shadow: none !important; border: none !important; }
                .bg-white { visibility: visible; position: absolute; left: 0; top: 0; margin: 0; padding: 2cm; width: 100%; }
                .bg-white * { visibility: visible; }
              }
            `}} />
          </div>
        </div>
      )}
    </div>
  );
}
