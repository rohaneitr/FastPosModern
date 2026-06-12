"use client";

import React, { useState } from 'react';
import { ProfitAndLossView } from '@/features/finance/ProfitAndLossView';
import { BalanceSheetView } from '@/features/finance/BalanceSheetView';

export default function AccountingReportsPage() {
    const [activeTab, setActiveTab] = useState<'pnl' | 'balance_sheet'>('pnl');

    return (
        <div className="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
            <div className="mb-8">
                <h1 className="text-3xl font-extrabold text-gray-900 tracking-tight">Financial Reports</h1>
                <p className="mt-2 text-sm text-gray-500 max-w-2xl">
                    View real-time, mathematically balanced financial statements derived directly from the system's core double-entry ledger.
                </p>
            </div>

            {/* Tabs */}
            <div className="mb-6 border-b border-gray-200">
                <nav className="-mb-px flex space-x-8" aria-label="Tabs">
                    <button
                        onClick={() => setActiveTab('pnl')}
                        className={`
                            whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors
                            ${activeTab === 'pnl'
                                ? 'border-blue-500 text-blue-600'
                                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                            }
                        `}
                    >
                        Profit & Loss Statement
                    </button>
                    <button
                        onClick={() => setActiveTab('balance_sheet')}
                        className={`
                            whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors
                            ${activeTab === 'balance_sheet'
                                ? 'border-blue-500 text-blue-600'
                                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                            }
                        `}
                    >
                        Balance Sheet
                    </button>
                </nav>
            </div>

            {/* Dynamic Content Rendering */}
            <div className="mt-4">
                {activeTab === 'pnl' && <ProfitAndLossView />}
                {activeTab === 'balance_sheet' && <BalanceSheetView />}
            </div>
        </div>
    );
}
