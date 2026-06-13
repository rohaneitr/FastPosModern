'use client';

import React from 'react';
import { I18nProvider } from '@/lib/i18n';
import { CurrencyProvider } from '@/lib/currency';
import { Toaster } from 'react-hot-toast';
import { QueryProvider } from '@/providers/QueryProvider';

export default function Providers({ children }: { children: React.ReactNode }) {
  return (
    <QueryProvider>
      <I18nProvider>
        <CurrencyProvider>
          {children}
          <Toaster position="top-right" />
        </CurrencyProvider>
      </I18nProvider>
    </QueryProvider>
  );
}

