'use client';

import React from 'react';
import { useTranslation } from '@/lib/i18n';
import LanguageSwitcher from '@/components/LanguageSwitcher';

export default function OfflineFallbackPage() {
  const { t } = useTranslation();

  return (
    <div className="min-h-screen bg-background flex flex-col items-center justify-center p-4 relative">
      <div className="absolute top-6 right-6">
        <LanguageSwitcher />
      </div>
      
      <div className="glass-card p-10 rounded-2xl shadow-2xl border border-border/50 backdrop-blur-xl text-center max-w-md w-full animate-in zoom-in-95 duration-500">
        <div className="w-24 h-24 mx-auto mb-6 bg-surface border border-border rounded-full flex items-center justify-center">
          <span className="text-4xl">🔌</span>
        </div>
        <h1 className="text-2xl font-bold text-foreground mb-4">
          {t('common.offlineTitle')}
        </h1>
        <p className="text-text-muted mb-8 text-sm">
          {t('common.offlineMessage')}
        </p>
        
        <button 
          onClick={() => window.location.reload()}
          className="w-full bg-primary hover:bg-primary-hover text-white font-bold py-3.5 rounded-xl transition-all shadow-lg text-sm flex justify-center items-center gap-2 transform active:scale-95"
        >
          {t('common.refresh') || 'Refresh'}
        </button>
      </div>
    </div>
  );
}
