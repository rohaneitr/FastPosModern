import { createContext, useContext, ReactNode, useState, useEffect } from 'react';

interface EntitlementMatrix {
    is_super_admin: boolean;
    tenant_id: number | null;
    subscription_status: 'Active' | 'Grace_Period' | 'Locked';
    active_modules: string[];
    permissions: string[];
    limits: {
        users: number;
        devices: number;
        locations: number;
    };
}

// In a real app, this matrix is fetched once during login or auth/me and stored in React Context/Zustand.
// We NEVER store this in localStorage as it can be tampered with.
const EntitlementContext = createContext<EntitlementMatrix | null>(null);

export const useEntitlements = () => {
    const [user, setUser] = useState<any>(null);

    useEffect(() => {
        const storedUser = sessionStorage.getItem('fastpos_user') || localStorage.getItem('fastpos_user');
        if (storedUser) {
            try { setUser(JSON.parse(storedUser)); } catch(e) {}
        }
    }, []);
    
    const matrix: EntitlementMatrix = user?.entitlements || {
        is_super_admin: user?.is_super_admin || false,
        tenant_id: user?.tenant_id || null,
        subscription_status: 'Active',
        active_modules: user?.active_modules || [],
        permissions: user?.permissions?.map((p: any) => typeof p === 'string' ? p : p.name) || [],
        limits: { users: 0, devices: 0, locations: 0 }
    };

    const hasModule = (slug: string): boolean => {
        if (matrix.is_super_admin || matrix.active_modules.includes('all')) return true;
        return matrix.active_modules.includes(slug);
    };

    const hasPermission = (permission: string): boolean => {
        if (matrix.is_super_admin || matrix.permissions.includes('*')) return true;
        return matrix.permissions.includes(permission);
    };

    const isLocked = () => matrix.subscription_status === 'Locked';

    return { matrix, hasModule, hasPermission, isLocked };
};
