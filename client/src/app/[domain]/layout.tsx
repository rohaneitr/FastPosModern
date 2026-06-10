import React from 'react';
import { notFound } from 'next/navigation';
import { GlobalAnnouncement } from '@/components/GlobalAnnouncement';

interface TenantConfig {
  id: number;
  name: string;
  subdomain: string;
  custom_domain: string | null;
  is_active: boolean;
  branding: any;
  currency: any;
  language: string;
}

async function getTenantConfig(domain: string): Promise<TenantConfig | null> {
  try {
    // Fix SSR Networking for Docker: Use internal container name and port.
    const res = await fetch(`http://backend:8000/api/v1/tenant/resolve/${domain}`, {
      next: { revalidate: 60 },
      headers: {
        'Accept': 'application/json',
      }
    });


    if (!res.ok) {
      if (res.status === 403) {
        throw new Error('Tenant is suspended or inactive');
      }
      return null;
    }

    const data = await res.json();
    return data.tenant;
  } catch (err: any) {
    if (err.message === 'Tenant is suspended or inactive') {
      throw err;
    }
    console.error('Failed to resolve tenant:', err);
    return null;
  }
}

export default async function TenantLayout({
  children,
  params,
}: {
  children: React.ReactNode;
  params: Promise<{ domain: string }>;
}) {
  const { domain } = await params;

  let tenant: TenantConfig | null = null;

  try {
    tenant = await getTenantConfig(domain);
  } catch (err: any) {
    // Render Suspended State
    return (
      <div className="min-h-screen bg-background flex flex-col items-center justify-center p-4">
        <div className="glass-card max-w-md w-full p-8 rounded-2xl border border-rose-500/30 text-center flex flex-col items-center">
          <div className="w-16 h-16 bg-rose-500/10 text-rose-500 rounded-full flex items-center justify-center mb-6 shadow-[0_0_30px_rgba(244,63,94,0.2)]">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round"><circle cx="12" cy="12" r="10" /><line x1="15" y1="9" x2="9" y2="15" /><line x1="9" y1="9" x2="15" y2="15" /></svg>
          </div>
          <h1 className="text-3xl font-extrabold text-white mb-2">Workspace Suspended</h1>
          <p className="text-text-muted mb-8 text-sm">
            This workspace <span className="text-white font-mono font-bold px-2 py-0.5 bg-surface rounded mx-1">{domain}</span> has been suspended or deactivated. Please contact support or your administrator.
          </p>
          <a href="mailto:support@fastpos.com" className="w-full py-3.5 bg-gradient-to-r from-rose-600 to-rose-500 hover:from-rose-500 hover:to-rose-400 text-white rounded-xl font-bold transition-all shadow-[0_0_20px_rgba(244,63,94,0.4)]">
            Contact Support
          </a>
        </div>
      </div>
    );
  }

  if (!tenant) {
    notFound();
  }

  return (
    <div className="min-h-screen bg-background flex flex-col" data-tenant-id={tenant.id} data-theme-color={tenant.branding?.primary_color}>
      <GlobalAnnouncement />
      <div className="flex-1">
        {children}
      </div>
    </div>
  );
}
