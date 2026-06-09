import React from 'react';

interface DateRangePickerProps {
    startDate: string;
    endDate: string;
    onChangeStart: (date: string) => void;
    onChangeEnd: (date: string) => void;
}

export const DateRangePicker: React.FC<DateRangePickerProps> = ({
    startDate,
    endDate,
    onChangeStart,
    onChangeEnd
}) => {
    return (
        <div className="flex flex-col sm:flex-row items-center space-y-3 sm:space-y-0 sm:space-x-4 bg-white p-4 rounded-xl shadow-sm border border-gray-100 mb-6">
            <div className="flex flex-col w-full sm:w-auto">
                <label className="text-xs font-semibold text-gray-500 mb-1 uppercase tracking-wider">Start Date</label>
                <input
                    type="date"
                    name="start_date"
                    value={startDate}
                    onChange={(e) => onChangeStart(e.target.value)}
                    className="px-4 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all w-full sm:w-48"
                />
            </div>
            <div className="flex flex-col w-full sm:w-auto">
                <label className="text-xs font-semibold text-gray-500 mb-1 uppercase tracking-wider">End Date</label>
                <input
                    type="date"
                    name="end_date"
                    value={endDate}
                    onChange={(e) => onChangeEnd(e.target.value)}
                    className="px-4 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all w-full sm:w-48"
                />
            </div>
        </div>
    );
};
