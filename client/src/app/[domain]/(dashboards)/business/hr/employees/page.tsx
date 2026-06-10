'use client';

import React, { useState, useEffect } from 'react';
import api from '@/lib/api';
import BulkMessageModal from '@/components/BulkMessageModal';
import toast from 'react-hot-toast';

export default function EmployeeProfilePage() {
  const [employees, setEmployees] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
  const [showProfileModal, setShowProfileModal] = useState(false);
  const [selectedUser, setSelectedUser] = useState<any>(null);
  const [profileForm, setProfileForm] = useState({
    base_salary: '0', joining_date: '', designation: '', nid_number: '', emergency_contact: ''
  });
  const [submitting, setSubmitting] = useState(false);

  // Invite Modal
  const [showInviteModal, setShowInviteModal] = useState(false);
  const [inviteForm, setInviteForm] = useState({ email: '', role: 'Cashier' });
  const [inviteLoading, setInviteLoading] = useState(false);

  // Attendance
  const [showAttendanceModal, setShowAttendanceModal] = useState(false);
  const [attendances, setAttendances] = useState<any[]>([]);
  const [attLoading, setAttLoading] = useState(false);
  const [attMonth, setAttMonth] = useState(new Date().toISOString().slice(0, 7));
  const [editingAttendance, setEditingAttendance] = useState<any>(null);
  const [attForm, setAttForm] = useState({ clock_in: '', clock_out: '', status: 'Present' });

  // Bulk Messaging
  const [showBulkMessageModal, setShowBulkMessageModal] = useState(false);

  useEffect(() => {
    fetchEmployees();
  }, []);

  const fetchEmployees = async () => {
    setLoading(true);
    try {
      const res = await api.get('/hr/employees');
      if (res.data) setEmployees(res.data);
    } catch (err) {
    } finally {
      setLoading(false);
    }
  };

  const handleEditProfile = (user: any) => {
    setSelectedUser(user);
    const prof = user.employee_profile || {};
    setProfileForm({
      base_salary: prof.base_salary || '0',
      joining_date: prof.joining_date || '',
      designation: prof.designation || '',
      nid_number: prof.nid_number || '',
      emergency_contact: prof.emergency_contact || ''
    });
    setShowProfileModal(true);
  };

  const saveProfile = async (e: React.FormEvent) => {
    e.preventDefault();
    setSubmitting(true);
    try {
      await api.put(`/hr/employees/${selectedUser.id}/profile`, {
        ...profileForm,
        base_salary: parseFloat(profileForm.base_salary)
      });
      setShowProfileModal(false);
      fetchEmployees();
      alert('Profile updated successfully!');
    } catch (err: any) {
      alert(err.response?.data?.message || 'Failed to update profile');
    } finally {
      setSubmitting(false);
    }
  };

  const handleSendInvite = async (e: React.FormEvent) => {
    e.preventDefault();
    setInviteLoading(true);
    try {
      await api.post('/business/invites', inviteForm);
      toast.success('Invitation sent successfully!');
      setShowInviteModal(false);
      setInviteForm({ email: '', role: 'Cashier' });
    } catch (err: any) {
      if (err.response?.status === 422) {
        const errors = err.response.data.errors;
        const errorMessages = Object.values(errors).flat().join('\n');
        toast.error(errorMessages);
      } else {
        toast.error(err.response?.data?.message || 'Failed to send invitation');
      }
    } finally {
      setInviteLoading(false);
    }
  };

  const openAttendance = async () => {
    setShowAttendanceModal(true);
    fetchAttendance(attMonth);
  };

  const fetchAttendance = async (month: string) => {
    setAttLoading(true);
    try {
      const res = await api.get(`/hr/attendance?month=${month}`);
      if (res.data) setAttendances(res.data);
    } catch (e) {
    } finally {
      setAttLoading(false);
    }
  };

  const handleEditAttendance = (att: any) => {
    setEditingAttendance(att);
    setAttForm({
      clock_in: att.clock_in ? new Date(att.clock_in).toISOString().slice(0, 16) : '',
      clock_out: att.clock_out ? new Date(att.clock_out).toISOString().slice(0, 16) : '',
      status: att.status || 'Present'
    });
  };

  const saveAttendance = async (e: React.FormEvent) => {
    e.preventDefault();
    try {
      await api.put(`/hr/attendance/${editingAttendance.id}`, attForm);
      setEditingAttendance(null);
      fetchAttendance(attMonth);
      alert('Attendance updated successfully!');
    } catch (err: any) {
      alert(err.response?.data?.message || 'Failed to update attendance');
    }
  };

  return (
    <div className="flex flex-col h-full gap-8 animate-in fade-in duration-500 pb-12">
      <div className="flex justify-between items-center">
        <div>
          <h1 className="text-3xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-blue-400 to-indigo-500">
            Employee Profiles & HR
          </h1>
          <p className="text-text-muted mt-1">Manage staff details and view attendance records.</p>
        </div>
        <div className="flex gap-3">
          <button 
            onClick={() => setShowInviteModal(true)}
            className="bg-primary text-white border border-primary/50 hover:bg-primary/90 px-6 py-2.5 rounded-xl font-bold transition-all shadow-lg"
          >
            Invite Staff
          </button>
          <button 
            onClick={() => setShowBulkMessageModal(true)}
            className="bg-primary/20 text-primary border border-primary/30 hover:bg-primary/30 px-6 py-2.5 rounded-xl font-bold transition-all shadow-lg shadow-primary/10"
          >
            Send Bulk Message
          </button>
          <button 
            onClick={openAttendance}
            className="bg-indigo-500/20 text-indigo-400 border border-indigo-500/30 hover:bg-indigo-500/30 px-6 py-2.5 rounded-xl font-bold transition-all shadow-lg shadow-indigo-500/10">
            View Attendance Grid
          </button>
        </div>
      </div>

      <div className="glass-card rounded-xl overflow-hidden border border-border">
        <div className="w-full overflow-x-auto">
          <table className="w-full text-left text-sm">
            <thead className="bg-surface/50 border-b border-border">
              <tr>
                <th className="p-4 font-semibold text-text-muted">Staff Member</th>
                <th className="p-4 font-semibold text-text-muted">Role & Designation</th>
                <th className="p-4 font-semibold text-text-muted">Contact Info</th>
                <th className="p-4 font-semibold text-text-muted text-right">Base Salary</th>
                <th className="p-4 font-semibold text-text-muted text-center">Action</th>
              </tr>
            </thead>
            <tbody>
              {loading ? (
                <tr><td colSpan={5} className="p-8 text-center text-text-muted">Loading employees...</td></tr>
              ) : employees.length === 0 ? (
                <tr><td colSpan={5} className="p-8 text-center text-text-muted">No system users found. Go to Users section to create them.</td></tr>
              ) : employees.map(user => (
                <tr key={user.id} className="border-b border-border/50 hover:bg-white/5 transition-colors">
                  <td className="p-4">
                    <div className="font-bold text-lg">{user.first_name} {user.last_name}</div>
                    <div className="text-xs text-text-muted">Joined: {user.employee_profile?.joining_date || 'N/A'}</div>
                  </td>
                  <td className="p-4">
                    <span className="px-2 py-1 bg-surface border border-border rounded font-mono text-xs text-blue-400 mr-2">
                      {user.role}
                    </span>
                    <span className="text-text-muted">{user.employee_profile?.designation || 'No designation'}</span>
                  </td>
                  <td className="p-4 text-text-muted">
                    <div>{user.email}</div>
                    <div className="text-xs">Emergency: {user.employee_profile?.emergency_contact || 'N/A'}</div>
                  </td>
                  <td className="p-4 text-right font-bold text-indigo-400 text-lg">
                    ${parseFloat(user.employee_profile?.base_salary || '0').toFixed(2)}
                  </td>
                  <td className="p-4 text-center">
                    <button onClick={() => handleEditProfile(user)} className="text-indigo-400 hover:text-white font-medium text-sm border border-indigo-500/30 px-3 py-1 rounded-lg hover:bg-indigo-500/20 transition-all">
                      Edit Profile
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>

      {/* Profile Modal */}
      {showProfileModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm animate-in fade-in">
          <div className="bg-surface border border-border w-full max-w-lg rounded-2xl shadow-2xl p-6 relative">
            <h2 className="text-2xl font-bold text-white mb-6">Edit Profile: {selectedUser?.first_name}</h2>
            <form onSubmit={saveProfile} className="flex flex-col gap-4">
              <div className="grid grid-cols-2 gap-4">
                <div className="flex flex-col gap-1.5">
                  <label className="text-sm font-medium text-text-muted">Base Salary (Monthly)</label>
                  <input type="number" step="0.01" required value={profileForm.base_salary} onChange={e => setProfileForm({...profileForm, base_salary: e.target.value})} className="bg-background border border-border rounded-lg px-4 py-2 text-white outline-none focus:border-indigo-500/50" />
                </div>
                <div className="flex flex-col gap-1.5">
                  <label className="text-sm font-medium text-text-muted">Joining Date</label>
                  <input type="date" value={profileForm.joining_date} onChange={e => setProfileForm({...profileForm, joining_date: e.target.value})} className="bg-background border border-border rounded-lg px-4 py-2 text-white outline-none focus:border-indigo-500/50 [color-scheme:dark]" />
                </div>
              </div>
              <div className="flex flex-col gap-1.5">
                <label className="text-sm font-medium text-text-muted">Designation</label>
                <input value={profileForm.designation} onChange={e => setProfileForm({...profileForm, designation: e.target.value})} className="bg-background border border-border rounded-lg px-4 py-2 text-white outline-none focus:border-indigo-500/50" placeholder="e.g. Senior Cashier" />
              </div>
              <div className="flex flex-col gap-1.5">
                <label className="text-sm font-medium text-text-muted">National ID Number</label>
                <input value={profileForm.nid_number} onChange={e => setProfileForm({...profileForm, nid_number: e.target.value})} className="bg-background border border-border rounded-lg px-4 py-2 text-white outline-none focus:border-indigo-500/50" />
              </div>
              <div className="flex flex-col gap-1.5">
                <label className="text-sm font-medium text-text-muted">Emergency Contact</label>
                <input value={profileForm.emergency_contact} onChange={e => setProfileForm({...profileForm, emergency_contact: e.target.value})} className="bg-background border border-border rounded-lg px-4 py-2 text-white outline-none focus:border-indigo-500/50" placeholder="Name & Phone" />
              </div>
              <div className="flex justify-end gap-3 mt-4">
                <button type="button" onClick={() => setShowProfileModal(false)} className="px-5 py-2 rounded-lg text-text-muted hover:text-white font-medium">Cancel</button>
                <button type="submit" disabled={submitting} className="bg-indigo-500 hover:bg-indigo-600 text-white px-6 py-2 rounded-lg font-bold transition-all disabled:opacity-50">
                  {submitting ? 'Saving...' : 'Save Profile'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Invite Modal */}
      {showInviteModal && (
        <div className="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-black/80 backdrop-blur-md animate-in fade-in">
          <div className="bg-surface border border-border w-full max-w-md rounded-2xl shadow-2xl p-6 relative">
            <h2 className="text-2xl font-bold text-white mb-2">Invite New Staff</h2>
            <p className="text-text-muted text-sm mb-6">Send an email invitation to join your business.</p>
            <form onSubmit={handleSendInvite} className="flex flex-col gap-4">
              <div className="flex flex-col gap-1.5">
                <label className="text-sm font-medium text-text-muted">Email Address</label>
                <input required type="email" value={inviteForm.email} onChange={e => setInviteForm({...inviteForm, email: e.target.value})} className="bg-background border border-border rounded-lg px-4 py-2.5 text-white outline-none focus:border-primary/50" placeholder="staff@example.com" />
              </div>
              <div className="flex flex-col gap-1.5">
                <label className="text-sm font-medium text-text-muted">Role</label>
                <select value={inviteForm.role} onChange={e => setInviteForm({...inviteForm, role: e.target.value})} className="bg-background border border-border rounded-lg px-4 py-2.5 text-white outline-none focus:border-primary/50">
                  <option value="Cashier">Cashier</option>
                  <option value="InventoryManager">Inventory Manager</option>
                  <option value="Accountant">Accountant</option>
                  <option value="BusinessAdmin">Business Admin</option>
                </select>
              </div>
              <div className="flex justify-end gap-3 mt-4">
                <button type="button" onClick={() => setShowInviteModal(false)} className="px-5 py-2.5 rounded-lg text-text-muted hover:text-white font-medium">Cancel</button>
                <button type="submit" disabled={inviteLoading} className="bg-primary hover:bg-primary/90 text-white px-6 py-2.5 rounded-lg font-bold transition-all disabled:opacity-50">
                  {inviteLoading ? 'Sending...' : 'Send Invitation'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Attendance Modal */}
      {showAttendanceModal && (
        <div className="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-black/80 backdrop-blur-md animate-in fade-in">
          <div className="bg-surface border border-border w-full max-w-4xl max-h-[90vh] flex flex-col rounded-2xl shadow-2xl overflow-hidden relative">
            <div className="p-6 border-b border-border flex justify-between items-center bg-background/50">
              <h2 className="text-2xl font-bold text-white">Daily Attendance Grid</h2>
              <div className="flex items-center gap-4">
                <input type="month" value={attMonth} onChange={e => { setAttMonth(e.target.value); fetchAttendance(e.target.value); }} className="bg-surface border border-border rounded-lg px-3 py-1.5 text-sm text-white outline-none [color-scheme:dark]" />
                <button onClick={() => setShowAttendanceModal(false)} className="text-text-muted hover:text-white text-2xl leading-none">&times;</button>
              </div>
            </div>
            <div className="flex-1 overflow-y-auto p-6">
              {attLoading ? (
                <div className="text-center py-12 text-text-muted">Loading attendance...</div>
              ) : attendances.length === 0 ? (
                <div className="text-center py-12 text-text-muted bg-surface/30 rounded-xl border border-dashed border-border">No attendance records for {attMonth}</div>
              ) : (
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                  {attendances.map(att => (
                    <div key={att.id} className="bg-background border border-border p-4 rounded-xl flex flex-col gap-2 relative overflow-hidden">
                      <div className={`absolute top-0 left-0 w-1 h-full ${att.status === 'Present' ? 'bg-emerald-500' : 'bg-rose-500'}`}></div>
                      <div className="flex justify-between items-start">
                        <span className="font-bold text-white">{att.user?.first_name} {att.user?.last_name}</span>
                        <div className="flex items-center gap-2">
                          <span className="text-xs font-mono text-text-muted bg-surface px-1.5 py-0.5 rounded">{att.date}</span>
                          <button onClick={() => handleEditAttendance(att)} className="text-xs text-indigo-400 hover:text-indigo-300 ml-1">Edit</button>
                        </div>
                      </div>
                      <div className="flex justify-between text-sm mt-2">
                        <div className="flex flex-col">
                          <span className="text-text-muted text-xs">IN</span>
                          <span className="font-mono text-emerald-400">{att.clock_in ? new Date(att.clock_in).toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'}) : '--:--'}</span>
                        </div>
                        <div className="flex flex-col text-right">
                          <span className="text-text-muted text-xs">OUT</span>
                          <span className="font-mono text-rose-400">{att.clock_out ? new Date(att.clock_out).toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'}) : '--:--'}</span>
                        </div>
                      </div>
                    </div>
                  ))}
                </div>
              )}
            </div>
          </div>
          
          {/* Edit Attendance Modal */}
          {editingAttendance && (
            <div className="fixed inset-0 z-[70] flex items-center justify-center p-4 bg-black/80 backdrop-blur-md animate-in fade-in">
              <div className="bg-surface border border-border w-full max-w-sm rounded-2xl shadow-2xl p-6 relative">
                <h3 className="text-xl font-bold text-white mb-4">Edit Attendance</h3>
                <form onSubmit={saveAttendance} className="flex flex-col gap-4">
                  <div className="flex flex-col gap-1.5">
                    <label className="text-sm font-medium text-text-muted">Clock In</label>
                    <input type="datetime-local" value={attForm.clock_in} onChange={e => setAttForm({...attForm, clock_in: e.target.value})} className="bg-background border border-border rounded-lg px-4 py-2 text-white outline-none focus:border-indigo-500/50 [color-scheme:dark]" />
                  </div>
                  <div className="flex flex-col gap-1.5">
                    <label className="text-sm font-medium text-text-muted">Clock Out</label>
                    <input type="datetime-local" value={attForm.clock_out} onChange={e => setAttForm({...attForm, clock_out: e.target.value})} className="bg-background border border-border rounded-lg px-4 py-2 text-white outline-none focus:border-indigo-500/50 [color-scheme:dark]" />
                  </div>
                  <div className="flex flex-col gap-1.5">
                    <label className="text-sm font-medium text-text-muted">Status</label>
                    <select value={attForm.status} onChange={e => setAttForm({...attForm, status: e.target.value})} className="bg-background border border-border rounded-lg px-4 py-2 text-white outline-none focus:border-indigo-500/50">
                      <option value="Present">Present</option>
                      <option value="Absent">Absent</option>
                      <option value="Late">Late</option>
                      <option value="Half-Day">Half-Day</option>
                    </select>
                  </div>
                  <div className="flex justify-end gap-3 mt-4">
                    <button type="button" onClick={() => setEditingAttendance(null)} className="px-5 py-2 rounded-lg text-text-muted hover:text-white font-medium">Cancel</button>
                    <button type="submit" className="bg-indigo-500 hover:bg-indigo-600 text-white px-6 py-2 rounded-lg font-bold transition-all">Save</button>
                  </div>
                </form>
              </div>
            </div>
          )}
        </div>
      )}

      {/* Bulk Message Modal */}
      <BulkMessageModal 
        isOpen={showBulkMessageModal}
        onClose={() => setShowBulkMessageModal(false)}
        users={employees}
      />
    </div>
  );
}
