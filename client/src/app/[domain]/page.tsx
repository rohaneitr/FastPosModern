import { redirect } from 'next/navigation';

export default async function TenantIndexPage({ params }: { params: Promise<{ domain: string }> }) {
  await params;
  // Redirect root of tenant domain to their specific login page
  redirect('/login');
}
