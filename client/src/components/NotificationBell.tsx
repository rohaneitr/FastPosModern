'use client';

import React, { useState, useEffect, useRef } from 'react';
import api from '@/lib/api';
import { usePosSounds } from '@/hooks/usePosSounds';

interface Notification {
  id: string;
  type: string;
  data: any;
  created_at: string;
  read_at: string | null;
}

export default function NotificationBell() {
  const { playTaskSuccess } = usePosSounds();
  const [notifications, setNotifications] = useState<Notification[]>([]);
  const [isOpen, setIsOpen] = useState(false);
  const dropdownRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    fetchNotifications();

    const handleClickOutside = (event: MouseEvent) => {
      if (dropdownRef.current && !dropdownRef.current.contains(event.target as Node)) {
        setIsOpen(false);
      }
    };
    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  const fetchNotifications = async () => {
    try {
      const res = await api.get('/notifications');
      if (res.data && res.data.data) {
        setNotifications(res.data.data);
      }
    } catch (e) {
    }
  };

  const markAsRead = async (id: string) => {
    // Optimistic UI update
    setNotifications(prev => prev.filter(n => n.id !== id));
    try {
      await api.put(`/notifications/${id}/read`);
      playTaskSuccess();
    } catch (e) {
      // Re-fetch to sync state on failure
      fetchNotifications();
    }
  };

  const markAllAsRead = async () => {
    setNotifications([]);
    try {
      await api.put('/notifications/read-all');
      playTaskSuccess();
    } catch (e) {
      fetchNotifications();
    }
  };

  const unreadCount = notifications.length;

  return (
    <div className="relative" ref={dropdownRef}>
      <button 
        onClick={() => setIsOpen(!isOpen)}
        className="relative p-2 rounded-full hover:bg-surface transition-colors active:scale-95"
      >
        <svg className="w-6 h-6 text-text-muted hover:text-white transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
        </svg>
        {unreadCount > 0 && (
          <span className="absolute top-1 right-1.5 w-2.5 h-2.5 bg-rose-500 rounded-full animate-pulse border-2 border-background"></span>
        )}
      </button>

      {isOpen && (
        <div className="absolute right-0 mt-2 w-80 bg-surface border border-border rounded-2xl shadow-2xl overflow-hidden z-50 animate-in fade-in slide-in-from-top-4 duration-200">
          <div className="px-4 py-3 border-b border-border flex justify-between items-center bg-background/50">
            <h3 className="font-bold text-white text-sm">Notifications</h3>
            {unreadCount > 0 && (
              <button onClick={markAllAsRead} className="text-xs text-primary hover:text-primary/80 font-medium transition-colors">
                Mark all as read
              </button>
            )}
          </div>
          
          <div className="max-h-[300px] overflow-y-auto">
            {unreadCount === 0 ? (
              <div className="p-8 text-center text-text-muted text-sm">
                No new notifications
              </div>
            ) : (
              <ul className="flex flex-col">
                {notifications.map(notif => (
                  <li key={notif.id} className="p-4 border-b border-border/50 hover:bg-white/5 transition-colors group flex items-start gap-3">
                    <div className="w-8 h-8 rounded-full bg-primary/20 text-primary flex items-center justify-center shrink-0 mt-0.5">
                      <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    </div>
                    <div className="flex-1">
                      <p className="text-sm text-white font-medium">{notif.data.subject || 'New Alert'}</p>
                      <p className="text-xs text-text-muted mt-1 line-clamp-2" dangerouslySetInnerHTML={{ __html: notif.data.message || '' }}></p>
                      <span className="text-[10px] text-text-muted opacity-60 mt-1 block">{new Date(notif.created_at).toLocaleString()}</span>
                    </div>
                    <button 
                      onClick={() => markAsRead(notif.id)}
                      className="opacity-0 group-hover:opacity-100 p-1.5 text-text-muted hover:text-white rounded-full hover:bg-surface transition-all active:scale-95"
                      title="Mark as read"
                    >
                      <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" /></svg>
                    </button>
                  </li>
                ))}
              </ul>
            )}
          </div>
        </div>
      )}
    </div>
  );
}
