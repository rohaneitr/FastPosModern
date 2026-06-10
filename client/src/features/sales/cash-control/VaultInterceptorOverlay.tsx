import React, { useState } from 'react';
import { cashControlApi } from './api';
import { useCashControl } from './RegisterSessionProvider';

export function VaultInterceptorOverlay() {
    const [balance, setBalance] = useState('');
    const [isSubmitting, setIsSubmitting] = useState(false);
    const { refreshStatus } = useCashControl();

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setIsSubmitting(true);
        try {
            await cashControlApi.openRegisterSession(balance || '0');
            await refreshStatus();
        } catch (error) {
            setIsSubmitting(false);
        }
    };

    return (
        <div className="vault-overlay fixed inset-0 z-50 flex items-center justify-center bg-gray-900/60 backdrop-blur-md">
            <div className="relative w-full max-w-md overflow-hidden rounded-2xl border border-white/10 bg-white/5 p-8 shadow-2xl backdrop-blur-xl dark:bg-black/40">
                <div className="mb-6 text-center">
                    <h2 className="text-2xl font-bold tracking-tight text-white">Drawer Locked</h2>
                    <p className="mt-2 text-sm text-gray-300">Enter opening balance to unlock terminal</p>
                </div>
                
                <form onSubmit={handleSubmit} className="space-y-6">
                    <div>
                        <div className="relative mt-1 rounded-md shadow-sm">
                            <div className="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4">
                                <span className="text-gray-400 sm:text-xl">$</span>
                            </div>
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                value={balance}
                                onChange={(e) => setBalance(e.target.value)}
                                className="block w-full rounded-xl border-0 bg-white/10 py-4 pl-10 pr-4 text-2xl text-white ring-1 ring-inset ring-white/20 transition placeholder:text-gray-400 focus:bg-white/20 focus:ring-2 focus:ring-inset focus:ring-indigo-500"
                                placeholder="0.00"
                                required
                            />
                        </div>
                    </div>

                    <button
                        type="submit"
                        disabled={isSubmitting}
                        className="w-full rounded-xl bg-indigo-600 py-3.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 disabled:opacity-50"
                    >
                        {isSubmitting ? 'Unlocking...' : 'Unlock Drawer'}
                    </button>
                </form>
            </div>
        </div>
    );
}
