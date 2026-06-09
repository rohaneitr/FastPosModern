'use client';

import React, { useState, useCallback, useRef, useEffect } from 'react';

// ─── TypeScript Contracts ────────────────────────────────────────────────

export interface Tenant {
    id: number;
    name: string;
    email: string;
    status: 'active' | 'inactive' | 'suspended';
    plan_name: string;
    valid_until: string;
    active_devices: number;
    created_at: string;
}

export interface PaginatedResponse {
    data: Tenant[];
    current_page: number;
    last_page: number;
    total: number;
    per_page: number;
}

interface DestructiveModalProps {
    isOpen: boolean;
    tenant: Tenant | null;
    onClose: () => void;
    onConfirm: (password: string) => void;
    loading: boolean;
}

// ─── Debounce Hook ───────────────────────────────────────────────────────

function useDebounce(value: string, delay: number): string {
    const [debounced, setDebounced] = useState(value);
    useEffect(() => {
        const handler = setTimeout(() => setDebounced(value), delay);
        return () => clearTimeout(handler);
    }, [value, delay]);
    return debounced;
}

// ─── Destructive Confirmation Modal ──────────────────────────────────────

export const DestructiveConfirmationModal: React.FC<DestructiveModalProps> = ({
    isOpen, tenant, onClose, onConfirm, loading
}) => {
    const [confirmName, setConfirmName] = useState('');
    const [password, setPassword] = useState('');

    if (!isOpen || !tenant) return null;

    const isMatch = confirmName.trim() === tenant.name;

    return (
        <div data-testid="destructive-modal" className="fixed inset-0 z-[9999] flex items-center justify-center bg-black/60 backdrop-blur-sm">
            <div className="bg-gray-900 border-2 border-red-500/50 rounded-2xl p-8 w-full max-w-md shadow-2xl shadow-red-500/20">
                <div className="flex items-center gap-3 mb-6">
                    <div className="w-12 h-12 rounded-full bg-red-500/20 flex items-center justify-center">
                        <svg className="w-6 h-6 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2">
                            <path strokeLinecap="round" strokeLinejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" />
                        </svg>
                    </div>
                    <div>
                        <h3 className="text-xl font-black text-red-400">Suspend Tenant</h3>
                        <p className="text-sm text-gray-400">This will immediately lock all operations.</p>
                    </div>
                </div>

                <div className="space-y-4">
                    <div>
                        <label className="block text-sm font-medium text-gray-400 mb-1.5">
                            Type <span className="text-red-400 font-bold">"{tenant.name}"</span> to confirm
                        </label>
                        <input
                            data-testid="confirm-name-input"
                            type="text"
                            value={confirmName}
                            onChange={(e) => setConfirmName(e.target.value)}
                            className="w-full bg-gray-800/80 border border-red-500/30 rounded-xl px-4 py-3 text-white text-sm outline-none focus:border-red-500 transition-colors"
                            placeholder="Exact business name"
                        />
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-gray-400 mb-1.5">Re-authenticate your password</label>
                        <input
                            data-testid="confirm-password-input"
                            type="password"
                            value={password}
                            onChange={(e) => setPassword(e.target.value)}
                            className="w-full bg-gray-800/80 border border-red-500/30 rounded-xl px-4 py-3 text-white text-sm outline-none focus:border-red-500 transition-colors"
                            placeholder="Your admin password"
                        />
                    </div>
                </div>

                <div className="flex gap-3 mt-8">
                    <button
                        onClick={onClose}
                        className="flex-1 py-3 rounded-xl font-bold text-sm text-gray-300 bg-gray-800 hover:bg-gray-700 transition-colors"
                    >
                        Cancel
                    </button>
                    <button
                        data-testid="confirm-suspend-btn"
                        onClick={() => onConfirm(password)}
                        disabled={!isMatch || !password || loading}
                        className={`flex-1 py-3 rounded-xl font-bold text-sm transition-all ${
                            isMatch && password && !loading
                                ? 'bg-red-600 hover:bg-red-700 text-white shadow-lg shadow-red-500/30'
                                : 'bg-gray-700 text-gray-500 cursor-not-allowed'
                        }`}
                    >
                        {loading ? 'Suspending...' : 'Suspend Now'}
                    </button>
                </div>
            </div>
        </div>
    );
};

// ─── Tenant Ledger Table ─────────────────────────────────────────────────

interface TenantLedgerProps {
    fetchTenants: (page: number, search: string) => Promise<PaginatedResponse>;
    onSuspend: (tenant: Tenant, password: string) => Promise<void>;
}

export const TenantLedgerTable: React.FC<TenantLedgerProps> = ({ fetchTenants, onSuspend }) => {
    const [tenants, setTenants] = useState<Tenant[]>([]);
    const [page, setPage] = useState(1);
    const [lastPage, setLastPage] = useState(1);
    const [total, setTotal] = useState(0);
    const [searchInput, setSearchInput] = useState('');
    const [loading, setLoading] = useState(false);
    const [suspendTarget, setSuspendTarget] = useState<Tenant | null>(null);
    const [suspendLoading, setSuspendLoading] = useState(false);

    const debouncedSearch = useDebounce(searchInput, 300);

    const loadTenants = useCallback(async (p: number, s: string) => {
        setLoading(true);
        try {
            const data = await fetchTenants(p, s);
            setTenants(data.data);
            setPage(data.current_page);
            setLastPage(data.last_page);
            setTotal(data.total);
        } catch {
            console.error('Failed to load tenants');
        } finally {
            setLoading(false);
        }
    }, [fetchTenants]);

    useEffect(() => {
        loadTenants(1, debouncedSearch);
    }, [debouncedSearch, loadTenants]);

    const handleSuspendConfirm = async (password: string) => {
        if (!suspendTarget) return;
        setSuspendLoading(true);
        try {
            await onSuspend(suspendTarget, password);
            setSuspendTarget(null);
            loadTenants(page, debouncedSearch);
        } catch {
            console.error('Suspend failed');
        } finally {
            setSuspendLoading(false);
        }
    };

    return (
        <>
            <div className="bg-white/5 backdrop-blur-xl border border-white/10 rounded-2xl overflow-hidden">
                <div className="p-6 border-b border-white/10 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                    <div>
                        <h3 className="text-xl font-black text-white">Tenant Ledger</h3>
                        <p className="text-sm text-gray-400 mt-1">{total} businesses registered</p>
                    </div>
                    <input
                        data-testid="tenant-search-input"
                        type="text"
                        value={searchInput}
                        onChange={(e) => setSearchInput(e.target.value)}
                        className="w-full md:w-80 bg-gray-800/60 border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white outline-none focus:border-indigo-500 transition-colors placeholder:text-gray-500"
                        placeholder="Search tenants by name or email..."
                    />
                </div>

                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b border-white/10 text-left">
                                <th className="px-6 py-4 font-semibold text-gray-400 uppercase tracking-wider text-xs">Business</th>
                                <th className="px-6 py-4 font-semibold text-gray-400 uppercase tracking-wider text-xs">Plan</th>
                                <th className="px-6 py-4 font-semibold text-gray-400 uppercase tracking-wider text-xs">Status</th>
                                <th className="px-6 py-4 font-semibold text-gray-400 uppercase tracking-wider text-xs">Valid Until</th>
                                <th className="px-6 py-4 font-semibold text-gray-400 uppercase tracking-wider text-xs">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {loading ? (
                                <tr><td colSpan={5} className="px-6 py-12 text-center text-gray-500">Loading...</td></tr>
                            ) : tenants.length === 0 ? (
                                <tr><td colSpan={5} className="px-6 py-12 text-center text-gray-500">No tenants found.</td></tr>
                            ) : (
                                tenants.map(tenant => (
                                    <tr key={tenant.id} className="border-b border-white/5 hover:bg-white/5 transition-colors">
                                        <td className="px-6 py-4">
                                            <p className="font-bold text-white">{tenant.name}</p>
                                            <p className="text-xs text-gray-400">{tenant.email}</p>
                                        </td>
                                        <td className="px-6 py-4">
                                            <span className="px-2.5 py-1 rounded-full text-xs font-bold bg-indigo-500/20 text-indigo-400">{tenant.plan_name}</span>
                                        </td>
                                        <td className="px-6 py-4">
                                            <span className={`px-2.5 py-1 rounded-full text-xs font-bold ${
                                                tenant.status === 'active' ? 'bg-emerald-500/20 text-emerald-400' :
                                                tenant.status === 'suspended' ? 'bg-red-500/20 text-red-400' :
                                                'bg-amber-500/20 text-amber-400'
                                            }`}>
                                                {tenant.status.toUpperCase()}
                                            </span>
                                        </td>
                                        <td className="px-6 py-4 text-gray-300">{new Date(tenant.valid_until).toLocaleDateString()}</td>
                                        <td className="px-6 py-4">
                                            <button
                                                data-testid={`suspend-btn-${tenant.id}`}
                                                onClick={() => setSuspendTarget(tenant)}
                                                className="text-xs font-bold text-red-400 hover:text-red-300 bg-red-500/10 hover:bg-red-500/20 px-3 py-1.5 rounded-lg transition-colors"
                                            >
                                                Suspend
                                            </button>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>

                {/* Pagination */}
                <div className="p-4 border-t border-white/10 flex justify-between items-center">
                    <p className="text-xs text-gray-500">Page {page} of {lastPage}</p>
                    <div className="flex gap-2">
                        <button
                            onClick={() => loadTenants(page - 1, debouncedSearch)}
                            disabled={page <= 1}
                            className="px-4 py-2 text-xs font-bold rounded-lg bg-gray-800 text-gray-300 hover:bg-gray-700 disabled:opacity-30 disabled:cursor-not-allowed transition-colors"
                        >
                            Previous
                        </button>
                        <button
                            onClick={() => loadTenants(page + 1, debouncedSearch)}
                            disabled={page >= lastPage}
                            className="px-4 py-2 text-xs font-bold rounded-lg bg-indigo-600 text-white hover:bg-indigo-700 disabled:opacity-30 disabled:cursor-not-allowed transition-colors"
                        >
                            Next
                        </button>
                    </div>
                </div>
            </div>

            <DestructiveConfirmationModal
                isOpen={!!suspendTarget}
                tenant={suspendTarget}
                onClose={() => setSuspendTarget(null)}
                onConfirm={handleSuspendConfirm}
                loading={suspendLoading}
            />
        </>
    );
};
