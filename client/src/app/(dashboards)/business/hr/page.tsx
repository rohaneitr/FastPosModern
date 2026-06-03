'use client';

import React, { useState, useEffect } from 'react';
import api from '@/lib/api';

export default function HRPage() {
  const [activeTab, setActiveTab] = useState<'employees' | 'payroll'>('employees');
  const [employees, setEmployees] = useState<any[]>([]);
  const [payrolls, setPayrolls] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);

  // Modal States
  const [showEmployeeModal, setShowEmployeeModal] = useState(false);
  const [showPayrollModal, setShowPayrollModal] = useState(false);
  const [submitting, setSubmitting] = useState(false);

  // Form States - Employee
  const [empForm, setEmpForm] = useState({
    employee_id: '', first_name: '', last_name: '', email: '', phone: '',
    department: '', designation: '', basic_salary: '0', joining_date: '', is_active: true
  });

  // Form States - Payroll
  const [payForm, setPayForm] = useState({
    employee_id: '', reference_no: '', month: '', total_amount: '0', payment_status: 'due'
  });

  useEffect(() => {
    fetchData();
  }, [activeTab]);

  const fetchData = async () => {
    setLoading(true);
    try {
      if (activeTab === 'employees') {
        const res = await api.get('/hr/employees');
        if (res.data && res.data.data) setEmployees(res.data.data);
      } else {
        const res = await api.get('/hr/payrolls');
        if (res.data && res.data.data) setPayrolls(res.data.data);
      }
    } catch (err) {
      console.warn(`Failed to fetch ${activeTab}`, err);
      // Fallback Demo Data
      if (activeTab === 'employees') {
        setEmployees([
          { id: 1, employee_id: 'EMP-001', first_name: 'Sarah', last_name: 'Connor', department: 'Sales', designation: 'Manager', is_active: 1 },
          { id: 2, employee_id: 'EMP-002', first_name: 'Kyle', last_name: 'Reese', department: 'Inventory', designation: 'Stock Clerk', is_active: 1 },
          { id: 3, employee_id: 'EMP-003', first_name: 'John', last_name: 'Connor', department: 'IT', designation: 'SysAdmin', is_active: 0 },
        ]);
      } else {
        setPayrolls([
          { id: 1, emp_code: 'EMP-001', first_name: 'Sarah', last_name: 'Connor', reference_no: 'PAY-2605-01', month: '2026-05', total_amount: '4500.00', payment_status: 'paid' },
          { id: 2, emp_code: 'EMP-002', first_name: 'Kyle', last_name: 'Reese', reference_no: 'PAY-2605-02', month: '2026-05', total_amount: '3200.00', payment_status: 'due' },
        ]);
      }
    } finally {
      setLoading(false);
    }
  };

  const handleSaveEmployee = async (e: React.FormEvent) => {
    e.preventDefault();
    setSubmitting(true);
    try {
      await api.post('/hr/employees', {
        ...empForm,
        basic_salary: parseFloat(empForm.basic_salary) || 0
      });
      setShowEmployeeModal(false);
      fetchData();
      setEmpForm({
        employee_id: '', first_name: '', last_name: '', email: '', phone: '',
        department: '', designation: '', basic_salary: '0', joining_date: '', is_active: true
      });
    } catch (err) {
      console.error(err);
      alert('Failed to save employee.');
    } finally {
      setSubmitting(false);
    }
  };

  const handleSavePayroll = async (e: React.FormEvent) => {
    e.preventDefault();
    setSubmitting(true);
    try {
      await api.post('/hr/payrolls', {
        ...payForm,
        total_amount: parseFloat(payForm.total_amount) || 0
      });
      setShowPayrollModal(false);
      fetchData();
      setPayForm({
        employee_id: '', reference_no: '', month: '', total_amount: '0', payment_status: 'due'
      });
    } catch (err) {
      console.error(err);
      alert('Failed to run payroll.');
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <div className="flex flex-col h-full gap-8 animate-in fade-in duration-500 pb-12">
      <div className="flex justify-between items-center">
        <div>
          <h1 className="text-3xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-orange-400 to-rose-500">
            HR & Payroll
          </h1>
          <p className="text-text-muted mt-1">Manage employees and monthly payrolls.</p>
        </div>
        <button 
          onClick={() => activeTab === 'employees' ? setShowEmployeeModal(true) : setShowPayrollModal(true)}
          className="bg-gradient-to-r from-orange-500 to-rose-600 hover:from-orange-600 hover:to-rose-700 text-white px-6 py-2.5 rounded-xl shadow-lg shadow-orange-500/25 font-bold transition-all active:scale-[0.98]">
          {activeTab === 'employees' ? '+ Add Employee' : '+ Run Payroll'}
        </button>
      </div>

      {/* Tabs */}
      <div className="glass-card rounded-xl p-2 inline-flex self-start gap-2">
        <button
          onClick={() => setActiveTab('employees')}
          className={`px-4 py-2 rounded-lg text-sm font-medium transition-all ${activeTab === 'employees' ? 'bg-orange-500 text-white shadow-md' : 'text-text-muted hover:text-white hover:bg-white/5'}`}
        >
          Employees
        </button>
        <button
          onClick={() => setActiveTab('payroll')}
          className={`px-4 py-2 rounded-lg text-sm font-medium transition-all ${activeTab === 'payroll' ? 'bg-orange-500 text-white shadow-md' : 'text-text-muted hover:text-white hover:bg-white/5'}`}
        >
          Payroll
        </button>
      </div>

      {/* Content Table */}
      <div className="glass-card rounded-xl overflow-hidden border border-border">
        <table className="w-full text-left text-sm">
          <thead className="bg-surface/50 border-b border-border">
            {activeTab === 'employees' ? (
              <tr>
                <th className="p-4 font-semibold text-text-muted">ID</th>
                <th className="p-4 font-semibold text-text-muted">Name</th>
                <th className="p-4 font-semibold text-text-muted">Department</th>
                <th className="p-4 font-semibold text-text-muted">Designation</th>
                <th className="p-4 font-semibold text-text-muted text-center">Status</th>
              </tr>
            ) : (
              <tr>
                <th className="p-4 font-semibold text-text-muted">Month</th>
                <th className="p-4 font-semibold text-text-muted">Employee</th>
                <th className="p-4 font-semibold text-text-muted">Reference No.</th>
                <th className="p-4 font-semibold text-text-muted text-right">Amount</th>
                <th className="p-4 font-semibold text-text-muted text-center">Status</th>
              </tr>
            )}
          </thead>
          <tbody>
            {loading ? (
              <tr><td colSpan={5} className="p-8 text-center text-text-muted">Loading {activeTab}...</td></tr>
            ) : activeTab === 'employees' ? (
              employees.length === 0 ? (
                <tr><td colSpan={5} className="p-8 text-center text-text-muted">No employees found.</td></tr>
              ) : (
                employees.map(emp => (
                  <tr key={emp.id} className="border-b border-border/50 hover:bg-white/5 transition-colors">
                    <td className="p-4 text-text-muted font-medium">{emp.employee_id}</td>
                    <td className="p-4 font-semibold text-lg">{emp.first_name} {emp.last_name}</td>
                    <td className="p-4 text-primary">{emp.department || 'N/A'}</td>
                    <td className="p-4">{emp.designation || 'N/A'}</td>
                    <td className="p-4 text-center">
                      <span className={`px-2 py-1 rounded-full text-xs font-bold uppercase ${emp.is_active ? 'bg-success/20 text-success' : 'bg-danger/20 text-danger'}`}>
                        {emp.is_active ? 'Active' : 'Inactive'}
                      </span>
                    </td>
                  </tr>
                ))
              )
            ) : (
              payrolls.length === 0 ? (
                <tr><td colSpan={5} className="p-8 text-center text-text-muted">No payrolls found.</td></tr>
              ) : (
                payrolls.map(pay => (
                  <tr key={pay.id} className="border-b border-border/50 hover:bg-white/5 transition-colors">
                    <td className="p-4 font-bold text-orange-400">{pay.month}</td>
                    <td className="p-4 font-medium">{pay.first_name} {pay.last_name} <span className="text-text-muted text-xs ml-2">({pay.emp_code})</span></td>
                    <td className="p-4 text-text-muted">{pay.reference_no}</td>
                    <td className="p-4 text-right font-bold text-lg">${parseFloat(pay.total_amount).toFixed(2)}</td>
                    <td className="p-4 text-center">
                      <span className={`px-2 py-1 rounded-full text-xs font-bold uppercase ${pay.payment_status === 'paid' ? 'bg-success/20 text-success' : 'bg-warning/20 text-warning'}`}>
                        {pay.payment_status}
                      </span>
                    </td>
                  </tr>
                ))
              )
            )}
          </tbody>
        </table>
      </div>

      {/* Employee Modal */}
      {showEmployeeModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm animate-in fade-in duration-200">
          <div className="bg-surface border border-border w-full max-w-2xl rounded-2xl shadow-2xl p-6 relative">
            <button onClick={() => setShowEmployeeModal(false)} className="absolute top-4 right-4 text-text-muted hover:text-white">
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
            <h2 className="text-2xl font-bold text-white mb-6">Add New Employee</h2>
            
            <form onSubmit={handleSaveEmployee} className="flex flex-col gap-5">
              <div className="grid grid-cols-2 gap-4">
                <div className="flex flex-col gap-1.5">
                  <label className="text-sm font-medium text-text-muted">First Name *</label>
                  <input required value={empForm.first_name} onChange={e => setEmpForm({...empForm, first_name: e.target.value})} className="bg-background border border-border rounded-lg px-4 py-2.5 text-white outline-none focus:border-orange-500/50" />
                </div>
                <div className="flex flex-col gap-1.5">
                  <label className="text-sm font-medium text-text-muted">Last Name *</label>
                  <input required value={empForm.last_name} onChange={e => setEmpForm({...empForm, last_name: e.target.value})} className="bg-background border border-border rounded-lg px-4 py-2.5 text-white outline-none focus:border-orange-500/50" />
                </div>
                <div className="flex flex-col gap-1.5">
                  <label className="text-sm font-medium text-text-muted">Employee ID</label>
                  <input value={empForm.employee_id} onChange={e => setEmpForm({...empForm, employee_id: e.target.value})} className="bg-background border border-border rounded-lg px-4 py-2.5 text-white outline-none focus:border-orange-500/50 placeholder:text-white/20" placeholder="e.g. EMP-001" />
                </div>
                <div className="flex flex-col gap-1.5">
                  <label className="text-sm font-medium text-text-muted">Email</label>
                  <input type="email" value={empForm.email} onChange={e => setEmpForm({...empForm, email: e.target.value})} className="bg-background border border-border rounded-lg px-4 py-2.5 text-white outline-none focus:border-orange-500/50" />
                </div>
                <div className="flex flex-col gap-1.5">
                  <label className="text-sm font-medium text-text-muted">Department</label>
                  <input value={empForm.department} onChange={e => setEmpForm({...empForm, department: e.target.value})} className="bg-background border border-border rounded-lg px-4 py-2.5 text-white outline-none focus:border-orange-500/50" />
                </div>
                <div className="flex flex-col gap-1.5">
                  <label className="text-sm font-medium text-text-muted">Designation</label>
                  <input value={empForm.designation} onChange={e => setEmpForm({...empForm, designation: e.target.value})} className="bg-background border border-border rounded-lg px-4 py-2.5 text-white outline-none focus:border-orange-500/50" />
                </div>
                <div className="flex flex-col gap-1.5">
                  <label className="text-sm font-medium text-text-muted">Basic Salary *</label>
                  <input type="number" step="0.01" required value={empForm.basic_salary} onChange={e => setEmpForm({...empForm, basic_salary: e.target.value})} className="bg-background border border-border rounded-lg px-4 py-2.5 text-white outline-none focus:border-orange-500/50" />
                </div>
                <div className="flex flex-col gap-1.5">
                  <label className="text-sm font-medium text-text-muted">Joining Date</label>
                  <input type="date" value={empForm.joining_date} onChange={e => setEmpForm({...empForm, joining_date: e.target.value})} className="bg-background border border-border rounded-lg px-4 py-2.5 text-white outline-none focus:border-orange-500/50 [color-scheme:dark]" />
                </div>
              </div>
              <div className="flex justify-end gap-3 mt-4">
                <button type="button" onClick={() => setShowEmployeeModal(false)} className="px-5 py-2.5 rounded-lg text-text-muted hover:text-white font-medium transition-colors">Cancel</button>
                <button type="submit" disabled={submitting} className="bg-orange-500 hover:bg-orange-600 text-white px-6 py-2.5 rounded-lg font-bold shadow-lg transition-all disabled:opacity-50">
                  {submitting ? 'Saving...' : 'Save Employee'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Payroll Modal */}
      {showPayrollModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm animate-in fade-in duration-200">
          <div className="bg-surface border border-border w-full max-w-md rounded-2xl shadow-2xl p-6 relative">
            <button onClick={() => setShowPayrollModal(false)} className="absolute top-4 right-4 text-text-muted hover:text-white">
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
            <h2 className="text-2xl font-bold text-white mb-6">Run Payroll</h2>
            
            <form onSubmit={handleSavePayroll} className="flex flex-col gap-5">
              <div className="flex flex-col gap-1.5">
                <label className="text-sm font-medium text-text-muted">Employee *</label>
                <select required value={payForm.employee_id} onChange={e => setPayForm({...payForm, employee_id: e.target.value})} className="bg-background border border-border rounded-lg px-4 py-2.5 text-white outline-none focus:border-orange-500/50">
                  <option value="">Select Employee</option>
                  {employees.map(emp => (
                    <option key={emp.id} value={emp.id}>{emp.first_name} {emp.last_name} ({emp.employee_id})</option>
                  ))}
                </select>
              </div>
              <div className="grid grid-cols-2 gap-4">
                <div className="flex flex-col gap-1.5">
                  <label className="text-sm font-medium text-text-muted">Month *</label>
                  <input type="month" required value={payForm.month} onChange={e => setPayForm({...payForm, month: e.target.value})} className="bg-background border border-border rounded-lg px-4 py-2.5 text-white outline-none focus:border-orange-500/50 [color-scheme:dark]" />
                </div>
                <div className="flex flex-col gap-1.5">
                  <label className="text-sm font-medium text-text-muted">Total Amount *</label>
                  <input type="number" step="0.01" required value={payForm.total_amount} onChange={e => setPayForm({...payForm, total_amount: e.target.value})} className="bg-background border border-border rounded-lg px-4 py-2.5 text-white outline-none focus:border-orange-500/50" />
                </div>
              </div>
              <div className="flex flex-col gap-1.5">
                <label className="text-sm font-medium text-text-muted">Reference No. (Optional)</label>
                <input value={payForm.reference_no} onChange={e => setPayForm({...payForm, reference_no: e.target.value})} className="bg-background border border-border rounded-lg px-4 py-2.5 text-white outline-none focus:border-orange-500/50" />
              </div>
              <div className="flex flex-col gap-1.5">
                <label className="text-sm font-medium text-text-muted">Payment Status</label>
                <select value={payForm.payment_status} onChange={e => setPayForm({...payForm, payment_status: e.target.value})} className="bg-background border border-border rounded-lg px-4 py-2.5 text-white outline-none focus:border-orange-500/50">
                  <option value="due">Due</option>
                  <option value="paid">Paid</option>
                </select>
              </div>
              <div className="flex justify-end gap-3 mt-4">
                <button type="button" onClick={() => setShowPayrollModal(false)} className="px-5 py-2.5 rounded-lg text-text-muted hover:text-white font-medium transition-colors">Cancel</button>
                <button type="submit" disabled={submitting} className="bg-orange-500 hover:bg-orange-600 text-white px-6 py-2.5 rounded-lg font-bold shadow-lg transition-all disabled:opacity-50">
                  {submitting ? 'Running...' : 'Run Payroll'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
