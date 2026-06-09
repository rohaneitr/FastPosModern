import React from 'react';

interface AsOfDatePickerProps {
    asOfDate: string;
    onChange: (date: string) => void;
}

export const AsOfDatePicker: React.FC<AsOfDatePickerProps> = ({ asOfDate, onChange }) => {
    return (
        <div className="flex flex-col sm:flex-row items-center space-y-3 sm:space-y-0 sm:space-x-4 bg-white p-4 rounded-xl shadow-sm border border-gray-100 mb-6">
            <div className="flex flex-col w-full sm:w-auto">
                <label className="text-xs font-semibold text-gray-500 mb-1 uppercase tracking-wider">As Of Date</label>
                <input
                    type="date"
                    name="as_of_date"
                    value={asOfDate}
                    onChange={(e) => onChange(e.target.value)}
                    className="px-4 py-2 border border-blue-200 bg-blue-50/50 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all w-full sm:w-64"
                />
            </div>
            <p className="text-xs text-gray-400 mt-2 sm:mt-0 sm:ml-4 max-w-sm">
                * The Balance Sheet displays a cumulative snapshot from inception up to this exact date.
            </p>
        </div>
    );
};
