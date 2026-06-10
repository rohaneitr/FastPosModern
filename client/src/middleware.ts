import { NextResponse } from 'next/server';
import type { NextRequest } from 'next/server';

export const config = {
  matcher: [
    // Explicitly catch all routes, including /login, to ensure subdomains are processed
    '/((?!api|_next/static|_next/image|favicon.ico).*)',
  ],
};

export function middleware(req: NextRequest) {
  const url = req.nextUrl.clone();

  // 1. Skip core assets and API routes
  if (
    url.pathname.startsWith('/api') ||
    url.pathname.startsWith('/_next') ||
    url.pathname.startsWith('/static') ||
    url.pathname.includes('.') // Skips files like .png, .js
  ) {
    return NextResponse.next();
  }

  // 2. Extract Hostname Robustly
  // Removes port number if it exists (e.g., localhost:3000 -> localhost)
  const hostHeader = req.headers.get('host') || '';
  const hostname = hostHeader.split(':')[0]; 
  // 3. Define Root Domains
  const envRootDomain = process.env.NEXT_PUBLIC_ROOT_DOMAIN || 'fastpos.com';
  
  const isRootDomain = 
    hostname === 'localhost' || 
    hostname === '127.0.0.1' ||
    hostname === '0.0.0.0' ||
    hostname === envRootDomain ||
    hostname === `www.${envRootDomain}`;

  // 4. Handle SuperAdmin & Root Domain Isolation
  // If we are on the root domain, we do NOT rewrite to /[domain].
  // We serve /login or /superadmin natively from the app/ directory.
  if (isRootDomain) {
    if (url.pathname === '/') {
      return NextResponse.redirect(new URL('/superadmin-login', req.url));
    }

    if (url.pathname === '/login' || url.pathname === '/superadmin/login') {
      url.pathname = '/superadmin-login';
      return NextResponse.rewrite(url);
    }
    
    // Prevent root domain access to /business which causes 404s
    if (url.pathname.startsWith('/business')) {
      return NextResponse.redirect(new URL('/superadmin-login', req.url));
    }

    // ── Super Admin Edge Middleware Shield ─────────────────────────────
    // Intercept all /superadmin/* dashboard routes and verify session token.
    // If session is missing or user lacks SuperAdmin role, hard redirect.
    if (url.pathname.startsWith('/superadmin') && !url.pathname.startsWith('/superadmin-login')) {
      const sessionCookie = req.cookies.get('fastpos_session')?.value;
      const userCookie = req.cookies.get('fastpos_user_role')?.value;

      // If no session cookie exists at all, eject immediately
      if (!sessionCookie) {
        return NextResponse.redirect(new URL('/superadmin-login', req.url));
      }

      // If the role cookie exists and is NOT SuperAdmin, block with redirect
      if (userCookie && userCookie !== 'SuperAdmin') {
        return NextResponse.redirect(new URL('/superadmin-login?reason=forbidden', req.url));
      }
    }

    return NextResponse.next();
  }

  // 5. If we reach here, we are on a SUBDOMAIN.
  // We must protect /superadmin from being accessed on a tenant subdomain.
  if (url.pathname.startsWith('/superadmin')) {
    return NextResponse.redirect(new URL('/', req.url));
  }

  // ── Tenant Edge Middleware Shield ────────────────────────────────
  // Protect tenant dashboards from unauthenticated access.
  const isTenantDashboardRoute = 
    url.pathname.startsWith('/tenant') || 
    url.pathname.startsWith('/business') || 
    url.pathname.startsWith('/settings');

  if (isTenantDashboardRoute) {
    const sessionCookie = req.cookies.get('fastpos_session')?.value;
    const roleCookie = req.cookies.get('fastpos_user_role')?.value;
    
    if (!sessionCookie) {
      return NextResponse.redirect(new URL('/login', req.url));
    }
    
    // Check SaaS Billing Status (Iron Gate)
    const businessStatus = req.cookies.get('fastpos_business_status')?.value;
    if (businessStatus === 'suspended' && !url.pathname.includes('/billing/suspended') && !url.pathname.startsWith('/api')) {
      return NextResponse.redirect(new URL('/business/billing/suspended', req.url));
    }
    
    // Block cashiers from accessing backoffice routes
    if (roleCookie === 'Cashier' && url.pathname.startsWith('/business')) {
      return NextResponse.redirect(new URL('/terminal', req.url));
    }
  }

  // 6. Extract Tenant Domain Strictly
  let tenantDomain = hostname;
  if (hostname.endsWith('.localhost')) {
    tenantDomain = hostname.replace('.localhost', '');
  } else if (hostname.endsWith('.127.0.0.1')) {
    tenantDomain = hostname.replace('.127.0.0.1', '');
  } else if (hostname.endsWith(`.${envRootDomain}`)) {
    tenantDomain = hostname.replace(`.${envRootDomain}`, '');
  }

  // 7. Execute the Rewrite!
  // Example: tenant1.localhost:3000/login -> /tenant1/login
  url.pathname = `/${tenantDomain}${url.pathname}`;
  return NextResponse.rewrite(url);
}
