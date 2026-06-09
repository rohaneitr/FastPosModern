'use client';

import React, { useState, useEffect } from 'react';
import api from '@/lib/api';

interface Reply {
  id: number;
  message: string;
  created_at: string;
  user: { id: number; name: string; email: string };
}

interface Ticket {
  id: number;
  subject: string;
  status: string;
  priority: string;
  created_at: string;
  user: { id: number; name: string };
  replies?: Reply[];
}

export default function TenantSupportPage() {
  const [tickets, setTickets] = useState<Ticket[]>([]);
  const [loading, setLoading] = useState(true);

  // Create Modal
  const [showCreateModal, setShowCreateModal] = useState(false);
  const [createForm, setCreateForm] = useState({ subject: '', priority: 'medium', message: '' });
  const [submitting, setSubmitting] = useState(false);

  // Thread Modal
  const [activeTicket, setActiveTicket] = useState<Ticket | null>(null);
  const [replyMessage, setReplyMessage] = useState('');
  const [replying, setReplying] = useState(false);

  useEffect(() => {
    fetchTickets();
  }, []);

  const fetchTickets = async () => {
    setLoading(true);
    try {
      const res = await api.get('/tickets');
      if (res.data) setTickets(res.data);
    } catch (err) {
      console.error(err);
    } finally {
      setLoading(false);
    }
  };

  const handleCreate = async (e: React.FormEvent) => {
    e.preventDefault();
    setSubmitting(true);
    try {
      await api.post('/tickets', createForm);
      setShowCreateModal(false);
      setCreateForm({ subject: '', priority: 'medium', message: '' });
      fetchTickets();
    } catch (err) {
      console.error(err);
      alert('Failed to create ticket');
    } finally {
      setSubmitting(false);
    }
  };

  const openTicket = async (id: number) => {
    try {
      const res = await api.get(`/tickets/${id}`);
      setActiveTicket(res.data);
    } catch (err) {
      console.error(err);
    }
  };

  const handleReply = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!activeTicket || !replyMessage.trim()) return;

    setReplying(true);
    try {
      const res = await api.post(`/tickets/${activeTicket.id}/reply`, { message: replyMessage });
      // Optimistic Update
      setActiveTicket(prev => prev ? {
        ...prev,
        replies: [...(prev.replies || []), res.data.reply]
      } : null);
      setReplyMessage('');
    } catch (err) {
      console.error(err);
      alert('Failed to send reply');
    } finally {
      setReplying(false);
    }
  };

  const closeTicket = async () => {
    if (!activeTicket) return;
    try {
      await api.put(`/tickets/${activeTicket.id}/status`, { status: 'closed' });
      setActiveTicket(prev => prev ? { ...prev, status: 'closed' } : null);
      setTickets(prev => prev.map(t => t.id === activeTicket.id ? { ...t, status: 'closed' } : t));
    } catch (err) {
      console.error(err);
    }
  };

  const getPriorityColor = (p: string) => {
    switch (p) {
      case 'urgent': return 'text-rose-500 bg-rose-500/10 border-rose-500/20';
      case 'high': return 'text-orange-500 bg-orange-500/10 border-orange-500/20';
      case 'low': return 'text-slate-400 bg-slate-500/10 border-slate-500/20';
      default: return 'text-blue-400 bg-blue-500/10 border-blue-500/20';
    }
  };

  const getStatusColor = (s: string) => {
    switch (s) {
      case 'closed': return 'text-slate-400 border-slate-500/20';
      case 'resolved': return 'text-emerald-400 border-emerald-500/20';
      case 'in_progress': return 'text-amber-400 border-amber-500/20';
      default: return 'text-cyan-400 border-cyan-500/20'; // open
    }
  };

  return (
    <div className="flex flex-col h-full gap-8 animate-in fade-in duration-500 pb-12 w-full">
      <div className="flex justify-between items-center">
        <div>
          <h1 className="text-3xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-blue-400 to-indigo-500">
            Support Center
          </h1>
          <p className="text-text-muted mt-1">Get help and communicate with platform administrators.</p>
        </div>
        <button 
          onClick={() => setShowCreateModal(true)}
          className="bg-indigo-500 hover:bg-indigo-400 text-white px-6 py-2.5 rounded-xl font-bold transition-all shadow-lg shadow-indigo-500/20 active:scale-95 flex items-center gap-2"
        >
          <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" /></svg>
          New Ticket
        </button>
      </div>

      <div className="glass-card rounded-2xl border border-border shadow-2xl overflow-hidden flex-1 flex flex-col">
        {loading ? (
           <div className="p-12 text-center text-text-muted flex justify-center items-center">
             <div className="w-6 h-6 border-2 border-indigo-500 border-t-transparent rounded-full animate-spin"></div>
           </div>
        ) : tickets.length === 0 ? (
           <div className="p-16 text-center text-text-muted flex flex-col items-center gap-4">
             <div className="w-16 h-16 rounded-full bg-surface flex items-center justify-center text-2xl">🎧</div>
             <p>No support tickets found. We are here if you need us!</p>
           </div>
        ) : (
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 p-6">
            {tickets.map(ticket => (
              <div 
                key={ticket.id} 
                onClick={() => openTicket(ticket.id)}
                className="bg-surface/50 border border-border rounded-xl p-5 hover:bg-surface hover:border-indigo-500/50 cursor-pointer transition-all hover:shadow-lg group"
              >
                <div className="flex justify-between items-start mb-3">
                  <span className={`text-[10px] uppercase font-black px-2 py-0.5 rounded border ${getStatusColor(ticket.status)}`}>
                    {ticket.status.replace('_', ' ')}
                  </span>
                  <span className={`text-[10px] uppercase font-black px-2 py-0.5 rounded border ${getPriorityColor(ticket.priority)}`}>
                    {ticket.priority}
                  </span>
                </div>
                <h3 className="font-bold text-white text-lg line-clamp-1 group-hover:text-indigo-400 transition-colors">{ticket.subject}</h3>
                <div className="flex justify-between items-center mt-4 text-xs text-text-muted">
                  <span>#{ticket.id.toString().padStart(4, '0')}</span>
                  <span>{new Date(ticket.created_at).toLocaleDateString()}</span>
                </div>
              </div>
            ))}
          </div>
        )}
      </div>

      {/* Create Modal */}
      {showCreateModal && (
        <div className="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-50 p-4 animate-in fade-in duration-200">
          <div className="bg-surface border border-border rounded-2xl w-full max-w-lg shadow-2xl p-6">
            <h2 className="text-xl font-bold text-white mb-6">Open Support Ticket</h2>
            <form onSubmit={handleCreate} className="flex flex-col gap-4">
              <input 
                required placeholder="Subject" 
                value={createForm.subject} onChange={e => setCreateForm({...createForm, subject: e.target.value})}
                className="w-full bg-background border border-border rounded-xl px-4 py-3 outline-none focus:border-indigo-500 text-white" 
              />
              <select 
                value={createForm.priority} onChange={e => setCreateForm({...createForm, priority: e.target.value})}
                className="w-full bg-background border border-border rounded-xl px-4 py-3 outline-none focus:border-indigo-500 text-white cursor-pointer"
              >
                <option value="low">Low Priority</option>
                <option value="medium">Medium Priority</option>
                <option value="high">High Priority</option>
                <option value="urgent">Urgent</option>
              </select>
              <textarea 
                required placeholder="Describe your issue in detail..." rows={5}
                value={createForm.message} onChange={e => setCreateForm({...createForm, message: e.target.value})}
                className="w-full bg-background border border-border rounded-xl px-4 py-3 outline-none focus:border-indigo-500 text-white resize-y custom-scrollbar"
              />
              <div className="flex justify-end gap-3 mt-4">
                <button type="button" onClick={() => setShowCreateModal(false)} className="px-5 py-2 text-text-muted hover:text-white font-medium">Cancel</button>
                <button type="submit" disabled={submitting} className="bg-indigo-500 hover:bg-indigo-400 text-white px-6 py-2 rounded-xl font-bold transition-colors disabled:opacity-50">
                  {submitting ? 'Submitting...' : 'Submit Ticket'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Thread Modal */}
      {activeTicket && (
        <div className="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-50 p-4 animate-in fade-in duration-200">
          <div className="bg-surface border border-border rounded-2xl w-full max-w-3xl shadow-2xl flex flex-col max-h-[90vh]">
            {/* Thread Header */}
            <div className="p-6 border-b border-border bg-background/50 flex justify-between items-start rounded-t-2xl shrink-0">
              <div>
                <h2 className="text-xl font-bold text-white">{activeTicket.subject}</h2>
                <div className="flex gap-2 mt-2">
                  <span className={`text-[10px] uppercase font-black px-2 py-0.5 rounded border ${getStatusColor(activeTicket.status)}`}>{activeTicket.status.replace('_', ' ')}</span>
                  <span className={`text-[10px] uppercase font-black px-2 py-0.5 rounded border ${getPriorityColor(activeTicket.priority)}`}>{activeTicket.priority}</span>
                </div>
              </div>
              <div className="flex gap-2">
                {activeTicket.status !== 'closed' && (
                  <button onClick={closeTicket} className="px-3 py-1.5 rounded-lg bg-surface hover:bg-white/5 border border-border text-xs font-medium text-text-muted hover:text-white transition-colors">
                    Close Ticket
                  </button>
                )}
                <button onClick={() => setActiveTicket(null)} className="p-1.5 rounded-lg hover:bg-white/5 text-text-muted hover:text-white transition-colors">
                  <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
              </div>
            </div>

            {/* Thread Body */}
            <div className="flex-1 overflow-y-auto p-6 flex flex-col gap-6 custom-scrollbar bg-surface">
              {activeTicket.replies?.map((reply, idx) => {
                const isMine = reply.user?.name === activeTicket.user?.name; // Simplistic check
                return (
                  <div key={reply.id || idx} className={`flex flex-col max-w-[85%] ${isMine ? 'self-end items-end' : 'self-start items-start'}`}>
                    <span className="text-[10px] text-text-muted mb-1 px-1">{reply.user?.name} • {new Date(reply.created_at).toLocaleString()}</span>
                    <div className={`p-4 rounded-2xl text-sm ${isMine ? 'bg-indigo-500/20 border border-indigo-500/30 text-indigo-50 rounded-tr-sm' : 'bg-background border border-border text-text-muted rounded-tl-sm'}`}>
                      {reply.message}
                    </div>
                  </div>
                );
              })}
            </div>

            {/* Thread Footer (Reply Box) */}
            {activeTicket.status !== 'closed' ? (
              <div className="p-4 border-t border-border bg-background/50 rounded-b-2xl shrink-0">
                <form onSubmit={handleReply} className="flex gap-3">
                  <input 
                    value={replyMessage} onChange={e => setReplyMessage(e.target.value)}
                    placeholder="Type your reply..."
                    className="flex-1 bg-surface border border-border rounded-xl px-4 py-2 text-sm text-white outline-none focus:border-indigo-500"
                  />
                  <button type="submit" disabled={replying || !replyMessage.trim()} className="bg-indigo-500 hover:bg-indigo-400 text-white px-5 py-2 rounded-xl font-bold disabled:opacity-50 transition-colors">
                    Reply
                  </button>
                </form>
              </div>
            ) : (
              <div className="p-4 border-t border-border bg-background/50 rounded-b-2xl text-center text-sm text-text-muted shrink-0">
                This ticket is closed and cannot receive new replies.
              </div>
            )}
          </div>
        </div>
      )}
    </div>
  );
}
