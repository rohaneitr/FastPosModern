'use client';

import React, { useState } from 'react';
import api from '@/lib/api';
import toast from 'react-hot-toast';

interface BulkMessageModalProps {
  isOpen: boolean;
  onClose: () => void;
  users: { id: number; name: string; email: string }[];
}

export default function BulkMessageModal({ isOpen, onClose, users }: BulkMessageModalProps) {
  const [selectedUserIds, setSelectedUserIds] = useState<number[]>([]);
  const [subject, setSubject] = useState('');
  const [message, setMessage] = useState('');
  const [submitting, setSubmitting] = useState(false);

  if (!isOpen) return null;

  const handleSelectAll = (checked: boolean) => {
    if (checked) {
      setSelectedUserIds(users.map(u => u.id));
    } else {
      setSelectedUserIds([]);
    }
  };

  const handleSelectUser = (id: number, checked: boolean) => {
    if (checked) {
      setSelectedUserIds(prev => [...prev, id]);
    } else {
      setSelectedUserIds(prev => prev.filter(uid => uid !== id));
    }
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (selectedUserIds.length === 0) {
      toast.error('Please select at least one recipient.');
      return;
    }
    
    setSubmitting(true);
    try {
      await api.post('/messages/bulk', {
        user_ids: selectedUserIds,
        subject,
        message
      });
      
      toast.success('Message queued successfully. It will be delivered shortly.');
      
      onClose();
    } catch (err: any) {
      if (err.response?.status === 422) {
        const errors = err.response.data.errors;
        const errorMessages = Object.values(errors).flat().join('\n');
        toast.error(errorMessages || err.response?.data?.message);
      } else {
        toast.error(err.response?.data?.message || 'Failed to dispatch bulk message');
      }
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <div className="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-50 p-4 animate-in fade-in duration-200">
      <div className="bg-surface border border-border rounded-2xl w-full max-w-2xl shadow-2xl flex flex-col max-h-[90vh]">
        <div className="flex justify-between items-center p-6 border-b border-border bg-background/50 rounded-t-2xl">
          <h2 className="text-xl font-bold text-white">Send Bulk Message</h2>
          <button onClick={onClose} className="text-text-muted hover:text-white p-2 rounded-full hover:bg-white/5 transition-colors">
            ✕
          </button>
        </div>
        
        <form onSubmit={handleSubmit} className="p-6 flex-1 overflow-y-auto flex flex-col gap-6">
          <div className="flex flex-col gap-2">
            <div className="flex justify-between items-center">
              <label className="text-sm font-medium text-text-muted">Select Recipients</label>
              <label className="flex items-center gap-2 text-xs text-white cursor-pointer hover:text-primary transition-colors">
                <input 
                  type="checkbox" 
                  checked={selectedUserIds.length === users.length && users.length > 0}
                  onChange={(e) => handleSelectAll(e.target.checked)}
                  className="accent-primary w-4 h-4"
                />
                Select All
              </label>
            </div>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-2 max-h-48 overflow-y-auto border border-border rounded-xl p-3 bg-background custom-scrollbar">
              {users.map(user => (
                <label key={user.id} className="flex items-center gap-3 p-2 rounded-lg hover:bg-white/5 cursor-pointer transition-colors">
                  <input 
                    type="checkbox" 
                    checked={selectedUserIds.includes(user.id)}
                    onChange={(e) => handleSelectUser(user.id, e.target.checked)}
                    className="accent-primary w-4 h-4"
                  />
                  <div className="flex flex-col min-w-0">
                    <span className="text-sm text-white font-medium truncate">{user.name}</span>
                    <span className="text-xs text-text-muted truncate">{user.email}</span>
                  </div>
                </label>
              ))}
            </div>
          </div>

          <div className="flex flex-col gap-2">
            <label className="text-sm font-medium text-text-muted">Subject</label>
            <input 
              required 
              value={subject} 
              onChange={e => setSubject(e.target.value)} 
              className="bg-background border border-border rounded-xl px-4 py-3 text-white outline-none focus:border-primary/50 transition-colors" 
              placeholder="Announcement: System Update"
            />
          </div>

          <div className="flex flex-col gap-2">
            <label className="text-sm font-medium text-text-muted">Message Body (HTML Supported)</label>
            <textarea 
              required 
              rows={6}
              value={message} 
              onChange={e => setMessage(e.target.value)} 
              className="bg-background border border-border rounded-xl px-4 py-3 text-white outline-none focus:border-primary/50 transition-colors resize-y custom-scrollbar" 
              placeholder="Enter your message here..."
            />
          </div>

          <div className="flex justify-end gap-3 pt-4">
            <button type="button" onClick={onClose} className="px-6 py-2.5 rounded-xl font-medium text-text-muted hover:text-white transition-colors">
              Cancel
            </button>
            <button 
              type="submit" 
              disabled={submitting}
              className="bg-primary hover:brightness-110 text-white px-8 py-2.5 rounded-xl font-bold transition-all duration-150 active:scale-[0.97] shadow-lg disabled:opacity-50 flex items-center gap-2"
            >
              {submitting ? 'Dispatching...' : 'Send Message'}
              {!submitting && <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" /></svg>}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
