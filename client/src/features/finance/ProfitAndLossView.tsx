import React, { useEffect, useState } from 'react';
import { getProfitAndLoss } from '../../lib/api/accounting';
import { ProfitAndLossReport, AccountEntry } from '../../types/accounting';
import { DateRangePicker } from './DateRangePicker';
import { ReportSkeleton, AccessDeniedSkeleton } from './ReportSkeleton';
import { ExportActionBar } from './ExportActionBar';
import { AccountingVisualizer } from './AccountingVisualizer';
import { exportToCSV } from '../../utils/exportUtils';
import toast from 'react-hot-toast';

export const ProfitAndLossView: React.FC = () => {
    const [data, setData] = useState<ProfitAndLossReport | null>(null);
    const [loading, setLoading] = useState<boolean>(true);
    const [forbidden, setForbidden] = useState<boolean>(false);
    
    const today = new Date();
    const firstDay = new Date(today.getFullYear(), today.getMonth(), 1).toISOString().split('T')[0];
    const lastDay = new Date(today.getFullYear(), today.getMonth() + 1, 0).toISOString().split('T')[0];

    const [startDate, setStartDate] = useState<string>(firstDay);
    const [endDate, setEndDate] = useState<string>(lastDay);

    const fetchData = async () => {
        setLoading(true);
        try {
            const report = await getProfitAndLoss(startDate, endDate);
            setData(report);
            setForbidden(false);
        } catch (error: any) {
            if (error.response?.status === 403) {
                setForbidden(true);
                toast.error("Access Denied: BusinessAdmin role required.");
            } else {
                toast.error("Failed to load Profit & Loss report.");
            }
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        fetchData();
    }, [startDate, endDate]);

    const handleExportCSV = () => {
        if (!data) return;
        
        // Flatten the breakdown for CSV
        const rows = [
            ...data.breakdown.revenue_accounts.map(acc => ({ Category: 'Revenue', Code: acc.code, Account: acc.account_name, Balance: acc.balance })),
            ...data.breakdown.cogs_accounts.map(acc => ({ Category: 'COGS', Code: acc.code, Account: acc.account_name, Balance: acc.balance })),
            ...data.breakdown.expense_accounts.map(acc => ({ Category: 'Operating Expense', Code: acc.code, Account: acc.account_name, Balance: acc.balance })),
            { Category: 'SUMMARY', Code: '-', Account: 'TOTAL REVENUE', Balance: data.totals.revenue },
            { Category: 'SUMMARY', Code: '-', Account: 'TOTAL COGS', Balance: data.totals.cogs },
            { Category: 'SUMMARY', Code: '-', Account: 'GROSS PROFIT', Balance: data.totals.gross_profit },
            { Category: 'SUMMARY', Code: '-', Account: 'TOTAL EXPENSES', Balance: data.totals.operating_expenses },
            { Category: 'SUMMARY', Code: '-', Account: 'NET PROFIT', Balance: data.totals.net_profit },
        ];
        exportToCSV('Profit_And_Loss', rows);
    };

    if (forbidden) return <AccessDeniedSkeleton />;

    return (
        <div className="printable-section">
            <div className="print:hidden">
                <ExportActionBar title="Profit & Loss" onExportCSV={handleExportCSV} />
                <DateRangePicker 
                    startDate={startDate} 
                    endDate={endDate} 
                    onChangeStart={setStartDate} 
                    onChangeEnd={setEndDate} 
                />
            </div>

            {loading || !data ? (
                <div className="print:hidden"><ReportSkeleton /></div>
            ) : (
                <div className="space-y-6">
                    <AccountingVisualizer 
                        revenue={data.totals.revenue} 
                        cogs={data.totals.cogs} 
                        grossProfit={data.totals.gross_profit} 
                        netProfit={data.totals.net_profit} 
                    />

                    <div className="bg-white/90 backdrop-blur-md rounded-2xl shadow-sm border border-gray-100 overflow-hidden print:shadow-none print:border-none">
                        <div className="p-6 border-b border-gray-100 bg-gradient-to-r from-gray-50 to-white print:bg-white print:border-b-2 print:border-black">
                            <h2 className="text-2xl font-extrabold text-gray-900 tracking-tight">Income Statement (P&L)</h2>
                            <p className="text-sm text-gray-500 mt-1 font-medium">Reporting Period: {startDate} to {endDate}</p>
                        </div>

                        <div className="p-0">
                            {/* Summary Cards */}
                            <div className="grid grid-cols-2 md:grid-cols-4 gap-px bg-gray-100/50 print:grid-cols-4 print:bg-white print:gap-4 print:border-b-2 print:border-black">
                                <div className="bg-white p-6 print:p-2">
                                    <p className="text-sm text-gray-500 font-semibold uppercase tracking-wider">Total Revenue</p>
                                    <p className="text-2xl font-extrabold text-gray-900 mt-2">${data.totals.revenue}</p>
                                </div>
                                <div className="bg-white p-6 print:p-2">
                                    <p className="text-sm text-gray-500 font-semibold uppercase tracking-wider">COGS</p>
                                    <p className="text-2xl font-extrabold text-orange-600 mt-2">-${data.totals.cogs}</p>
                                </div>
                                <div className="bg-white p-6 print:p-2">
                                    <p className="text-sm text-gray-500 font-semibold uppercase tracking-wider">Gross Profit</p>
                                    <p className="text-2xl font-extrabold text-blue-600 mt-2">${data.totals.gross_profit}</p>
                                </div>
                                <div className={`bg-white p-6 print:p-2 ${parseFloat(data.totals.net_profit) < 0 ? 'bg-red-50/50' : ''}`}>
                                    <p className="text-sm text-gray-500 font-semibold uppercase tracking-wider">Net Profit</p>
                                    <p className={`text-3xl font-black mt-2 tracking-tight ${parseFloat(data.totals.net_profit) >= 0 ? 'text-emerald-600' : 'text-red-600'}`}>
                                        ${data.totals.net_profit}
                                    </p>
                                </div>
                            </div>

                            {/* Breakdown Tables */}
                            <div className="p-8 space-y-10 print:p-4">
                                <AccountTable title="Operating Expenses" accounts={data.breakdown.expense_accounts} total={data.totals.operating_expenses} />
                            </div>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
};

const AccountTable = ({ title, accounts, total }: { title: string, accounts: AccountEntry[], total: string }) => (
    <div className="break-inside-avoid">
        <h3 className="text-lg font-bold text-gray-900 mb-4 pb-2 border-b-2 border-gray-100 print:border-black">{title}</h3>
        {accounts.length === 0 ? (
            <p className="text-sm text-gray-500 italic bg-gray-50 p-4 rounded-lg print:bg-transparent">No activity for this period.</p>
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
    </div>
);
