'use client';

import React from 'react';

interface ReceiptPreviewModalProps {
    isOpen: boolean;
    onClose: () => void;
    receiptText: string;
}

export function ReceiptPreviewModal({ isOpen, onClose, receiptText }: ReceiptPreviewModalProps) {
    if (!isOpen) return null;

    const handlePrint = () => {
        // Trigger native browser print which will be hijacked by the @media print CSS rules
        window.print();
    };

    return (
        <div className="fixed inset-0 z-[100] flex items-center justify-center bg-gray-900/60 backdrop-blur-md">
            {/* 
                We inject a global print style dynamically or rely on the main CSS. 
                This block isolates the receipt to exactly 80mm bounds during native printing.
            */}
            <style dangerouslySetInnerHTML={{__html: `
                @media print {
                    @page { margin: 0; size: 80mm auto; }
                    body * { visibility: hidden; }
                    .thermal-print-zone, .thermal-print-zone * { visibility: visible; }
                    .thermal-print-zone {
                        position: absolute;
                        left: 0;
                        top: 0;
                        width: 80mm;
                        padding: 0;
                        margin: 0;
                        font-family: monospace;
                        font-size: 12px;
                        line-height: 1.2;
                    }
                }
            `}} />

            <div className="w-full max-w-sm bg-white dark:bg-gray-800 shadow-2xl rounded-2xl flex flex-col overflow-hidden border border-gray-200 dark:border-gray-700">
                <div className="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center bg-gray-50 dark:bg-gray-900">
                    <h2 className="text-sm font-bold text-gray-900 dark:text-white uppercase tracking-wider">Receipt Preview</h2>
                    <button onClick={onClose} className="text-gray-500 hover:text-gray-700 dark:hover:text-gray-300">
                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div className="p-6 bg-gray-100 dark:bg-gray-900 flex justify-center overflow-y-auto max-h-[60vh]">
                    {/* The Digital Thermal Roll Clone */}
                    <div className="thermal-print-zone bg-white text-black p-4 shadow-sm w-[300px] min-h-[400px] border-t-4 border-t-gray-300">
                        <pre className="whitespace-pre-wrap font-mono text-[11px] leading-tight break-all">
                            {/* We sanitize ESC/POS control characters for screen display, rendering only the text grid */}
                            {receiptText.replace(/\x1b\[.*?m|\x1b.*?[a-zA-Z]|\x1d.*?[a-zA-Z]/g, '')}
                        </pre>
                    </div>
                </div>

                <div className="px-6 py-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 flex justify-end space-x-3">
                    <button onClick={onClose} className="px-4 py-2 text-sm font-semibold text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700 rounded-lg transition-colors">
                        Close
                    </button>
                    <button onClick={handlePrint} className="px-6 py-2 text-sm font-bold text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg shadow-md transition-colors flex items-center space-x-2">
                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                        </svg>
                        <span>Print Receipt</span>
                    </button>
                </div>
            </div>
        </div>
    );
}
