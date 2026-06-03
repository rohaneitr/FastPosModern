'use client';

import React, { createContext, useContext, useState, useEffect, useCallback } from 'react';
import en, { type TranslationKeys } from './en';
import bn from './bn';

// ── Supported Languages ──
export const SUPPORTED_LANGUAGES = [
  { code: 'en', name: 'English', nativeName: 'English', dir: 'ltr' },
  { code: 'bn', name: 'Bengali', nativeName: 'বাংলা', dir: 'ltr' },
] as const;

export type LanguageCode = (typeof SUPPORTED_LANGUAGES)[number]['code'];

const translations: Record<LanguageCode, TranslationKeys> = { en, bn };

// ── Helper: deep access with dot notation ──
function getNestedValue(obj: any, path: string): string {
  const keys = path.split('.');
  let current = obj;
  for (const key of keys) {
    if (current === undefined || current === null) return path;
    current = current[key];
  }
  return typeof current === 'string' ? current : path;
}

// ── Context ──
interface I18nContextType {
  locale: LanguageCode;
  setLocale: (code: LanguageCode) => void;
  t: (key: string, params?: Record<string, string | number>) => string;
  dir: 'ltr' | 'rtl';
}

const I18nContext = createContext<I18nContextType>({
  locale: 'en',
  setLocale: () => {},
  t: (key) => key,
  dir: 'ltr',
});

// ── Provider ──
export function I18nProvider({ children }: { children: React.ReactNode }) {
  const [locale, setLocaleState] = useState<LanguageCode>('en');

  // Load saved language on mount
  useEffect(() => {
    const saved = localStorage.getItem('fastpos_language') as LanguageCode | null;
    if (saved && translations[saved]) {
      setLocaleState(saved);
    } else {
      // Check user data for language preference
      const userJson = localStorage.getItem('fastpos_user');
      if (userJson) {
        try {
          const user = JSON.parse(userJson);
          if (user.language && translations[user.language as LanguageCode]) {
            setLocaleState(user.language as LanguageCode);
          }
        } catch {}
      }
    }
  }, []);

  const setLocale = useCallback((code: LanguageCode) => {
    if (translations[code]) {
      setLocaleState(code);
      localStorage.setItem('fastpos_language', code);
      // Update HTML dir attribute for RTL support (future Arabic, etc.)
      const langInfo = SUPPORTED_LANGUAGES.find(l => l.code === code);
      if (langInfo) {
        document.documentElement.setAttribute('dir', langInfo.dir);
        document.documentElement.setAttribute('lang', code);
      }
    }
  }, []);

  const t = useCallback((key: string, params?: Record<string, string | number>): string => {
    let value = getNestedValue(translations[locale], key);
    // Fallback to English if not found
    if (value === key) {
      value = getNestedValue(translations.en, key);
    }
    // Replace template parameters like {year}
    if (params) {
      Object.entries(params).forEach(([k, v]) => {
        value = value.replace(`{${k}}`, String(v));
      });
    }
    return value;
  }, [locale]);

  const dir = SUPPORTED_LANGUAGES.find(l => l.code === locale)?.dir || 'ltr';

  return (
    <I18nContext.Provider value={{ locale, setLocale, t, dir }}>
      {children}
    </I18nContext.Provider>
  );
}

// ── Hook ──
export function useTranslation() {
  return useContext(I18nContext);
}

export default I18nContext;
