'use client';

import React from 'react';
import Link from 'next/link';
import { usePathname } from 'next/navigation';
import { cn } from '@/lib/utils';
import { ChevronRight, Home } from 'lucide-react';

/** Route segment display name overrides */
const segmentNames: Record<string, string> = {
  business: 'Dashboard',
  user: 'User Portal',
  superadmin: 'SuperAdmin',
  sales: 'Sales & Invoices',
  products: 'Products',
  inventory: 'Inventory',
  contacts: 'Contacts & CRM',
  categories: 'Catalog Settings',
  accounting: 'Accounting',
  reports: 'Reports',
  settings: 'Settings',
  purchases: 'Purchases',
  hr: 'HR',
  employees: 'Employees',
  payroll: 'Payroll',
  pharmacy: 'Pharmacy',
  warranty: 'Warranty & RMA',
  billing: 'Subscription',
  quotations: 'Quotations',
  pos: 'POS Terminal',
  profile: 'Profile',
  users: 'Users & Roles',
  transfers: 'Stock Transfers',
  support: 'Support',
};

export function Breadcrumb({ className }: { className?: string }) {
  const pathname = usePathname();
  
  // Remove the [domain] segment (e.g., "/tech/business/sales" → ["business", "sales"])
  const segments = pathname
    .split('/')
    .filter(Boolean)
    .slice(1); // Skip the domain segment

  if (segments.length <= 1) return null;

  return (
    <nav className={cn('flex items-center gap-1.5 text-sm', className)} aria-label="Breadcrumb">
      <Link
        href={`/${pathname.split('/')[1]}/${segments[0]}`}
        className="text-text-muted hover:text-white transition-colors"
      >
        <Home className="w-3.5 h-3.5" />
      </Link>
      {segments.map((segment, i) => {
        const href = `/${pathname.split('/')[1]}/${segments.slice(0, i + 1).join('/')}`;
        const isLast = i === segments.length - 1;
        const displayName = segmentNames[segment] || segment.charAt(0).toUpperCase() + segment.slice(1);

        return (
          <React.Fragment key={segment + i}>
            <ChevronRight className="w-3 h-3 text-text-muted/50" />
            {isLast ? (
              <span className="text-white font-medium">{displayName}</span>
            ) : (
              <Link href={href} className="text-text-muted hover:text-white transition-colors">
                {displayName}
              </Link>
            )}
          </React.Fragment>
        );
      })}
    </nav>
  );
}
