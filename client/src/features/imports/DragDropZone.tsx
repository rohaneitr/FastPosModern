import React, { useCallback, useState } from 'react';

interface DragDropZoneProps {
    onFileSelected: (file: File) => void;
    disabled?: boolean;
}

export const DragDropZone: React.FC<DragDropZoneProps> = ({ onFileSelected, disabled }) => {
    const [isDragging, setIsDragging] = useState(false);

    const handleDrag = useCallback((e: React.DragEvent) => {
        e.preventDefault();
        e.stopPropagation();
        if (e.type === 'dragenter' || e.type === 'dragover') {
            setIsDragging(true);
        } else if (e.type === 'dragleave') {
            setIsDragging(false);
        }
    }, []);

    const handleDrop = useCallback((e: React.DragEvent) => {
        e.preventDefault();
        e.stopPropagation();
        setIsDragging(false);
        
        if (disabled) return;

        if (e.dataTransfer.files && e.dataTransfer.files[0]) {
            const file = e.dataTransfer.files[0];
            if (file.type === 'text/csv' || file.name.endsWith('.csv')) {
                onFileSelected(file);
            }
        }
    }, [onFileSelected, disabled]);

    const handleFileInput = (e: React.ChangeEvent<HTMLInputElement>) => {
        if (e.target.files && e.target.files[0]) {
            onFileSelected(e.target.files[0]);
        }
    };

    return (
        <div 
            className={`
                relative w-full h-64 rounded-2xl border-2 border-dashed transition-all duration-300 ease-in-out flex flex-col items-center justify-center p-6
                ${disabled ? 'opacity-50 cursor-not-allowed bg-gray-50 border-gray-200' : 
                  isDragging ? 'bg-blue-50/80 border-blue-400 scale-[1.02] shadow-lg shadow-blue-100/50' : 
                  'bg-white/50 border-gray-300 hover:bg-white/80 hover:border-gray-400 cursor-pointer'}
                backdrop-blur-md
            `}
            onDragEnter={handleDrag}
            onDragLeave={handleDrag}
            onDragOver={handleDrag}
            onDrop={handleDrop}
            onClick={() => !disabled && document.getElementById('file-upload')?.click()}
        >
            <input 
                id="file-upload" 
                type="file" 
                accept=".csv" 
                className="hidden" 
                onChange={handleFileInput}
                disabled={disabled}
            />
            
            <div className={`p-4 rounded-full mb-4 transition-colors ${isDragging ? 'bg-blue-100 text-blue-600' : 'bg-gray-100 text-gray-500'}`}>
                <svg className="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                </svg>
            </div>
            
            <h3 className="text-lg font-bold text-gray-900">
                {isDragging ? 'Drop CSV to import' : 'Click or Drag & Drop to Upload'}
            </h3>
            <p className="text-sm text-gray-500 mt-2 text-center max-w-sm">
                Upload your legacy products via a standard CSV file. Maximum file size is 10MB.
            </p>
        </div>
    );
};
