import React, { useState, useEffect } from 'react';
import { uploadProductsCSV, getImportStatus } from '../../lib/api/imports';
import { ImportStatusResponse } from '../../types/imports';
import { DragDropZone } from './DragDropZone';
import { ProgressBar } from './ProgressBar';
import { ErrorRemediationPanel } from './ErrorRemediationPanel';
import toast from 'react-hot-toast';

export const ImportDashboard: React.FC = () => {
    const [status, setStatus] = useState<'idle' | 'uploading' | 'processing' | 'completed'>('idle');
    const [importData, setImportData] = useState<ImportStatusResponse | null>(null);

    // Polling logic
    useEffect(() => {
        let interval: NodeJS.Timeout;

        if (status === 'processing' && importData?.id) {
            interval = setInterval(async () => {
                try {
                    const response = await getImportStatus(importData.id);
                    setImportData(response.data);

                    if (['completed', 'partial_success', 'failed'].includes(response.data.status)) {
                        setStatus('completed');
                        if (response.data.status === 'completed') {
                            toast.success("Import finished successfully!");
                        } else {
                            toast.error("Import finished with errors. Please review.");
                        }
                    }
                } catch (error) {
                }
            }, 1000);
        }

        return () => clearInterval(interval);
    }, [status, importData?.id]);

    const handleFileSelected = async (file: File) => {
        setStatus('uploading');
        try {
            const result = await uploadProductsCSV(file);
            setImportData({
                id: result.import_id,
                status: 'processing',
                total_rows: result.total_rows,
                processed_rows: 0,
                successful_rows: 0,
                failed_rows: 0,
                errors: {}
            });
            setStatus('processing');
            toast.success("File uploaded successfully. Processing started.");
        } catch (error: any) {
            setStatus('idle');
            if (error.response?.status === 403) {
                toast.error("Access Denied: BusinessAdmin role required.");
            } else if (error.response?.data?.errors?.file) {
                toast.error(error.response.data.errors.file[0]);
            } else {
                toast.error("Failed to upload file.");
            }
        }
    };

    const handleReset = () => {
        setStatus('idle');
        setImportData(null);
    };

    return (
        <div className="max-w-4xl mx-auto py-8">
            <div className="mb-8">
                <h2 className="text-3xl font-extrabold text-gray-900 tracking-tight">Bulk Data Import</h2>
                <p className="mt-2 text-sm text-gray-500">
                    Securely migrate thousands of legacy products via our asynchronous processing engine.
                </p>
            </div>

            <div className="bg-white/90 backdrop-blur-md rounded-2xl shadow-sm border border-gray-100 p-8">
                {status === 'idle' || status === 'uploading' ? (
                    <div>
                        <DragDropZone 
                            onFileSelected={handleFileSelected} 
                            disabled={status === 'uploading'} 
                        />
                        {status === 'uploading' && (
                            <p className="text-center text-sm font-bold text-blue-600 mt-4 animate-pulse">
                                Uploading file to server...
                            </p>
                        )}
                    </div>
                ) : (
                    <div className="space-y-6">
                        {importData && (
                            <ProgressBar 
                                total={importData.total_rows} 
                                processed={importData.processed_rows} 
                                status={importData.status} 
                            />
                        )}

                        {status === 'completed' && importData && (
                            <div className="animate-fade-in-up">
                                <ErrorRemediationPanel errors={importData.errors} />
                                
                                <div className="mt-8 flex justify-center">
                                    <button 
                                        onClick={handleReset}
                                        className="px-6 py-3 bg-gray-900 text-white font-bold rounded-xl shadow-md hover:bg-gray-800 transition-colors"
                                    >
                                        Import Another File
                                    </button>
                                </div>
                            </div>
                        )}
                    </div>
                )}
            </div>
        </div>
    );
};
