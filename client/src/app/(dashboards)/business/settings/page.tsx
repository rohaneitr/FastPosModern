'use client';

import React, { useState, useEffect } from 'react';
import api from '@/lib/api';
import { useTranslation, SUPPORTED_LANGUAGES, type LanguageCode } from '@/lib/i18n';
import { useCurrency, CURRENCIES } from '@/lib/currency';

export default function SettingsPage() {
  const { t, locale, setLocale } = useTranslation();
  const { settings: currencySettings, updateSettings: updateCurrency, rates, ratesLastUpdated, fetchRates, currencies } = useCurrency();
  const [activeTab, setActiveTab] = useState('business');
  const [settings, setSettings] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const [rateLoading, setRateLoading] = useState(false);

  useEffect(() => { fetchSettings(); }, []);

  const fetchSettings = async () => {
    setLoading(true);
    try {
      const res = await api.get('/settings');
      if (res.data) setSettings(res.data);
    } catch {
      setSettings({
        business: { name: 'Demo Business', time_zone: 'Asia/Dhaka', currency_code: currencySettings.code },
        locations: [{ id: 1, name: 'Main HQ' }],
        tax_rates: [{ id: 1, name: 'VAT', amount: '15.00' }],
        printers: [],
      });
    } finally { setLoading(false); }
  };

  const handleFetchRates = async () => {
    setRateLoading(true);
    await fetchRates();
    setRateLoading(false);
  };

  const tabs = [
    { id: 'business', label: t('settings.businessProfile'), icon: '🏢' },
    { id: 'currency', label: t('settings.currencySettings'), icon: '💱' },
    { id: 'language', label: t('settings.languageSettings'), icon: '🌐' },
    { id: 'locations', label: t('settings.locations'), icon: '📍' },
    { id: 'tax', label: t('settings.taxRates'), icon: '💰' },
    { id: 'printers', label: t('settings.receiptPrinters'), icon: '🖨️' },
    { id: 'invoices', label: t('settings.invoicesBarcodes'), icon: '🧾' },
  ];

  return (
    <div className="flex flex-col h-full gap-8 animate-in fade-in duration-500 pb-12">
      <div>
        <h1 className="text-3xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-blue-400 to-teal-400">
          {t('settings.masterSettings')}
        </h1>
        <p className="text-text-muted mt-1">{t('settings.masterSettingsDesc')}</p>
      </div>

      <div className="flex flex-col md:flex-row gap-6">
        <div className="w-full md:w-64 flex flex-col gap-2">
          {tabs.map(tab => (
            <button key={tab.id} onClick={() => setActiveTab(tab.id)}
              className={`text-left px-4 py-3 rounded-xl font-medium transition-all flex items-center gap-3 ${
                activeTab === tab.id ? 'bg-primary text-white shadow-lg' : 'bg-surface/50 text-text-muted hover:bg-surface hover:text-white border border-border'}`}>
              <span>{tab.icon}</span> {tab.label}
            </button>
          ))}
        </div>

        <div className="flex-1 glass-card rounded-2xl p-6 border border-border min-h-[400px]">
          {loading ? (
            <div className="flex h-full items-center justify-center text-text-muted">{t('settings.loadingSettings')}</div>
          ) : (
            <div className="animate-in slide-in-from-right-4 duration-300">
              
              {/* ── Business Profile ── */}
              {activeTab === 'business' && (
                <div className="flex flex-col gap-4">
                  <h2 className="text-xl font-semibold mb-4 border-b border-border pb-2">{t('settings.businessProfile')}</h2>
                  <div className="grid grid-cols-2 gap-6">
                    <div>
                      <label className="block text-sm text-text-muted mb-1">{t('settings.businessName')}</label>
                      <input className="w-full bg-background/50 border border-border rounded-lg p-2" defaultValue={settings.business?.name} />
                    </div>
                    <div>
                      <label className="block text-sm text-text-muted mb-1">{t('settings.timeZone')}</label>
                      <input className="w-full bg-background/50 border border-border rounded-lg p-2" defaultValue={settings.business?.time_zone} />
                    </div>
                  </div>
                  <button className="mt-4 bg-primary text-white px-6 py-2 rounded-lg self-start">{t('common.save')}</button>
                </div>
              )}

              {/* ── Currency Settings ── */}
              {activeTab === 'currency' && (
                <div className="flex flex-col gap-6">
                  <h2 className="text-xl font-semibold mb-2 border-b border-border pb-2">{t('settings.currencySettings')}</h2>
                  
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                      <label className="block text-sm text-text-muted mb-1">{t('settings.defaultCurrency')}</label>
                      <select value={currencySettings.code}
                        onChange={(e) => updateCurrency({ code: e.target.value })}
                        className="w-full bg-background/50 border border-border rounded-lg p-2.5 text-foreground">
                        {currencies.map(c => (
                          <option key={c.code} value={c.code}>{c.symbol} {c.code} — {c.name}</option>
                        ))}
                      </select>
                    </div>
                    <div>
                      <label className="block text-sm text-text-muted mb-1">{t('settings.symbolPosition')}</label>
                      <select value={currencySettings.symbolPosition}
                        onChange={(e) => updateCurrency({ symbolPosition: e.target.value as 'before' | 'after' })}
                        className="w-full bg-background/50 border border-border rounded-lg p-2.5 text-foreground">
                        <option value="before">{t('settings.beforeAmount')} (৳100)</option>
                        <option value="after">{t('settings.afterAmount')} (100৳)</option>
                      </select>
                    </div>
                    <div>
                      <label className="block text-sm text-text-muted mb-1">{t('settings.decimalPrecision')}</label>
                      <select value={currencySettings.decimalPrecision}
                        onChange={(e) => updateCurrency({ decimalPrecision: parseInt(e.target.value) })}
                        className="w-full bg-background/50 border border-border rounded-lg p-2.5 text-foreground">
                        <option value="0">0 (100)</option>
                        <option value="2">2 (100.00)</option>
                        <option value="3">3 (100.000)</option>
                      </select>
                    </div>
                    <div>
                      <label className="block text-sm text-text-muted mb-1">{t('settings.thousandsSeparator')}</label>
                      <select value={currencySettings.thousandsSeparator}
                        onChange={(e) => updateCurrency({ thousandsSeparator: e.target.value as any })}
                        className="w-full bg-background/50 border border-border rounded-lg p-2.5 text-foreground">
                        <option value=",">, (1,000)</option>
                        <option value=".">. (1.000)</option>
                        <option value=" ">Space (1 000)</option>
                        <option value="">None (1000)</option>
                      </select>
                    </div>
                  </div>

                  {/* Preview */}
                  <div className="bg-surface/50 border border-border rounded-xl p-4">
                    <p className="text-sm text-text-muted mb-2">Preview:</p>
                    <p className="text-2xl font-bold text-white">
                      {(() => {
                        const info = CURRENCIES.find(c => c.code === currencySettings.code);
                        const sym = info?.symbol || currencySettings.code;
                        const num = (1234567.89).toFixed(currencySettings.decimalPrecision);
                        const [intPart, decPart] = num.split('.');
                        let formatted = '';
                        const digits = intPart.split('').reverse();
                        for (let i = 0; i < digits.length; i++) {
                          if (i > 0 && i % 3 === 0 && currencySettings.thousandsSeparator) formatted = currencySettings.thousandsSeparator + formatted;
                          formatted = digits[i] + formatted;
                        }
                        if (decPart) formatted += currencySettings.decimalSeparator + decPart;
                        return currencySettings.symbolPosition === 'before' ? `${sym}${formatted}` : `${formatted}${sym}`;
                      })()}
                    </p>
                  </div>

                  {/* Exchange Rates */}
                  <div>
                    <div className="flex justify-between items-center mb-3">
                      <h3 className="text-lg font-semibold text-white">{t('settings.exchangeRates')}</h3>
                      <button onClick={handleFetchRates} disabled={rateLoading}
                        className="bg-primary/20 text-primary px-4 py-1.5 rounded-lg text-sm font-medium hover:bg-primary/30 transition-colors disabled:opacity-50 flex items-center gap-2">
                        {rateLoading && <div className="w-3 h-3 border-2 border-primary/30 border-t-primary rounded-full animate-spin" />}
                        {t('settings.updateRates')}
                      </button>
                    </div>
                    {ratesLastUpdated && (
                      <p className="text-xs text-text-muted mb-3">{t('settings.lastUpdated')}: {new Date(ratesLastUpdated).toLocaleString()}</p>
                    )}
                    <div className="grid grid-cols-2 md:grid-cols-3 gap-3">
                      {currencies.map(c => (
                        <div key={c.code} className={`bg-surface/50 border rounded-lg p-3 flex justify-between items-center ${c.code === currencySettings.code ? 'border-primary/50' : 'border-border'}`}>
                          <div>
                            <span className="text-lg mr-1">{c.symbol}</span>
                            <span className="text-sm font-bold text-white">{c.code}</span>
                          </div>
                          <span className="text-sm text-text-muted font-mono">
                            {rates[c.code] ? rates[c.code].toFixed(4) : '—'}
                          </span>
                        </div>
                      ))}
                    </div>
                  </div>
                </div>
              )}

              {/* ── Language Settings ── */}
              {activeTab === 'language' && (
                <div className="flex flex-col gap-6">
                  <h2 className="text-xl font-semibold mb-2 border-b border-border pb-2">{t('settings.languageSettings')}</h2>
                  <div className="max-w-md">
                    <label className="block text-sm text-text-muted mb-2">{t('settings.selectLanguage')}</label>
                    <div className="flex flex-col gap-3">
                      {SUPPORTED_LANGUAGES.map(lang => (
                        <button key={lang.code} onClick={() => setLocale(lang.code as LanguageCode)}
                          className={`flex items-center justify-between p-4 rounded-xl border transition-all ${
                            locale === lang.code ? 'border-primary bg-primary/10 text-white' : 'border-border bg-surface/50 text-text-muted hover:border-primary/30'}`}>
                          <div className="flex items-center gap-3">
                            <span className="text-2xl">{lang.code === 'en' ? '🇬🇧' : '🇧🇩'}</span>
                            <div>
                              <p className="font-semibold text-white">{lang.nativeName}</p>
                              <p className="text-xs text-text-muted">{lang.name}</p>
                            </div>
                          </div>
                          {locale === lang.code && (
                            <span className="text-primary text-lg">✓</span>
                          )}
                        </button>
                      ))}
                    </div>
                    <p className="text-xs text-text-muted mt-4">
                      {locale === 'bn' ? 'ভাষা পরিবর্তন পুরো অ্যাপ্লিকেশনে প্রযোজ্য হবে।' : 'Language changes will apply across the entire application.'}
                    </p>
                  </div>
                </div>
              )}

              {/* ── Locations ── */}
              {activeTab === 'locations' && (
                <div>
                  <div className="flex justify-between items-center mb-4 border-b border-border pb-2">
                    <h2 className="text-xl font-semibold">{t('settings.locations')}</h2>
                    <button className="text-sm bg-primary/20 text-primary px-3 py-1 rounded">{t('settings.addLocation')}</button>
                  </div>
                  <div className="grid gap-3">
                    {settings.locations?.map((loc: any) => (
                      <div key={loc.id} className="bg-surface/50 p-4 rounded-lg border border-border flex justify-between">
                        <span className="font-medium">{loc.name}</span>
                        <span className="text-text-muted text-sm px-2 bg-background rounded">ID: {loc.id}</span>
                      </div>
                    ))}
                  </div>
                </div>
              )}

              {/* ── Tax Rates ── */}
              {activeTab === 'tax' && (
                <div>
                  <div className="flex justify-between items-center mb-4 border-b border-border pb-2">
                    <h2 className="text-xl font-semibold">{t('settings.taxRates')}</h2>
                    <button className="text-sm bg-primary/20 text-primary px-3 py-1 rounded">{t('settings.addTax')}</button>
                  </div>
                  <div className="grid gap-3">
                    {settings.tax_rates?.map((tax: any) => (
                      <div key={tax.id} className="bg-surface/50 p-4 rounded-lg border border-border flex justify-between">
                        <span className="font-medium">{tax.name}</span>
                        <span className="text-primary font-bold">{parseFloat(tax.amount)}%</span>
                      </div>
                    ))}
                  </div>
                </div>
              )}

              {/* ── Printers ── */}
              {activeTab === 'printers' && (
                <div>
                  <div className="flex justify-between items-center mb-4 border-b border-border pb-2">
                    <h2 className="text-xl font-semibold">{t('settings.receiptPrinters')}</h2>
                    <button className="text-sm bg-primary/20 text-primary px-3 py-1 rounded">{t('settings.addPrinter')}</button>
                  </div>
                  <div className="grid gap-3">
                    {settings.printers?.length === 0 ? (
                      <p className="text-text-muted text-sm">{t('common.noData')}</p>
                    ) : settings.printers?.map((p: any) => (
                      <div key={p.id} className="bg-surface/50 p-4 rounded-lg border border-border flex justify-between items-center">
                        <div>
                          <div className="font-medium">{p.name}</div>
                          <div className="text-xs text-text-muted uppercase">{p.connection_type}</div>
                        </div>
                        <span className="text-sm font-mono text-emerald-400">{p.ip_address || p.path}</span>
                      </div>
                    ))}
                  </div>
                </div>
              )}

              {/* ── Invoices ── */}
              {activeTab === 'invoices' && (
                <div className="text-text-muted flex flex-col items-center justify-center h-48">
                  <span className="text-4xl mb-2">🧾</span>
                  <p>{t('settings.invoiceBarcodesPlaceholder')}</p>
                </div>
              )}

            </div>
          )}
        </div>
      </div>
    </div>
  );
}
