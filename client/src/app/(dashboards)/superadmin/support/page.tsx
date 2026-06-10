'use client';

import React, { useState, useEffect } from 'react';
import api from '@/lib/api';

interface Reply {
  id: number;
  message: string;
  created_at: string;
  user: { id: number; name: string; email: string } | null;
  is_admin_reply: boolean;
}

interface Ticket {
  id: number;
  subject: string;
  status: string;
  priority: string;
  created_at: string;
  user: { id: number; name: string };
  business?: { id: number; name: string; subdomain: string };
  replies?: Reply[];
}

export default function SuperadminSupportPage() {
  const [tickets, setTickets] = useState<Ticket[]>([]);
  const [loading, setLoading] = useState(true);

  // Thread Modal
  const [activeTicket, setActiveTicket] = useState<Ticket | null>(null);
  const [replyMessage, setReplyMessage] = useState('');
  const [replying, setReplying] = useState(false);
  
  // Filter state
  const [statusFilter, setStatusFilter] = useState('all');
  
  // Toast
  const [toast, setToast] = useState<string | null>(null);
  const showToast = (msg: string) => { setToast(msg); setTimeout(() => setToast(null), 3000); };

  useEffect(() => {
    fetchTickets();
  }, []);

  const fetchTickets = async () => {
    setLoading(true);
    try {
      const res = await api.get('/superadmin/tickets');
      if (res.data && res.data.tickets) setTickets(res.data.tickets.data);
    } catch (err: any) {
      showToast(err?.response?.data?.message ?? 'Failed to load support tickets');
    } finally {
      setLoading(false);
    }
  };

  const openTicket = async (id: number) => {
    try {
      const res = await api.get(`/superadmin/tickets/${id}`);
      setActiveTicket(res.data.ticket);
    } catch (err: any) {
      showToast(err?.response?.data?.message ?? 'Failed to open ticket');
    }
  };

  const handleReply = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!activeTicket || !replyMessage.trim()) return;

    setReplying(true);
    try {
      const res = await api.post(`/superadmin/tickets/${activeTicket.id}/reply`, { message: replyMessage });
      // Optimistic Update
      setActiveTicket(prev => prev ? {
        ...prev,
        replies: [...(prev.replies || []), res.data.reply]
      } : null);
      setReplyMessage('');
    } catch (err: any) {
      showToast(err?.response?.data?.message ?? 'Failed to send reply');
    } finally {
      setReplying(false);
    }
  };

  const updateStatus = async (status: string) => {
    if (!activeTicket) return;
    try {
      await api.patch(`/superadmin/tickets/${activeTicket.id}/status`, { status });
      setActiveTicket(prev => prev ? { ...prev, status } : null);
      setTickets(prev => prev.map(t => t.id === activeTicket.id ? { ...t, status } : t));
    } catch (err: any) {
      showToast(err?.response?.data?.message ?? 'Failed to update ticket status');
    }
  };

  const getPriorityColor = (p: string) => {
    switch (p) {
      case 'Urgent': return 'text-rose-500 bg-rose-500/10 border-rose-500/20';
      case 'High': return 'text-orange-500 bg-orange-500/10 border-orange-500/20';
      case 'Low': return 'text-slate-400 bg-slate-500/10 border-slate-500/20';
      default: return 'text-blue-400 bg-blue-500/10 border-blue-500/20';
    }
  };

  const getStatusColor = (s: string) => {
    switch (s) {
      case 'Closed': return 'text-slate-400 border-slate-500/20';
      case 'Resolved': return 'text-emerald-400 border-emerald-500/20';
      case 'In Progress': return 'text-amber-400 border-amber-500/20';
      default: return 'text-cyan-400 border-cyan-500/20'; // open
    }
  };

  const filteredTickets = tickets.filter(t => statusFilter === 'all' || t.status === statusFilter);

  return (
    <>
      {toast && (
        <div className="fixed top-6 right-6 z-50 px-5 py-3 rounded-xl text-sm font-semibold bg-rose-500/15 border border-rose-500/30 text-rose-300 shadow-2xl animate-in slide-in-from-top-4 duration-300">
          ❌ {toast}
        </div>
      )}
      <div className="flex flex-col h-full gap-8 animate-in fade-in duration-500 pb-12 w-full max-w-7xl mx-auto">
      <div className="flex justify-between items-end">
        <div>
          <h1 className="text-3xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-purple-400 to-pink-500">
            Global Support Desk
          </h1>
          <p className="text-text-muted mt-1">Manage and resolve tickets across all tenant workspaces.</p>
        </div>
        <div className="flex items-center gap-3">
          <select 
            value={statusFilter} onChange={e => setStatusFilter(e.target.value)}
            className="bg-surface border border-border rounded-xl px-4 py-2 text-sm text-white outline-none cursor-pointer hover:border-purple-500/50 transition-colors"
          >
            <option value="all">All Statuses</option>
            <option value="Open">Open</option>
            <option value="In Progress">In Progress</option>
            <option value="Resolved">Resolved</option>
            <option value="Closed">Closed</option>
          </select>
          <button onClick={fetchTickets} className="p-2.5 rounded-xl bg-surface hover:bg-surface/80 border border-border transition-colors">
            <svg className={`w-4 h-4 text-text-muted ${loading ? 'animate-spin' : ''}`} fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
          </button>
        </div>
      </div>

      <div className="glass-card rounded-2xl border border-border shadow-2xl overflow-hidden flex-1 flex flex-col">
        {loading && tickets.length === 0 ? (
           <div className="p-12 text-center text-text-muted flex justify-center items-center">
             <div className="w-6 h-6 border-2 border-purple-500 border-t-transparent rounded-full animate-spin"></div>
           </div>
        ) : filteredTickets.length === 0 ? (
           <div className="p-16 text-center text-text-muted flex flex-col items-center gap-4">
             <div className="w-16 h-16 rounded-full bg-surface flex items-center justify-center text-2xl">🎧</div>
             <p>No support tickets match your criteria. Inbox Zero!</p>
           </div>
        ) : (
          <div className="w-full overflow-x-auto">
            <table className="w-full text-left text-sm whitespace-nowrap">
              <thead className="bg-background/50 border-b border-border">
                <tr>
                  <th className="p-5 font-semibold text-text-muted">Ticket ID / Subject</th>
                  <th className="p-5 font-semibold text-text-muted">Tenant (Business)</th>
                  <th className="p-5 font-semibold text-text-muted">Priority</th>
                  <th className="p-5 font-semibold text-text-muted">Status</th>
                  <th className="p-5 font-semibold text-text-muted text-right">Created At</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-border/50">
                {filteredTickets.map(ticket => (
                  <tr 
                    key={ticket.id} 
                    onClick={() => openTicket(ticket.id)}
                    className="hover:bg-white/5 transition-colors cursor-pointer group"
                  >
                    <td className="p-5">
                      <p className="font-bold text-white group-hover:text-purple-400 transition-colors">
                        #{ticket.id.toString().padStart(4, '0')} — {ticket.subject}
                      </p>
                    </td>
                    <td className="p-5 text-text-muted font-medium">
                      {ticket.business ? ticket.business.name : 'Unknown Tenant'}
                    </td>
                    <td className="p-5">
                      <span className={`text-[10px] uppercase font-black px-2 py-0.5 rounded border ${getPriorityColor(ticket.priority)}`}>
                        {ticket.priority}
                      </span>
                    </td>
                    <td className="p-5">
                      <span className={`text-[10px] uppercase font-black px-2 py-0.5 rounded border ${getStatusColor(ticket.status)}`}>
                        {ticket.status.replace('_', ' ')}
                      </span>
                    </td>
                    <td className="p-5 text-right text-text-muted text-xs">
                      {new Date(ticket.created_at).toLocaleString()}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>

      {/* Thread Modal */}
      {activeTicket && (
        <div className="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-50 p-4 animate-in fade-in duration-200">
          <div className="bg-surface border border-purple-500/20 rounded-2xl w-full max-w-4xl shadow-[0_0_40px_rgba(168,85,247,0.1)] flex flex-col max-h-[90vh]">
            {/* Thread Header */}
            <div className="p-6 border-b border-border bg-background/50 flex justify-between items-start rounded-t-2xl shrink-0">
              <div>
                <div className="flex items-center gap-3 mb-1">
                  <h2 className="text-xl font-bold text-white">#{activeTicket.id.toString().padStart(4, '0')} - {activeTicket.subject}</h2>
                  <span className={`text-[10px] uppercase font-black px-2 py-0.5 rounded border ${getStatusColor(activeTicket.status)}`}>{activeTicket.status.replace('_', ' ')}</span>
                  <span className={`text-[10px] uppercase font-black px-2 py-0.5 rounded border ${getPriorityColor(activeTicket.priority)}`}>{activeTicket.priority}</span>
                </div>
                <p className="text-xs text-text-muted">
                  Tenant: <span className="text-purple-400 font-bold">{activeTicket.business?.name || 'Unknown'}</span> 
                  &nbsp;•&nbsp; Requested By: {activeTicket.user?.name}
                </p>
              </div>
              <div className="flex gap-2">
                <select 
                  value={activeTicket.status}
                  onChange={(e) => updateStatus(e.target.value)}
                  className="bg-background border border-border rounded-lg px-3 py-1.5 text-xs text-white outline-none cursor-pointer focus:border-purple-500/50"
                >
                  <option value="Open">Open</option>
                  <option value="In Progress">In Progress</option>
                  <option value="Resolved">Resolved</option>
                  <option value="Closed">Closed</option>
                </select>
                <button onClick={() => setActiveTicket(null)} className="p-1.5 rounded-lg hover:bg-white/5 text-text-muted hover:text-white transition-colors">
                  <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
              </div>
            </div>

            {/* Thread Body */}
            <div className="flex-1 overflow-y-auto p-6 flex flex-col gap-6 custom-scrollbar bg-surface/50">
              {activeTicket.replies?.map((reply, idx) => {
                const isSuperAdmin = reply.is_admin_reply;
                return (
                  <div key={reply.id || idx} className={`flex flex-col max-w-[85%] ${isSuperAdmin ? 'self-end items-end' : 'self-start items-start'}`}>
                    <span className="text-[10px] text-text-muted mb-1 px-1">
                      {isSuperAdmin ? 'SuperAdmin (You)' : (reply.user?.name || 'User')} • {new Date(reply.created_at).toLocaleString()}
                    </span>
                    <div className={`p-4 rounded-2xl text-sm shadow-md ${isSuperAdmin ? 'bg-purple-600/20 border border-purple-500/30 text-purple-50 rounded-tr-sm' : 'bg-background border border-border text-text-muted rounded-tl-sm'}`}>
                      {reply.message}
                    </div>
                  </div>
                );
              })}
            </div>

            {/* Thread Footer (Reply Box) */}
            <div className="p-4 border-t border-border bg-background/50 rounded-b-2xl shrink-0">
              <form onSubmit={handleReply} className="flex gap-3">
                <input 
                  value={replyMessage} onChange={e => setReplyMessage(e.target.value)}
                  placeholder="Type your response to the tenant..."
                  className="flex-1 bg-surface border border-border rounded-xl px-4 py-3 text-sm text-white outline-none focus:border-purple-500/50"
                />
                <button type="submit" disabled={replying || !replyMessage.trim()} className="bg-purple-600 hover:bg-purple-500 text-white px-8 py-3 rounded-xl font-bold disabled:opacity-50 transition-colors shadow-lg shadow-purple-500/20 active:scale-95">
                  Send Reply
                </button>
              </form>
            </div>
          </div>
        </div>
      )}
    </div>
    </>
  );
}
