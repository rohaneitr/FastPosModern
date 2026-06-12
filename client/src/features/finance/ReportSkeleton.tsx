import React from 'react';

export const ReportSkeleton: React.FC = () => {
    return (
        <div className="animate-pulse space-y-6">
            <div className="h-24 bg-gray-200 rounded-xl w-full max-w-2xl"></div>
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div className="h-32 bg-gray-200 rounded-xl w-full"></div>
                <div className="h-32 bg-gray-200 rounded-xl w-full"></div>
                <div className="h-32 bg-gray-200 rounded-xl w-full"></div>
            </div>
            <div className="space-y-4">
                <div className="h-10 bg-gray-200 rounded w-full"></div>
                <div className="h-10 bg-gray-200 rounded w-full"></div>
                <div className="h-10 bg-gray-200 rounded w-full"></div>
            </div>
        </div>
    );
};

export const AccessDeniedSkeleton: React.FC = () => {
    return (
        <div className="flex flex-col items-center justify-center py-24 text-center px-4">
            <div className="w-20 h-20 bg-red-100 rounded-full flex items-center justify-center mb-6">
                <svg className="w-10 h-10 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8V7a4 4 0 00-8 0v4h8z" />
                </svg>
            </div>
            <h2 className="text-2xl font-bold text-gray-900 mb-2">Access Denied</h2>
            <p className="text-gray-500 max-w-md">
                You do not have the required permissions to view enterprise financial reports. This area is strictly restricted to Business Administrators.
            </p>
        </div>
    );
};
