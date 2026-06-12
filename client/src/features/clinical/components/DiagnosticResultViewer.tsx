import React from 'react';
import { AlertTriangle, CheckCircle } from 'lucide-react';

interface DiagnosticResult {
    parameter_name: string;
    result_value: string;
    unit: string;
    reference_range: string;
    is_abnormal: boolean;
}

interface DiagnosticResultViewerProps {
    results: DiagnosticResult[];
    testName: string;
    doctorRemarks?: string;
}

export const DiagnosticResultViewer: React.FC<DiagnosticResultViewerProps> = ({ results, testName, doctorRemarks }) => {
    return (
        <div className="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
            <div className="bg-gray-50 border-b border-gray-200 p-6 flex justify-between items-center">
                <h3 className="font-black text-gray-900 text-xl tracking-tight">{testName}</h3>
                <span className="bg-indigo-100 text-indigo-700 text-xs font-bold px-3 py-1 rounded-full uppercase tracking-wider">Verified Results</span>
            </div>
            
            <div className="overflow-x-auto">
                <table className="w-full text-left border-collapse">
                    <thead>
                        <tr className="bg-gray-50/50 text-gray-500 text-xs uppercase tracking-wider">
                            <th className="p-4 font-bold w-1/3 border-b border-gray-200">Investigation</th>
                            <th className="p-4 font-bold border-b border-gray-200">Result</th>
                            <th className="p-4 font-bold border-b border-gray-200">Reference Interval</th>
                            <th className="p-4 font-bold border-b border-gray-200 text-right">Status</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100">
                        {results.map((res, idx) => (
                            <tr key={idx} className={`hover:bg-gray-50/50 transition-colors ${res.is_abnormal ? 'bg-rose-50/30' : ''}`}>
                                <td className="p-4 font-semibold text-gray-900">
                                    {res.parameter_name}
                                </td>
                                <td className="p-4">
                                    <span className={`font-black text-lg ${res.is_abnormal ? 'text-rose-600' : 'text-gray-900'}`}>
                                        {res.result_value}
                                    </span>
                                    <span className="text-gray-500 text-sm ml-1">{res.unit}</span>
                                </td>
                                <td className="p-4 text-gray-500 text-sm font-medium">
                                    {res.reference_range}
                                </td>
                                <td className="p-4 text-right">
                                    {res.is_abnormal ? (
                                        <span className="inline-flex items-center gap-1 bg-rose-100 text-rose-700 px-2 py-1 rounded text-xs font-bold">
                                            <AlertTriangle size={14} /> Out of Bounds
                                        </span>
                                    ) : (
                                        <span className="inline-flex items-center gap-1 text-emerald-600 text-xs font-bold">
                                            <CheckCircle size={14} /> Normal
                                        </span>
                                    )}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {doctorRemarks && doctorRemarks !== 'None' && (
                <div className="p-6 bg-gray-50 border-t border-gray-200">
                    <h4 className="text-sm font-bold text-gray-900 mb-2 uppercase tracking-wider">Clinical Remarks</h4>
                    <p className="text-gray-700 italic border-l-4 border-indigo-500 pl-4 py-1">
                        "{doctorRemarks}"
                    </p>
                </div>
            )}
        </div>
    );
};
