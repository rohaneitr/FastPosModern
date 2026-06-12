import React from 'react';

interface ExportActionBarProps {
    title: string;
    onExportCSV: () => void;
}

export const ExportActionBar: React.FC<ExportActionBarProps> = ({ title, onExportCSV }) => {
    
    const handlePrintPDF = () => {
        window.print();
    };

    return (
        <div className="flex flex-col sm:flex-row justify-between items-center bg-white/80 backdrop-blur-md p-4 rounded-xl shadow-sm border border-gray-100 mb-6 print:hidden">
            <div>
                <h3 className="text-sm font-bold text-gray-800 uppercase tracking-wide">Export & Audit Options</h3>
                <p className="text-xs text-gray-500 mt-1">Generate print-ready PDFs or precise tabular CSVs.</p>
            </div>
            
            <div className="flex space-x-3 mt-4 sm:mt-0">
                <button
                    onClick={onExportCSV}
                    className="flex items-center px-4 py-2 bg-green-50 text-green-700 hover:bg-green-100 border border-green-200 rounded-lg text-sm font-medium transition-colors focus:ring-2 focus:ring-green-500 focus:outline-none shadow-sm"
                >
                    <svg className="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    Export to Excel (CSV)
                </button>
                
                <button
                    onClick={handlePrintPDF}
                    className="flex items-center px-4 py-2 bg-blue-600 text-white hover:bg-blue-700 border border-transparent rounded-lg text-sm font-medium transition-colors focus:ring-2 focus:ring-blue-500 focus:outline-none shadow-sm"
                >
                    <svg className="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                    </svg>
                    Export to PDF
                </button>
            </div>
        </div>
    );
};
