import React, { useState } from 'react';

interface ErrorRemediationPanelProps {
    errors: Record<string, string>;
}

export const ErrorRemediationPanel: React.FC<ErrorRemediationPanelProps> = ({ errors }) => {
    const [expanded, setExpanded] = useState(true);
    const errorKeys = Object.keys(errors);

    if (errorKeys.length === 0) return null;

    return (
        <div className="mt-8 bg-white rounded-2xl border border-red-200 shadow-sm overflow-hidden">
            <button 
                onClick={() => setExpanded(!expanded)}
                className="w-full p-4 flex justify-between items-center bg-red-50 hover:bg-red-100 transition-colors border-b border-red-100"
            >
                <div className="flex items-center space-x-3">
                    <div className="bg-red-500 text-white p-1 rounded-full">
                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </div>
                    <h3 className="text-md font-bold text-red-900">
                        Attention Required: {errorKeys.length} Rows Failed Validation
                    </h3>
                </div>
                <svg className={`w-5 h-5 text-red-500 transition-transform ${expanded ? 'rotate-180' : ''}`} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
                </svg>
            </button>

            {expanded && (
                <div className="max-h-96 overflow-y-auto p-4 space-y-3 bg-red-50/30">
                    {errorKeys.map(row => (
                        <div key={row} className="flex items-start p-3 bg-white border-l-4 border-red-500 rounded shadow-sm">
                            <span className="inline-flex items-center justify-center px-2 py-1 mr-3 text-xs font-bold leading-none text-red-100 bg-red-600 rounded">
                                Row {row}
                            </span>
                            <span className="text-sm font-medium text-red-800 break-words">
                                {errors[row]}
                            </span>
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
};
