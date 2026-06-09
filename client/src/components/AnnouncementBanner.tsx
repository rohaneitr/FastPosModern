'use client';

import React, { useState, useEffect } from 'react';
import api from '@/lib/api';

interface Announcement {
  id: number;
  title: string;
  message: string;
  type: 'info' | 'warning' | 'success';
}

export default function AnnouncementBanner() {
  const [announcements, setAnnouncements] = useState<Announcement[]>([]);
  const [visibleIndex, setVisibleIndex] = useState(0);
  const [hidden, setHidden] = useState(false);

  useEffect(() => {
    // Non-blocking fetch
    api.get('/announcements')
      .then(res => {
        if (res.data && res.data.length > 0) {
          setAnnouncements(res.data);
        }
      })
      .catch(err => console.warn('Failed to fetch announcements:', err));
  }, []);

  if (hidden || announcements.length === 0) return null;

  const current = announcements[visibleIndex];

  let colorClass = 'bg-blue-500/20 text-blue-100 border-blue-500/30';
  let icon = <svg className="w-5 h-5 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>;

  if (current.type === 'warning') {
    colorClass = 'bg-amber-500/20 text-amber-100 border-amber-500/30';
    icon = <svg className="w-5 h-5 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>;
  } else if (current.type === 'success') {
    colorClass = 'bg-emerald-500/20 text-emerald-100 border-emerald-500/30';
    icon = <svg className="w-5 h-5 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>;
  }

  const handleNext = () => {
    setVisibleIndex((prev) => (prev + 1) % announcements.length);
  };

  return (
    <div className={`w-full px-4 py-2 flex items-center justify-between border-b ${colorClass} transition-colors animate-in slide-in-from-top-4 duration-300 relative z-50`}>
      <div className="flex items-center gap-3 flex-1 overflow-hidden">
        {icon}
        <div className="flex flex-col sm:flex-row sm:items-center gap-1 sm:gap-2 truncate">
          <strong className="text-sm font-bold whitespace-nowrap">{current.title}:</strong>
          <span className="text-sm opacity-90 truncate" dangerouslySetInnerHTML={{ __html: current.message }} />
        </div>
      </div>
      
      <div className="flex items-center gap-3 ml-4 shrink-0">
        {announcements.length > 1 && (
          <button onClick={handleNext} className="text-xs font-bold opacity-80 hover:opacity-100 uppercase tracking-wide">
            Next ({visibleIndex + 1}/{announcements.length})
          </button>
        )}
        <button onClick={() => setHidden(true)} className="opacity-70 hover:opacity-100 p-1 rounded-full hover:bg-black/10 transition-colors">
          <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" /></svg>
        </button>
      </div>
    </div>
  );
}
