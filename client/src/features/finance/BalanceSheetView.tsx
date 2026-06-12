import React, { useEffect, useState } from 'react';
import { getBalanceSheet } from '../../lib/api/accounting';
import { BalanceSheetReport, AccountEntry } from '../../types/accounting';
import { AsOfDatePicker } from './AsOfDatePicker';
import { ReportSkeleton, AccessDeniedSkeleton } from './ReportSkeleton';
import { ExportActionBar } from './ExportActionBar';
import { exportToCSV } from '../../utils/exportUtils';
import toast from 'react-hot-toast';

export const BalanceSheetView: React.FC = () => {
    const [data, setData] = useState<BalanceSheetReport | null>(null);
    const [loading, setLoading] = useState<boolean>(true);
    const [forbidden, setForbidden] = useState<boolean>(false);
    
    const today = new Date().toISOString().split('T')[0];
    const [asOfDate, setAsOfDate] = useState<string>(today);

    const fetchData = async () => {
        setLoading(true);
        try {
            const report = await getBalanceSheet(asOfDate);
            setData(report);
            setForbidden(false);
        } catch (error: any) {
            if (error.response?.status === 403) {
                setForbidden(true);
                toast.error("Access Denied: BusinessAdmin role required.");
            } else {
                toast.error("Failed to load Balance Sheet.");
            }
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        fetchData();
    }, [asOfDate]);

    const handleExportCSV = () => {
        if (!data) return;
        
        // Flatten the breakdown for CSV
        const rows = [
            ...data.breakdown.asset_accounts.map(acc => ({ Category: 'Asset', Code: acc.code, Account: acc.account_name, Balance: acc.balance })),
            ...data.breakdown.liability_accounts.map(acc => ({ Category: 'Liability', Code: acc.code, Account: acc.account_name, Balance: acc.balance })),
            ...data.breakdown.equity_accounts.map(acc => ({ Category: 'Equity', Code: acc.code, Account: acc.account_name, Balance: acc.balance })),
            { Category: 'SUMMARY', Code: 'SYS', Account: 'RETAINED EARNINGS (NET PROFIT)', Balance: data.totals.retained_earnings },
            { Category: 'SUMMARY', Code: '-', Account: 'TOTAL ASSETS', Balance: data.totals.assets },
            { Category: 'SUMMARY', Code: '-', Account: 'TOTAL LIABILITIES', Balance: data.totals.liabilities },
            { Category: 'SUMMARY', Code: '-', Account: 'TOTAL EQUITY', Balance: data.totals.total_equity },
            { Category: 'SUMMARY', Code: '-', Account: 'LIABILITIES & EQUITY', Balance: data.totals.liabilities_and_equity },
        ];
        exportToCSV('Balance_Sheet', rows);
    };

    if (forbidden) return <AccessDeniedSkeleton />;

    return (
        <div className="printable-section">
            <div className="print:hidden">
                <ExportActionBar title="Balance Sheet" onExportCSV={handleExportCSV} />
                <AsOfDatePicker 
                    asOfDate={asOfDate} 
                    onChange={setAsOfDate} 
                />
            </div>

            {loading || !data ? (
                <div className="print:hidden"><ReportSkeleton /></div>
            ) : (
                <div className="bg-white/90 backdrop-blur-md rounded-2xl shadow-sm border border-gray-100 overflow-hidden print:shadow-none print:border-none mt-6">
                    <div className="p-6 border-b border-gray-100 bg-blue-50/30 print:bg-white print:border-b-2 print:border-black">
                        <h2 className="text-2xl font-extrabold text-gray-900 tracking-tight">Balance Sheet</h2>
                        <p className="text-sm text-gray-500 mt-1 font-medium">Snapshot As Of: <span className="font-bold text-gray-700 print:text-black">{asOfDate}</span></p>
                    </div>

                    {/* Check Equilibrium */}
                    {parseFloat(data.totals.assets) !== parseFloat(data.totals.liabilities_and_equity) && (
                        <div className="bg-red-50 p-4 border-b border-red-200">
                            <p className="text-sm text-red-700 font-bold">⚠️ Critical Error: Ledger is Imbalanced</p>
                            <p className="text-xs text-red-600 mt-1">Assets (${data.totals.assets}) != Liabilities + Equity (${data.totals.liabilities_and_equity})</p>
                        </div>
                    )}

                    <div className="grid grid-cols-1 md:grid-cols-2 gap-8 p-8 print:p-4">
                        {/* Left Side: Assets */}
                        <div>
                            <AccountTable title="Assets" accounts={data.breakdown.asset_accounts} total={data.totals.assets} isPrimary />
                        </div>

                        {/* Right Side: Liabilities & Equity */}
                        <div className="space-y-10">
                            <AccountTable title="Liabilities" accounts={data.breakdown.liability_accounts} total={data.totals.liabilities} />
                            
                            <div className="break-inside-avoid">
                                <h3 className="text-lg font-bold text-gray-900 mb-4 pb-2 border-b-2 border-gray-100 print:border-black">Equity</h3>
                                <table className="min-w-full divide-y divide-gray-200 print:divide-black">
                                    <tbody className="divide-y divide-gray-100 print:divide-gray-400">
                                        {data.breakdown.equity_accounts.map(acc => (
                                            <tr key={acc.code} className="hover:bg-blue-50/30 transition-colors">
                                                <td className="py-3 px-2 text-sm text-gray-500 w-24 font-mono">{acc.code}</td>
                                                <td className="py-3 px-2 text-sm font-semibold text-gray-700">{acc.account_name}</td>
                                                <td className="py-3 px-2 text-sm font-medium text-gray-900 text-right">${acc.balance}</td>
                                            </tr>
                                        ))}
                                        <tr className="bg-blue-50/50 print:bg-white print:border-t print:border-dashed print:border-gray-400">
                                            <td className="py-3 px-2 text-sm text-gray-500 font-mono">SYS</td>
                                            <td className="py-3 px-2 text-sm font-semibold text-gray-900">Retained Earnings (Net Profit)</td>
                                            <td className="py-3 px-2 text-sm font-bold text-blue-700 text-right print:text-black">${data.totals.retained_earnings}</td>
                                        </tr>
                                        <tr className="bg-gray-50/50 print:bg-white">
                                            <td className="py-4 px-2 text-sm font-bold text-gray-900 uppercase tracking-wider" colSpan={2}>Total Equity</td>
                                            <td className="py-4 px-2 text-base font-bold text-gray-900 text-right border-t-2 border-gray-300 print:border-black">${data.totals.total_equity}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            {/* Grand Total */}
                            <div className="mt-8 pt-4 border-t-4 border-double border-gray-300 flex justify-between items-center print:border-black print:border-t-2">
                                <span className="font-bold text-gray-900 text-lg uppercase tracking-wide">Total Liabilities & Equity</span>
                                <span className="font-black text-blue-700 text-2xl print:text-black">${data.totals.liabilities_and_equity}</span>
                            </div>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
};

const AccountTable = ({ title, accounts, total, isPrimary }: { title: string, accounts: AccountEntry[], total: string, isPrimary?: boolean }) => (
    <div className="break-inside-avoid">
        <h3 className="text-lg font-bold text-gray-900 mb-4 pb-2 border-b-2 border-gray-100 print:border-black">{title}</h3>
        {accounts.length === 0 ? (
            <p className="text-sm text-gray-500 italic bg-gray-50 p-4 rounded-lg print:bg-transparent">No accounts found.</p>
        ) : (
            <table className="min-w-full divide-y divide-gray-200 print:divide-black">
                <tbody className="divide-y divide-gray-100 print:divide-gray-400">
                    {accounts.map(acc => (
                        <tr key={acc.code} className="hover:bg-blue-50/30 transition-colors">
                            <td className="py-3 px-2 text-sm text-gray-500 w-24 font-mono">{acc.code}</td>
                            <td className="py-3 px-2 text-sm font-semibold text-gray-700">{acc.account_name}</td>
                            <td className="py-3 px-2 text-sm font-medium text-gray-900 text-right">${acc.balance}</td>
                        </tr>
                    ))}
                    <tr className="bg-gray-50/50 print:bg-white">
                        <td className="py-4 px-2 text-sm font-bold text-gray-900 uppercase tracking-wider" colSpan={2}>Total {title}</td>
                        <td className="py-4 px-2 text-base font-bold text-gray-900 text-right border-t-2 border-gray-300 print:border-black">${total}</td>
                    </tr>
                </tbody>
            </table>
        )}
        {isPrimary && (
            <div className="mt-8 pt-4 border-t-4 border-double border-gray-300 flex justify-between items-center print:border-black print:border-t-2">
                <span className="font-bold text-gray-900 text-lg uppercase tracking-wide">Total {title}</span>
                <span className="font-black text-blue-700 text-2xl print:text-black">${total}</span>
            </div>
        )}
    </div>
);
