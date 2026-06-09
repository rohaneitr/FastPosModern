'use client';

import React, { createContext, useContext, useState, useEffect, useCallback } from 'react';
import api from '@/lib/api';

// ── Supported Currencies ──
export interface CurrencyInfo {
  code: string;
  name: string;
  symbol: string;
  symbolNative: string;
  decimalDigits: number;
  nameBn: string; // Bengali name
}

export const CURRENCIES: CurrencyInfo[] = [
  { code: 'BDT', name: 'Bangladeshi Taka', symbol: '৳', symbolNative: '৳', decimalDigits: 2, nameBn: 'বাংলাদেশি টাকা' },
  { code: 'USD', name: 'US Dollar', symbol: '$', symbolNative: '$', decimalDigits: 2, nameBn: 'মার্কিন ডলার' },
  { code: 'EUR', name: 'Euro', symbol: '€', symbolNative: '€', decimalDigits: 2, nameBn: 'ইউরো' },
  { code: 'GBP', name: 'British Pound', symbol: '£', symbolNative: '£', decimalDigits: 2, nameBn: ' ব্রিটিশ পাউন্ড' },
  { code: 'INR', name: 'Indian Rupee', symbol: '₹', symbolNative: '₹', decimalDigits: 2, nameBn: 'ভারতীয় রুপি' },
  { code: 'AED', name: 'UAE Dirham', symbol: 'د.إ', symbolNative: 'د.إ', decimalDigits: 2, nameBn: 'আমিরাতি দিরহাম' },
  { code: 'SAR', name: 'Saudi Riyal', symbol: '﷼', symbolNative: 'ر.س', decimalDigits: 2, nameBn: 'সৌদি রিয়াল' },
  { code: 'MYR', name: 'Malaysian Ringgit', symbol: 'RM', symbolNative: 'RM', decimalDigits: 2, nameBn: 'মালয়েশিয়ান রিঙ্গিত' },
  { code: 'SGD', name: 'Singapore Dollar', symbol: 'S$', symbolNative: 'S$', decimalDigits: 2, nameBn: 'সিঙ্গাপুর ডলার' },
];

// ── Currency Settings ──
export interface CurrencySettings {
  code: string;
  symbolPosition: 'before' | 'after';
  decimalPrecision: number;
  thousandsSeparator: ',' | '.' | ' ' | '';
  decimalSeparator: '.' | ',';
}

const DEFAULT_SETTINGS: CurrencySettings = {
  code: 'BDT',
  symbolPosition: 'before',
  decimalPrecision: 2,
  thousandsSeparator: ',',
  decimalSeparator: '.',
};

// ── Exchange Rates (cached) ──
export interface ExchangeRateCache {
  base: string;
  rates: Record<string, number>;
  lastUpdated: string;
}

import { Decimal } from './decimal';

// ── Format currency value ──
export function formatCurrency(
  amount: string | number,
  settings: CurrencySettings,
  currencyOverride?: string
): string {
  const code = currencyOverride || settings.code;
  const info = CURRENCIES.find(c => c.code === code);
  const symbol = info?.symbol || code;

  // Use Decimal to safely parse and fix precision
  const decAmount = new Decimal(amount);
  const fixed = decAmount.abs().toFixed(settings.decimalPrecision);
  const [intPart, decPart] = fixed.split('.');

  // Add thousands separator
  let formatted = '';
  const digits = intPart.split('').reverse();
  for (let i = 0; i < digits.length; i++) {
    if (i > 0 && i % 3 === 0 && settings.thousandsSeparator) {
      formatted = settings.thousandsSeparator + formatted;
    }
    formatted = digits[i] + formatted;
  }

  // Add decimal part
  if (settings.decimalPrecision > 0 && decPart) {
    formatted += settings.decimalSeparator + decPart;
  }

  // Add negative sign
  const sign = decAmount.isNegative() ? '-' : '';

  // Position symbol
  if (settings.symbolPosition === 'before') {
    return `${sign}${symbol}${formatted}`;
  } else {
    return `${sign}${formatted}${symbol}`;
  }
}

// ── Convert between currencies ──
export function convertCurrency(
  amount: number,
  fromCode: string,
  toCode: string,
  rates: Record<string, number>
): number {
  if (fromCode === toCode) return amount;
  const fromRate = rates[fromCode] || 1;
  const toRate = rates[toCode] || 1;
  return (amount / fromRate) * toRate;
}

// ── Context ──
interface CurrencyContextType {
  settings: CurrencySettings;
  updateSettings: (partial: Partial<CurrencySettings>) => void;
  format: (amount: string | number, currencyOverride?: string) => string;
  convert: (amount: number, fromCode: string, toCode?: string) => number;
  rates: Record<string, number>;
  ratesLastUpdated: string | null;
  fetchRates: () => Promise<void>;
  currencies: CurrencyInfo[];
  currentCurrency: CurrencyInfo;
}

const CurrencyContext = createContext<CurrencyContextType>({
  settings: DEFAULT_SETTINGS,
  updateSettings: () => {},
  format: (amount) => `$${new Decimal(amount).toFixed(2)}`,
  convert: (amount) => amount,
  rates: {},
  ratesLastUpdated: null,
  fetchRates: async () => {},
  currencies: CURRENCIES,
  currentCurrency: CURRENCIES[0], // BDT
});

// ── Provider ──
export function CurrencyProvider({ children }: { children: React.ReactNode }) {
  const [settings, setSettings] = useState<CurrencySettings>(DEFAULT_SETTINGS);
  const [rates, setRates] = useState<Record<string, number>>({});
  const [ratesLastUpdated, setRatesLastUpdated] = useState<string | null>(null);
  const [isHydrated, setIsHydrated] = useState(false);

  // Load settings from user/business data
  useEffect(() => {
    // Load from localStorage
    const savedSettings = localStorage.getItem('fastpos_currency_settings');
    if (savedSettings) {
      try {
        const parsed = JSON.parse(savedSettings);
        setSettings(prev => ({ ...prev, ...parsed }));
      } catch {}
    }

    // Load from user's business data or SuperAdmin preference
    const userJson = localStorage.getItem('fastpos_user');
    if (userJson) {
      try {
        const user = JSON.parse(userJson);
        const isSuperAdmin = user.roles?.some((r: any) => r.name === 'SuperAdmin' || r.name === 'superadmin');
        
        if (user.preferred_currency) {
          setSettings(prev => ({ ...prev, code: user.preferred_currency }));
        } else if (isSuperAdmin) {
          const superAdminCurrency = localStorage.getItem('fpos_superadmin_currency');
          if (superAdminCurrency) {
            setSettings(prev => ({ ...prev, code: superAdminCurrency }));
          }
        } else if (user.business?.currency_code) {
          setSettings(prev => ({ ...prev, code: user.business.currency_code }));
        }
      } catch {}
    } else {
      const superAdminCurrency = localStorage.getItem('fpos_superadmin_currency');
      if (superAdminCurrency) {
        setSettings(prev => ({ ...prev, code: superAdminCurrency }));
      }
    }

    // Load cached exchange rates
    const cachedRates = localStorage.getItem('fastpos_exchange_rates');
    if (cachedRates) {
      try {
        const parsed: ExchangeRateCache = JSON.parse(cachedRates);
        setRates(parsed.rates);
        setRatesLastUpdated(parsed.lastUpdated);
      } catch {}
    }

    setIsHydrated(true);
  }, []);

  const updateSettings = useCallback((partial: Partial<CurrencySettings>) => {
    setSettings(prev => {
      const updated = { ...prev, ...partial };
      localStorage.setItem('fastpos_currency_settings', JSON.stringify(updated));
      if (partial.code) {
        localStorage.setItem('fpos_superadmin_currency', partial.code);
        
        // Sync with backend profile asynchronously using standardized API
        api.put('/profile/preferences', { preferred_currency: partial.code })
          .catch((err: any) => console.error('Failed to sync currency preference', err));
          
        // Update local user object cache
        try {
          const userJson = localStorage.getItem('fastpos_user');
          if (userJson) {
            const user = JSON.parse(userJson);
            user.preferred_currency = partial.code;
            localStorage.setItem('fastpos_user', JSON.stringify(user));
          }
        } catch {}
      }
      return updated;
    });
  }, []);

  const format = useCallback((amount: string | number, currencyOverride?: string) => {
    return formatCurrency(amount, settings, currencyOverride);
  }, [settings]);

  const convert = useCallback((amount: number, fromCode: string, toCode?: string) => {
    return convertCurrency(amount, fromCode, toCode || settings.code, rates);
  }, [settings.code, rates]);

  // Fetch live exchange rates from a free API
  const fetchRates = useCallback(async () => {
    try {
      // Using exchangerate-api.com (free tier, no key needed for open API)
      const response = await fetch('https://open.er-api.com/v6/latest/BDT');
      if (response.ok) {
        const data = await response.json();
        if (data.result === 'success' && data.rates) {
          const newRates = data.rates as Record<string, number>;
          const now = new Date().toISOString();
          setRates(newRates);
          setRatesLastUpdated(now);
          // Cache locally
          const cache: ExchangeRateCache = {
            base: 'BDT',
            rates: newRates,
            lastUpdated: now,
          };
          localStorage.setItem('fastpos_exchange_rates', JSON.stringify(cache));
        }
      }
    } catch (error) {
      console.warn('Failed to fetch exchange rates, using cached values.', error);
    }
  }, []);

  const currentCurrency = CURRENCIES.find(c => c.code === settings.code) || CURRENCIES[0];

  return (
    <CurrencyContext.Provider value={{
      settings,
      updateSettings,
      format,
      convert,
      rates,
      ratesLastUpdated,
      fetchRates,
      currencies: CURRENCIES,
      currentCurrency,
    }}
    >
      {isHydrated ? children : <div className="min-h-screen flex items-center justify-center"><div className="w-8 h-8 border-4 border-blue-500 border-t-transparent rounded-full animate-spin"></div></div>}
    </CurrencyContext.Provider>
  );
}

// ── Hook ──
export function useCurrency() {
  return useContext(CurrencyContext);
}

export default CurrencyContext;
