'use client';

import React, { useState, useEffect } from 'react';
import api from '@/lib/api';

export default function UserManagementPage() {
  const [activeTab, setActiveTab] = useState('users');
  const [loading, setLoading] = useState(true);
  
  // Data State
  const [users, setUsers] = useState<any[]>([]);
  const [roles, setRoles] = useState<any[]>([]);
  const [permissions, setPermissions] = useState<any[]>([]);
  
  // Role Edit State
  const [selectedRole, setSelectedRole] = useState<any>(null);
  const [isEditingRole, setIsEditingRole] = useState(false);
  const [roleName, setRoleName] = useState('');
  const [selectedPermissions, setSelectedPermissions] = useState<string[]>([]);

  // User Edit State
  const [isEditingUser, setIsEditingUser] = useState(false);
  const [selectedUser, setSelectedUser] = useState<any>(null);
  const [userFormData, setUserFormData] = useState({ first_name: '', last_name: '', email: '', phone: '', address: '', role: 'Cashier' });
  const [isSubmittingUser, setIsSubmittingUser] = useState(false);
  const [generatedPassword, setGeneratedPassword] = useState<string | null>(null);

  useEffect(() => {
    fetchData();
  }, [activeTab]);

  const fetchData = async () => {
    setLoading(true);
    try {
      if (activeTab === 'users') {
        // Fetch System Users
        const res = await api.get('/users');
        if (res.data) setUsers(res.data);
      } else if (activeTab === 'roles') {
        const [rolesRes, permRes] = await Promise.all([
          api.get('/roles'),
          api.get('/permissions')
        ]);
        setRoles(rolesRes.data);
        setPermissions(permRes.data);
      }
    } catch (err) {
      if (activeTab === 'users') setUsers([]);
      else {
        setRoles([]);
        setPermissions([]);
      }
    } finally {
      setLoading(false);
    }
  };

  const handleAddRoleClick = () => {
    setSelectedRole(null);
    setRoleName('');
    setSelectedPermissions([]);
    setIsEditingRole(true);
    setActiveTab('roles');
  };

  const handleAddUserClick = () => {
    setSelectedUser(null);
    setUserFormData({ first_name: '', last_name: '', email: '', phone: '', address: '', role: 'Cashier' });
    setIsEditingUser(true);
  };

  const handleEditUserClick = (user: any) => {
    setSelectedUser(user);
    setUserFormData({ 
      first_name: user.first_name, 
      last_name: user.last_name, 
      email: user.email, 
      phone: user.phone || user.settings?.phone || '', 
      address: user.address || user.settings?.address || '', 
      role: user.role || 'Cashier' 
    });
    setIsEditingUser(true);
  };

  const handleSaveUser = async () => {
    setIsSubmittingUser(true);
    try {
      if (selectedUser) {
        await api.put(`/users/${selectedUser.id}`, userFormData);
        setIsEditingUser(false);
      } else {
        const res = await api.post('/users', userFormData);
        if (res.data?.generated_password) {
          setGeneratedPassword(res.data.generated_password);
        } else {
          setIsEditingUser(false);
        }
      }
      fetchData();
    } catch (e: any) {
      alert(`User action failed: ${e.response?.data?.message || e.message}`);
    } finally {
      setIsSubmittingUser(false);
    }
  };

  const handleRoleSelect = (role: any) => {
    setSelectedRole(role);
    setRoleName(role.name.split('#')[0]);
    setSelectedPermissions(role.permissions?.map((p: any) => p.name) || []);
    setIsEditingRole(true);
  };

  const togglePermission = (permName: string) => {
    setSelectedPermissions(prev => 
      prev.includes(permName) ? prev.filter(p => p !== permName) : [...prev, permName]
    );
  };

  const saveRole = async () => {
    try {
      const payload = { name: roleName, permissions: selectedPermissions };
      if (selectedRole) {
        await api.put(`/roles/${selectedRole.id}`, payload);
        alert('Role updated successfully!');
      } else {
        await api.post('/roles', payload);
        alert('Role created successfully!');
      }
      setIsEditingRole(false);
      fetchData(); // Refresh list
    } catch (e: any) {
      alert(`Action failed: ${e.response?.data?.message || e.message}`);
      setIsEditingRole(false);
    }
  };

  const tabs = [
    { id: 'users', label: 'System Users' },
    { id: 'roles', label: 'Roles & Permissions' },
  ];

  return (
    <div className="flex flex-col h-full gap-8 animate-in fade-in duration-500 pb-12">
      <div className="flex justify-between items-center">
        <div>
          <h1 className="text-3xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-yellow-400 to-orange-500">
            User Management
          </h1>
          <p className="text-text-muted mt-1">Manage system access, roles, and granular permissions.</p>
        </div>
        <button 
          onClick={activeTab === 'users' ? handleAddUserClick : handleAddRoleClick}
          className="bg-yellow-500 hover:bg-yellow-600 text-black px-6 py-2 rounded-lg shadow-lg font-bold transition-colors"
        >
          {activeTab === 'users' ? '+ Add User' : '+ Add Role'}
        </button>
      </div>

      {/* Tabs */}
      <div className="glass-card rounded-xl p-2 inline-flex self-start gap-2">
        {tabs.map(tab => (
          <button
            key={tab.id}
            onClick={() => setActiveTab(tab.id)}
            className={`px-4 py-2 rounded-lg text-sm font-medium transition-all ${
              activeTab === tab.id 
                ? 'bg-yellow-500 text-black shadow-md' 
                : 'text-text-muted hover:text-white hover:bg-white/5'
            }`}
          >
            {tab.label}
          </button>
        ))}
      </div>

      <div className="glass-card rounded-xl border border-border min-h-[400px]">
        
        {activeTab === 'users' && (
          <div className="overflow-x-auto p-2">
            <div className="w-full overflow-x-auto">
<table className="w-full text-left text-sm">
              <thead className="bg-surface/50 border-b border-border">
                <tr>
                  <th className="p-4 font-semibold text-text-muted">Name</th>
                  <th className="p-4 font-semibold text-text-muted">Email</th>
                  <th className="p-4 font-semibold text-text-muted">Phone</th>
                  <th className="p-4 font-semibold text-text-muted">Assigned Role</th>
                  <th className="p-4 font-semibold text-text-muted text-center">Status</th>
                  <th className="p-4 font-semibold text-text-muted text-center">Action</th>
                </tr>
              </thead>
              <tbody>
                {loading ? (
                  <tr><td colSpan={5} className="p-8 text-center text-text-muted">Loading users...</td></tr>
                ) : users.map((u, i) => (
                  <tr key={i} className="border-b border-border/50 hover:bg-white/5 transition-colors">
                    <td className="p-4 font-bold">
                      {u.first_name} {u.last_name}
                      <div className="text-xs font-normal text-text-muted mt-0.5">{u.address}</div>
                    </td>
                    <td className="p-4 text-text-muted">{u.email}</td>
                    <td className="p-4 text-text-muted">{u.phone || 'N/A'}</td>
                    <td className="p-4">
                      <span className="px-2 py-1 rounded text-xs bg-surface border border-border font-mono text-yellow-400">
                        {u.role || 'Cashier'}
                      </span>
                    </td>
                    <td className="p-4 text-center">
                      <span className={`px-2 py-1 rounded-full text-xs font-bold uppercase ${u.is_active ? 'bg-success/20 text-success' : 'bg-danger/20 text-danger'}`}>
                        {u.is_active ? 'Active' : 'Suspended'}
                      </span>
                    </td>
                    <td className="p-4 text-center">
                      <button onClick={() => handleEditUserClick(u)} className="text-primary hover:text-blue-400 font-medium">Edit</button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
</div>
          </div>
        )}

        {activeTab === 'roles' && (
          <div className="p-6">
            <div className="grid md:grid-cols-3 gap-6">
              
              {/* Roles List */}
              <div className="md:col-span-1 border-r border-border pr-6">
                <h2 className="text-xl font-bold mb-4">Roles</h2>
                <div className="flex flex-col gap-2">
                  {roles.map(r => (
                    <div 
                      key={r.id} 
                      onClick={() => handleRoleSelect(r)}
                      className={`p-3 rounded-lg border cursor-pointer transition-colors flex justify-between items-center ${
                        selectedRole?.id === r.id ? 'border-yellow-500 bg-yellow-500/10' : 'border-border bg-surface/50 hover:bg-surface'
                      }`}
                    >
                      <span className="font-medium text-yellow-400">{r.name.split('#')[0]}</span>
                      <span className="text-xs text-text-muted">Edit ➔</span>
                    </div>
                  ))}
                </div>
              </div>

              {/* Permissions Matrix */}
              <div className="md:col-span-2">
                <h2 className="text-xl font-bold mb-4">
                  {isEditingRole ? (selectedRole ? `Edit Role: ${roleName}` : 'Create New Role') : 'Permissions Matrix'}
                </h2>
                <p className="text-sm text-text-muted mb-4">Select a role on the left to assign granular permissions across the application domains.</p>
                
                {isEditingRole && (
                  <div className="mb-6">
                    <label className="block text-sm text-text-muted mb-1">Role Name</label>
                    <input 
                      value={roleName}
                      onChange={(e) => setRoleName(e.target.value)}
                      className="w-full max-w-md bg-background/50 border border-border rounded-lg p-2" 
                      placeholder="e.g. Area Manager" 
                    />
                  </div>
                )}

                <div className="grid grid-cols-1 md:grid-cols-2 gap-4 opacity-100 transition-opacity" style={{ opacity: isEditingRole ? 1 : 0.5, pointerEvents: isEditingRole ? 'auto' : 'none' }}>
                  {Array.from(new Set(permissions.map(p => p.group || 'General'))).map(group => (
                    <div key={group as string} className="bg-surface/30 border border-border rounded-lg p-4">
                      <h3 className="font-bold border-b border-border pb-2 mb-3 text-primary">{group} Management</h3>
                      <div className="flex flex-col gap-2">
                        {permissions.filter(p => p.group === group).length > 0 ? (
                          permissions.filter(p => p.group === group).map(p => (
                            <label key={p.id} className="flex items-center gap-2 text-sm cursor-pointer hover:text-white text-text-muted transition-colors">
                              <input 
                                type="checkbox" 
                                checked={selectedPermissions.includes(p.name)}
                                onChange={() => togglePermission(p.name)}
                                className="rounded bg-background border-border text-yellow-500 focus:ring-yellow-500" 
                              />
                              {p.name}
                            </label>
                          ))
                        ) : (
                          <div className="text-xs text-text-muted italic">No permissions loaded.</div>
                        )}
                      </div>
                    </div>
                  ))}
                </div>
                
                {isEditingRole && (
                  <div className="mt-6 flex gap-3">
                    <button onClick={saveRole} className="bg-yellow-500 hover:bg-yellow-600 text-black px-6 py-2 rounded-lg font-bold">
                      {selectedRole ? 'Update Role' : 'Save New Role'}
                    </button>
                    <button onClick={() => setIsEditingRole(false)} className="bg-surface border border-border px-6 py-2 rounded-lg font-medium text-white hover:bg-white/10">
                      Cancel
                    </button>
                  </div>
                )}
              </div>

            </div>
          </div>
        )}

      </div>

      {/* User Add/Edit Modal */}
      {isEditingUser && (
        <div className="fixed inset-0 bg-black/60 flex items-center justify-center z-50 animate-in fade-in">
          <div className="bg-surface border border-border p-6 rounded-2xl w-full max-w-md shadow-2xl">
            <h2 className="text-2xl font-bold mb-4">{selectedUser ? 'Edit User' : 'Add New User'}</h2>
            <div className="flex flex-col gap-4">
              <div>
                <label className="block text-sm text-text-muted mb-1">First Name</label>
                <input value={userFormData.first_name} onChange={e => setUserFormData({...userFormData, first_name: e.target.value})} className="w-full bg-background/50 border border-border rounded-lg p-2" />
              </div>
              <div>
                <label className="block text-sm text-text-muted mb-1">Last Name</label>
                <input value={userFormData.last_name} onChange={e => setUserFormData({...userFormData, last_name: e.target.value})} className="w-full bg-background/50 border border-border rounded-lg p-2" />
              </div>
              <div>
                <label className="block text-sm text-text-muted mb-1">Email</label>
                <input value={userFormData.email} onChange={e => setUserFormData({...userFormData, email: e.target.value})} className="w-full bg-background/50 border border-border rounded-lg p-2" />
              </div>
              <div>
                <label className="block text-sm text-text-muted mb-1">Phone Number</label>
                <input value={userFormData.phone} onChange={e => setUserFormData({...userFormData, phone: e.target.value})} className="w-full bg-background/50 border border-border rounded-lg p-2" placeholder="e.g. +1 555-0100" />
              </div>
              <div>
                <label className="block text-sm text-text-muted mb-1">Physical Address</label>
                <textarea value={userFormData.address} onChange={e => setUserFormData({...userFormData, address: e.target.value})} className="w-full bg-background/50 border border-border rounded-lg p-2 h-20 resize-none" placeholder="Enter full address..."></textarea>
              </div>
              <div>
                <label className="block text-sm text-text-muted mb-1">Role</label>
                <select value={userFormData.role} onChange={e => setUserFormData({...userFormData, role: e.target.value})} className="w-full bg-background/50 border border-border rounded-lg p-2">
                  <option>Admin</option>
                  <option>Cashier</option>
                  <option>Inventory Manager</option>
                </select>
              </div>
              <div className="mt-4 flex gap-3">
                <button 
                  onClick={handleSaveUser} 
                  disabled={isSubmittingUser}
                  className="flex-1 bg-yellow-500 hover:bg-yellow-600 text-black py-2 rounded-lg font-bold transition-colors disabled:opacity-50"
                >
                  {isSubmittingUser ? 'Saving...' : (selectedUser ? 'Update User' : 'Save New User')}
                </button>
                <button onClick={() => setIsEditingUser(false)} className="flex-1 bg-surface border border-border py-2 rounded-lg font-medium text-white hover:bg-white/10 transition-colors">
                  Cancel
                </button>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Generated Password Modal */}
      {generatedPassword && (
        <div className="fixed inset-0 bg-black/80 flex items-center justify-center z-[60] animate-in fade-in">
          <div className="bg-surface border border-emerald-500/30 p-8 rounded-2xl w-full max-w-md shadow-2xl text-center">
            <div className="w-16 h-16 bg-emerald-500/20 text-emerald-500 rounded-full flex items-center justify-center mx-auto mb-4 text-3xl">✓</div>
            <h2 className="text-2xl font-bold mb-2">User Created</h2>
            <p className="text-text-muted mb-6">Please securely copy and share the generated password with the user. It will not be shown again.</p>
            <div className="bg-background border border-border p-4 rounded-xl mb-6 flex items-center justify-between">
              <code className="text-lg font-mono text-yellow-400 font-bold">{generatedPassword}</code>
              <button 
                onClick={() => {
                  navigator.clipboard.writeText(generatedPassword);
                  alert('Copied to clipboard!');
                }}
                className="text-text-muted hover:text-white flex items-center gap-1 text-sm bg-surface px-3 py-1.5 rounded-lg border border-border"
              >
                Copy
              </button>
            </div>
            <button 
              onClick={() => { setGeneratedPassword(null); setIsEditingUser(false); }} 
              className="w-full bg-emerald-500 hover:bg-emerald-600 text-black py-3 rounded-xl font-bold transition-colors shadow-lg shadow-emerald-500/20"
            >
              I have saved the password
            </button>
          </div>
        </div>
      )}
    </div>
  );
}
