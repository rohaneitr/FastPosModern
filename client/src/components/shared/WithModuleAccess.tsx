import React from 'react';
import { useAuth } from '@/contexts/AuthContext';

interface WithModuleAccessProps {
    slug: string;
    children: React.ReactNode;
    fallback?: React.ReactNode;
}

export function WithModuleAccess({ slug, children, fallback = null }: WithModuleAccessProps) {
    const { user } = useAuth();
    
    // We assume the active_modules is parsed into user.business.active_modules
    const activeModules = (user as any)?.business?.active_modules || [];
    
    // Physical short-circuit (Prevents rendering in Virtual DOM entirely)
    if (!activeModules.includes(slug)) {
        return <>{fallback}</>;
    }
    
    return <>{children}</>;
}
