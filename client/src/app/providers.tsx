'use client';

import React from 'react';
import { I18nProvider } from '@/lib/i18n';
import { CurrencyProvider } from '@/lib/currency';

export default function Providers({ children }: { children: React.ReactNode }) {
  return (
    <I18nProvider>
      <CurrencyProvider>
        {children}
      </CurrencyProvider>
    </I18nProvider>
  );
}
