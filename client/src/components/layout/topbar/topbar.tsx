'use client';

import React, { useState, useEffect } from 'react';
import { useRouter, usePathname } from 'next/navigation';
import { cn } from '@/lib/utils';
import { Menu, Search, X } from 'lucide-react';
import { useCurrency } from '@/lib/currency';
import { useTranslation } from '@/lib/i18n';
import { Spinner } from '@/components/ui/spinner';
import LanguageSwitcher from '@/components/LanguageSwitcher';
import ClockWidget from '@/components/ClockWidget';
import NotificationBell from '@/components/NotificationBell';
import api from '@/lib/api';

interface TopbarProps {
  onMenuToggle: () => void;
  className?: string;
}

export function Topbar({ onMenuToggle, className }: TopbarProps) {
  const router = useRouter();
  const pathname = usePathname();
  const { t } = useTranslation();
  const { currentCurrency, currencies, updateSettings } = useCurrency();

  const [globalSearch, setGlobalSearch] = useState('');
  const [globalResults, setGlobalResults] = useState<any[]>([]);
  const [isSearching, setIsSearching] = useState(false);

  // Close search on route change
  useEffect(() => {
    setGlobalSearch('');
  }, [pathname]);

  // Debounced global search
  useEffect(() => {
    const timer = setTimeout(async () => {
      if (!globalSearch) {
        setGlobalResults([]);
        return;
      }
      setIsSearching(true);
      try {
        const res = await api.get(`/products?page=1&search=${encodeURIComponent(globalSearch)}`);
        setGlobalResults(res.data.data || res.data || []);
      } catch {
        console.error('Global search failed');
      } finally {
        setIsSearching(false);
      }
    }, 400);
    return () => clearTimeout(timer);
  }, [globalSearch]);

  return (
    <header
      className={cn(
        'h-14 bg-background/80 border-b border-border flex items-center justify-between px-4 md:px-6 backdrop-blur-xl z-10 shrink-0',
        className
      )}
    >
      {/* Left: Hamburger + Search */}
      <div className="flex items-center gap-3">
        <button
          onClick={onMenuToggle}
          className="md:hidden text-text-muted hover:text-white p-1.5 rounded-lg hover:bg-white/5 transition-colors"
          aria-label="Toggle menu"
        >
          <Menu className="w-5 h-5" />
        </button>

        {/* Global Search */}
        <div className="hidden lg:flex relative">
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-text-muted" />
          <input
            type="text"
            value={globalSearch}
            onChange={(e) => setGlobalSearch(e.target.value)}
            placeholder={t('nav.quickSearch')}
            className="bg-surface/50 border border-border rounded-xl pl-9 pr-4 py-1.5 text-sm outline-none focus:border-emerald-500/50 focus:ring-1 focus:ring-emerald-500/20 w-72 transition-all placeholder:text-text-muted/50"
          />

          {/* Search Dropdown */}
          {globalSearch && (
            <div className="absolute top-full left-0 mt-2 w-96 bg-surface border border-border rounded-xl shadow-2xl overflow-hidden z-[100] animate-in slide-in-from-top-2 duration-200">
              <div className="bg-background/80 backdrop-blur-md px-4 py-2 border-b border-border/50 flex justify-between items-center">
                <span className="text-[10px] font-bold text-text-muted uppercase tracking-wider">
                  Search Results
                </span>
                <button
                  onClick={() => setGlobalSearch('')}
                  className="text-text-muted hover:text-white transition-colors"
                >
                  <X className="w-3.5 h-3.5" />
                </button>
              </div>

              {isSearching ? (
                <div className="p-8 text-center flex flex-col items-center gap-3">
                  <Spinner size="md" className="text-emerald-400" />
                  <span className="text-sm text-text-muted">Searching inventory…</span>
                </div>
              ) : globalResults.length > 0 ? (
                <div className="max-h-[60vh] overflow-y-auto custom-scrollbar">
                  {globalResults.map((item) => (
                    <div
                      key={item.id}
                      className="p-3 border-b border-border/20 hover:bg-white/5 flex flex-col cursor-pointer transition-colors"
                      onClick={() => {
                        setGlobalSearch('');
                        router.push('/business/products');
                      }}
                    >
                      <div className="flex justify-between items-start">
                        <span className="font-bold text-sm text-white line-clamp-1">{item.name}</span>
                        <span className="text-emerald-400 font-bold text-sm whitespace-nowrap ml-2">
                          {currentCurrency.symbol}
                          {item.sell_price_inc_tax || item.price || '0.00'}
                        </span>
                      </div>
                      <div className="flex items-center gap-2 mt-1">
                        <span className="text-xs text-text-muted font-mono bg-background/50 px-1.5 py-0.5 rounded">
                          {item.sku}
                        </span>
                        {item.generic_name && (
                          <span className="text-xs text-text-muted/70 truncate">{item.generic_name}</span>
                        )}
                      </div>
                    </div>
                  ))}
                  <div
                    className="p-3 text-center bg-background/50 hover:bg-emerald-500/10 cursor-pointer transition-colors"
                    onClick={() => {
                      setGlobalSearch('');
                      router.push('/business/products');
                    }}
                  >
                    <span className="text-xs text-emerald-400 font-bold">View all matches in Catalog →</span>
                  </div>
                </div>
              ) : (
                <div className="p-8 text-center flex flex-col items-center gap-2">
                  <Search className="w-6 h-6 text-text-muted/30" />
                  <span className="text-sm text-white font-medium">No results found</span>
                  <span className="text-xs text-text-muted">
                    No products match &quot;{globalSearch}&quot;
                  </span>
                </div>
              )}
            </div>
          )}
        </div>
      </div>

      {/* Right: Clock, Currency, Language, Notifications */}
      <div className="flex items-center gap-2.5">
        <ClockWidget />
        <select
          value={currentCurrency.code}
          onChange={(e) => updateSettings({ code: e.target.value })}
          className="bg-emerald-500/10 border border-emerald-500/20 rounded-lg px-2 py-1 text-xs font-bold text-emerald-400 outline-none cursor-pointer"
        >
          {currencies.map((c) => (
            <option key={c.code} value={c.code} className="bg-background text-foreground font-normal">
              {c.symbol} {c.code}
            </option>
          ))}
        </select>
        <LanguageSwitcher />
        <NotificationBell />
      </div>
    </header>
  );
}
