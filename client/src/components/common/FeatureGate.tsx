import React, { ReactNode } from 'react';
import { useEntitlements } from '@/hooks/useEntitlements';
import { useRouter } from 'next/navigation';

interface FeatureGateProps {
    module?: string;
    permission?: string;
    children: ReactNode;
    fallback?: ReactNode; // Optional component to render if denied (e.g., "Upgrade to unlock")
    isRoute?: boolean; // If true, automatically redirects rather than just hiding UI
}

export const FeatureGate: React.FC<FeatureGateProps> = ({ 
    module, 
    permission, 
    children, 
    fallback = null,
    isRoute = false 
}) => {
    const { hasModule, hasPermission, isLocked } = useEntitlements();
    const router = useRouter();

    // 1. Hard Subscription Enforcement
    if (isLocked()) {
        if (isRoute) {
            router.push('/dashboard/subscription/suspended');
            return null;
        }
        return <div className="p-4 bg-red-50 text-red-600 rounded">Account Suspended. Please renew.</div>;
    }

    // 2. Module Level Gate
    if (module && !hasModule(module)) {
        if (isRoute) {
            // Force redirect to upgrade page instead of 404
            router.push(`/dashboard/subscription/upgrade?required_module=${module}`);
            return null;
        }
        return <>{fallback}</>;
    }

    // 3. Granular RBAC Gate (Spatie)
    if (permission && !hasPermission(permission)) {
        if (isRoute) {
            router.push('/dashboard/unauthorized');
            return null;
        }
        return <>{fallback}</>;
    }

    // User is fully entitled
    return <>{children}</>;
};
