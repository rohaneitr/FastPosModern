import React, { useEffect, useState, useMemo } from 'react';
import { cashControlApi } from './api';
import { useCashControl } from './RegisterSessionProvider';
import Decimal from 'decimal.js';

const DENOMINATIONS = [
    { value: new Decimal('1000'), label: '1000' },
    { value: new Decimal('500'), label: '500' },
    { value: new Decimal('100'), label: '100' },
    { value: new Decimal('50'), label: '50' },
    { value: new Decimal('20'), label: '20' },
    { value: new Decimal('10'), label: '10' },
    { value: new Decimal('5'), label: '5' },
    { value: new Decimal('1'), label: '1' },
    { value: new Decimal('0.25'), label: '0.25' },
    { value: new Decimal('0.10'), label: '0.10' },
    { value: new Decimal('0.05'), label: '0.05' },
    { value: new Decimal('0.01'), label: '0.01' },
];

export function ZReportClosureModal() {
    const { refreshStatus } = useCashControl();
    const [counts, setCounts] = useState<Record<string, string>>({});
    const [isSubmitting, setIsSubmitting] = useState(false);

    // Ensure session is suspended when modal opens
    useEffect(() => {
        let isMounted = true;
        const suspend = async () => {
            try {
                await cashControlApi.suspendRegisterSession();
            } catch (err) {
            }
        };
        suspend();
        return () => { isMounted = false; };
    }, []);

    const handleCountChange = (valueStr: string, count: string) => {
        setCounts(prev => ({ ...prev, [valueStr]: count }));
    };

    const totalCounted = useMemo(() => {
        return DENOMINATIONS.reduce((sum, denom) => {
            const countStr = counts[denom.value.toString()];
            if (!countStr) return sum;
            try {
                const count = new Decimal(countStr);
                if (count.isNaN() || count.isNegative()) return sum;
                return sum.plus(denom.value.times(count));
            } catch {
                return sum;
            }
        }, new Decimal('0'));
    }, [counts]);

    const handleSubmit = async () => {
        setIsSubmitting(true);
        try {
            await cashControlApi.closeRegisterSession(totalCounted.toString());
            await refreshStatus();
        } catch (error) {
            setIsSubmitting(false);
        }
    };

    return (
        <div className="z-report-modal fixed inset-0 z-50 flex items-center justify-center bg-gray-900/80 backdrop-blur-md">
            <div className="relative w-full max-w-2xl overflow-hidden rounded-2xl border border-white/10 bg-white shadow-2xl dark:bg-gray-900">
                <div className="border-b border-gray-200 bg-gray-50 px-6 py-4 dark:border-white/10 dark:bg-white/5">
                    <h2 className="text-xl font-bold text-gray-900 dark:text-white">Z-Report: Physical Cash Count</h2>
                    <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">Enter physical note quantities to compute total cash in drawer.</p>
                </div>
                
                <div className="grid grid-cols-2 gap-4 p-6 sm:grid-cols-3">
                    {DENOMINATIONS.map((denom) => {
                        const valStr = denom.value.toString();
                        return (
                            <div key={valStr} className="flex flex-col">
                                <label className="mb-1 text-xs font-medium text-gray-700 dark:text-gray-300">
                                    ${denom.label} Notes
                                </label>
                                <input
                                    type="number"
                                    min="0"
                                    step="1"
                                    placeholder="0"
                                    value={counts[valStr] || ''}
                                    onChange={(e) => handleCountChange(valStr, e.target.value)}
                                    className="block w-full rounded-md border-0 bg-gray-50 py-2 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6 dark:bg-white/5 dark:text-white dark:ring-white/10 dark:focus:ring-indigo-500"
                                />
                            </div>
                        );
                    })}
                </div>

                <div className="border-t border-gray-200 bg-gray-50 px-6 py-4 flex items-center justify-between dark:border-white/10 dark:bg-white/5">
                    <div>
                        <span className="text-sm font-medium text-gray-500 dark:text-gray-400">Total Counted</span>
                        <div className="text-2xl font-bold text-indigo-600 dark:text-indigo-400">
                            ${totalCounted.toFixed(2)}
                        </div>
                    </div>
                    <button
                        onClick={handleSubmit}
                        disabled={isSubmitting}
                        className="rounded-md bg-indigo-600 px-6 py-3 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 disabled:opacity-50"
                    >
                        {isSubmitting ? 'Closing Register...' : 'Finalize Close'}
                    </button>
                </div>
            </div>
        </div>
    );
}
