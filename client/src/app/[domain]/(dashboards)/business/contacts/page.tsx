'use client';

import React, { useState, useEffect } from 'react';
import Link from 'next/link';
import api from '@/lib/api';

export default function ContactsPage() {
  const [contacts, setContacts] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
  const [activeTab, setActiveTab] = useState<'all' | 'customer' | 'supplier'>('all');
  const [showDueOnly, setShowDueOnly] = useState(false);
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [formData, setFormData] = useState({
    type: 'customer',
    first_name: '',
    last_name: '',
    email: '',
    mobile: '',
    supplier_business_name: ''
  });

  const fetchContacts = async () => {
    setLoading(true);
    try {
      const url = activeTab === 'all' ? '/contacts' : `/contacts?type=${activeTab}`;
      const res = await api.get(url);
      if (res.data && res.data.data) {
        setContacts(res.data.data);
      }
    } catch (err) {
      console.error("Failed to fetch contacts", err);
      // Removed fallback mock data to enforce real API
      setContacts([]);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchContacts();
  }, [activeTab]);

  const handleInputChange = (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) => {
    const { name, value } = e.target;
    setFormData(prev => ({ ...prev, [name]: value }));
  };

  const handleCreateContact = async (e: React.FormEvent) => {
    e.preventDefault();
    try {
      await api.post('/contacts', formData);
      alert('Contact created successfully!');
      setIsModalOpen(false);
      setFormData({ type: 'customer', first_name: '', last_name: '', email: '', mobile: '', supplier_business_name: '' });
      fetchContacts();
    } catch (err: any) {
      alert(`Failed to create contact: ${err.response?.data?.message || err.message}`);
    }
  };

  return (
    <div className="flex flex-col h-full gap-6 animate-in fade-in duration-500">
      
      {/* Header & Tabs */}
      <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
          <h1 className="text-3xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-primary to-emerald-400">
            Contacts CRM
          </h1>
          <p className="text-text-muted mt-1">Manage your customers and suppliers efficiently.</p>
        </div>
        
        <button 
          onClick={() => setIsModalOpen(true)}
          className="bg-primary hover:bg-primary-hover text-white px-6 py-2 rounded-lg shadow-lg hover:shadow-[0_0_15px_rgba(59,130,246,0.5)] transition-all font-medium flex items-center gap-2"
        >
          <span>+</span> Add Contact
        </button>
      </div>

      <div className="flex flex-col md:flex-row justify-between items-center gap-4 w-full">
        <div className="glass-card rounded-xl p-2 inline-flex gap-2">
          {['all', 'customer', 'supplier'].map((tab) => (
            <button
              key={tab}
              onClick={() => setActiveTab(tab as any)}
              className={`px-4 py-2 rounded-lg text-sm font-medium transition-all capitalize ${
                activeTab === tab 
                  ? 'bg-primary text-white shadow-md' 
                  : 'text-text-muted hover:text-white hover:bg-white/5'
              }`}
            >
              {tab}
            </button>
          ))}
        </div>
        
        <div className="flex items-center gap-2">
          <label className="flex items-center gap-2 cursor-pointer text-sm font-medium text-text-muted hover:text-white transition-colors">
            <input 
              type="checkbox" 
              className="rounded bg-background/50 border-border text-primary focus:ring-primary h-4 w-4"
              checked={showDueOnly}
              onChange={(e) => setShowDueOnly(e.target.checked)}
            />
            Show Due Customers Only
          </label>
        </div>
      </div>

      {/* Grid of Contacts */}
      <div className="flex-1 overflow-y-auto pb-6 pr-2">
        {loading ? (
          <div className="flex justify-center items-center h-40 text-text-muted">
            <div className="w-8 h-8 border-4 border-primary border-t-transparent rounded-full animate-spin"></div>
          </div>
        ) : (
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
            {contacts.filter(c => showDueOnly ? (Number(c.total_due) > 0) : true).map((contact) => (
              <div 
                key={contact.id} 
                className="glass-card rounded-xl p-5 flex flex-col gap-4 border border-border hover:border-primary/50 transition-all hover:-translate-y-1 hover:shadow-xl group"
              >
                <div className="flex justify-between items-start">
                  <div className={`text-xs px-2 py-1 rounded-full uppercase tracking-wider font-bold 
                    ${contact.type === 'customer' ? 'bg-emerald-500/20 text-emerald-400' : 'bg-amber-500/20 text-amber-400'}`}
                  >
                    {contact.type}
                  </div>
                  <Link href={`/business/contacts/${contact.id}/ledger`} className="text-sm font-medium text-primary hover:text-primary-hover bg-primary/10 hover:bg-primary/20 px-3 py-1.5 rounded-lg transition-all flex items-center gap-1">
                    <span>Ledger</span>
                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" /></svg>
                  </Link>
                </div>
                
                <div className="flex items-center gap-4 mt-2">
                  <div className="w-12 h-12 rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-white font-bold text-lg shadow-inner group-hover:scale-110 transition-transform">
                    {contact.name?.charAt(0) || contact.first_name?.charAt(0) || '?'}
                  </div>
                  <div>
                    <h3 className="font-semibold text-lg line-clamp-1">{contact.name || `${contact.first_name} ${contact.last_name}`}</h3>
                    {contact.supplier_business_name && <p className="text-sm text-primary">{contact.supplier_business_name}</p>}
                  </div>
                </div>

                <div className="mt-2 flex flex-col gap-2 text-sm text-text-muted">
                  <div className="flex items-center justify-between border-b border-white/5 pb-2 mb-1">
                    <span className="text-xs uppercase tracking-wider">Due Balance</span>
                    <span className={`font-bold ${Number(contact.total_due) > 0 ? 'text-rose-400' : 'text-emerald-400'}`}>
                      ${Number(contact.total_due || 0).toFixed(2)}
                    </span>
                  </div>
                  <div className="flex items-center justify-between border-b border-white/5 pb-2 mb-1">
                    <span className="text-xs uppercase tracking-wider">Total Sales</span>
                    <span className="font-semibold text-white">
                      ${Number(contact.total_sales || 0).toFixed(2)}
                    </span>
                  </div>
                  <div className="flex items-center gap-2 mt-1">
                    <span>📧</span>
                    <span className="truncate">{contact.email || 'N/A'}</span>
                  </div>
                  <div className="flex items-center gap-2">
                    <span>📱</span>
                    <span>{contact.mobile || 'N/A'}</span>
                  </div>
                </div>
              </div>
            ))}
            {contacts.length === 0 && (
              <div className="col-span-full py-12 flex flex-col items-center justify-center text-text-muted">
                <span className="text-4xl mb-4 opacity-50">📇</span>
                <p>No contacts found.</p>
              </div>
            )}
          </div>
        )}
      </div>

      {/* Create Modal */}
      {isModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm animate-in fade-in duration-200">
          <div className="glass-card w-full max-w-md rounded-2xl p-6 shadow-2xl border border-white/10 animate-in zoom-in-95 duration-200">
            <div className="flex justify-between items-center mb-6">
              <h2 className="text-xl font-bold">New Contact</h2>
              <button onClick={() => setIsModalOpen(false)} className="text-text-muted hover:text-white">✕</button>
            </div>
            
            <form onSubmit={handleCreateContact} className="flex flex-col gap-4">
              <div>
                <label className="block text-sm font-medium text-text-muted mb-1">Contact Type</label>
                <select 
                  name="type" 
                  value={formData.type} 
                  onChange={handleInputChange}
                  className="w-full bg-background/50 border border-border rounded-lg px-4 py-2.5 outline-none focus:border-primary transition-colors text-white"
                >
                  <option value="customer">Customer</option>
                  <option value="supplier">Supplier</option>
                  <option value="both">Both</option>
                </select>
              </div>

              {formData.type === 'supplier' && (
                <div>
                  <label className="block text-sm font-medium text-text-muted mb-1">Business Name</label>
                  <input 
                    name="supplier_business_name" 
                    value={formData.supplier_business_name} 
                    onChange={handleInputChange}
                    placeholder="E.g. Acme Corp" 
                    className="w-full bg-background/50 border border-border rounded-lg px-4 py-2.5 outline-none focus:border-primary transition-colors"
                  />
                </div>
              )}

              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label className="block text-sm font-medium text-text-muted mb-1">First Name *</label>
                  <input 
                    name="first_name" 
                    value={formData.first_name} 
                    onChange={handleInputChange}
                    required
                    placeholder="John" 
                    className="w-full bg-background/50 border border-border rounded-lg px-4 py-2.5 outline-none focus:border-primary transition-colors"
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium text-text-muted mb-1">Last Name</label>
                  <input 
                    name="last_name" 
                    value={formData.last_name} 
                    onChange={handleInputChange}
                    placeholder="Doe" 
                    className="w-full bg-background/50 border border-border rounded-lg px-4 py-2.5 outline-none focus:border-primary transition-colors"
                  />
                </div>
              </div>

              <div>
                <label className="block text-sm font-medium text-text-muted mb-1">Email</label>
                <input 
                  type="email"
                  name="email" 
                  value={formData.email} 
                  onChange={handleInputChange}
                  placeholder="john@example.com" 
                  className="w-full bg-background/50 border border-border rounded-lg px-4 py-2.5 outline-none focus:border-primary transition-colors"
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-text-muted mb-1">Mobile</label>
                <input 
                  name="mobile" 
                  value={formData.mobile} 
                  onChange={handleInputChange}
                  placeholder="+1 234 567 890" 
                  className="w-full bg-background/50 border border-border rounded-lg px-4 py-2.5 outline-none focus:border-primary transition-colors"
                />
              </div>

              <div className="mt-4 flex gap-3 justify-end">
                <button 
                  type="button" 
                  onClick={() => setIsModalOpen(false)}
                  className="px-5 py-2.5 rounded-lg font-medium text-text-muted hover:bg-white/5 transition-colors"
                >
                  Cancel
                </button>
                <button 
                  type="submit" 
                  className="px-5 py-2.5 bg-primary hover:bg-primary-hover text-white rounded-lg font-medium transition-colors shadow-lg shadow-primary/30"
                >
                  Save Contact
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
