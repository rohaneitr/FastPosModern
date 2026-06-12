'use client';

import React, { useEffect, useState } from 'react';
import api from '@/lib/api';

export function GlobalAnnouncement() {
  const [announcements, setAnnouncements] = useState<any[]>([]);

  useEffect(() => {
    // Fetch global announcements
    api.get('/system/announcements').then((res) => {
      if (res.data?.active && res.data.active.length > 0) {
        setAnnouncements(res.data.active);
      }
    }).catch(err => console.error('Failed to fetch announcements', err));
  }, []);

  if (announcements.length === 0) return null;

  return (
    <>
      {announcements.map((ann) => {
        const bgClass = ann.type === 'danger' ? 'bg-red-600' : ann.type === 'warning' ? 'bg-amber-600' : 'bg-blue-600';
        return (
          <div key={ann.id} className={`${bgClass} text-white px-4 py-2 flex flex-col sm:flex-row items-center justify-center gap-2 shadow-lg animate-in slide-in-from-top duration-500 z-[100] relative`}>
            <span className="font-bold text-sm uppercase tracking-wider">{ann.title}:</span>
            <span className="text-sm">{ann.message}</span>
          </div>
        );
      })}
    </>
  );
}
