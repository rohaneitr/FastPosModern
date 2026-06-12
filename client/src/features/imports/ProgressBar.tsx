import React from 'react';

interface ProgressBarProps {
    total: number;
    processed: number;
    status: 'pending' | 'processing' | 'completed' | 'partial_success' | 'failed';
}

export const ProgressBar: React.FC<ProgressBarProps> = ({ total, processed, status }) => {
    const percentage = total > 0 ? Math.min(Math.round((processed / total) * 100), 100) : 0;
    
    let colorClass = 'bg-blue-500';
    let textClass = 'text-blue-700';
    let bgClass = 'bg-blue-50';
    
    if (status === 'completed') {
        colorClass = 'bg-green-500';
        textClass = 'text-green-700';
        bgClass = 'bg-green-50';
    } else if (status === 'partial_success') {
        colorClass = 'bg-yellow-500';
        textClass = 'text-yellow-700';
        bgClass = 'bg-yellow-50';
    } else if (status === 'failed') {
        colorClass = 'bg-red-500';
        textClass = 'text-red-700';
        bgClass = 'bg-red-50';
    }

    const isProcessing = status === 'processing';

    return (
        <div className={`p-6 rounded-2xl border ${bgClass} border-opacity-50 transition-colors duration-500`}>
            <div className="flex justify-between items-end mb-3">
                <div>
                    <h4 className={`text-sm font-bold uppercase tracking-wider ${textClass}`}>
                        {status === 'processing' ? 'Importing Data...' : 
                         status === 'completed' ? 'Import Complete' : 
                         status === 'partial_success' ? 'Import Finished with Errors' : 'Failed'}
                    </h4>
                    <p className={`text-xs mt-1 font-medium ${textClass} opacity-80`}>
                        Processing: {processed.toLocaleString()} / {total.toLocaleString()} rows
                    </p>
                </div>
                <div className={`text-3xl font-black ${textClass}`}>
                    {percentage}%
                </div>
            </div>
            
            <div className="w-full bg-white/50 rounded-full h-4 overflow-hidden shadow-inner relative">
                <div 
                    className={`h-full ${colorClass} transition-all duration-1000 ease-out relative`}
                    style={{ width: `${percentage}%` }}
                >
                    {isProcessing && (
                        <div className="absolute inset-0 bg-white/20 w-full animate-pulse"></div>
                    )}
                </div>
            </div>
        </div>
    );
};
