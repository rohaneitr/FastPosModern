import React from 'react';

interface VisualizerProps {
    revenue: string;
    cogs: string;
    grossProfit: string;
    netProfit: string;
}

export const AccountingVisualizer: React.FC<VisualizerProps> = ({ revenue, cogs, grossProfit, netProfit }) => {
    const revVal = parseFloat(revenue) || 0;
    const cogsVal = parseFloat(cogs) || 0;
    const grossVal = parseFloat(grossProfit) || 0;
    const netVal = parseFloat(netProfit) || 0;

    // To properly scale the bars, we find the maximum absolute value
    const maxVal = Math.max(Math.abs(revVal), Math.abs(cogsVal), Math.abs(grossVal), Math.abs(netVal));
    const baseline = maxVal > 0 ? maxVal : 1; // Prevent division by zero

    const calculateWidth = (val: number) => {
        return Math.max((Math.abs(val) / baseline) * 100, 1); // Minimum 1% width for visibility
    };

    const renderBar = (label: string, value: number, baseColor: string, isNegative: boolean) => {
        const widthPercent = calculateWidth(value);
        const colorClass = isNegative ? 'bg-red-500' : baseColor;
        const textClass = isNegative ? 'text-red-700 font-bold' : 'text-gray-900 font-bold';
        
        return (
            <div className="flex flex-col sm:flex-row items-start sm:items-center py-3 group">
                <div className="w-full sm:w-32 text-xs font-semibold uppercase tracking-wider text-gray-500 mb-2 sm:mb-0">
                    {label}
                </div>
                <div className="flex-1 w-full flex items-center pr-4">
                    <div className="flex-1 bg-gray-100 rounded-full h-4 overflow-hidden shadow-inner relative">
                        <div 
                            className={`h-full ${colorClass} transition-all duration-1000 ease-in-out`} 
                            style={{ width: `${widthPercent}%` }}
                        />
                    </div>
                </div>
                <div className={`w-32 text-right ${textClass}`}>
                    ${value.toFixed(4)}
                </div>
            </div>
        );
    };

    return (
        <div className="bg-white/80 backdrop-blur-xl border border-gray-100 rounded-2xl p-6 shadow-sm mb-8 print:hidden">
            <div className="flex justify-between items-center mb-6">
                <h3 className="text-lg font-extrabold text-gray-900 tracking-tight">Performance Visualizer</h3>
                <span className="px-3 py-1 bg-blue-50 text-blue-700 text-xs font-bold rounded-full">Graphical View</span>
            </div>
            
            <div className="space-y-2">
                {renderBar("Total Revenue", revVal, "bg-green-500", false)}
                {renderBar("Cost of Goods", cogsVal, "bg-orange-400", false)}
                {renderBar("Gross Profit", grossVal, "bg-blue-500", grossVal < 0)}
                {renderBar("Net Profit", netVal, "bg-emerald-500", netVal < 0)}
            </div>
        </div>
    );
};
