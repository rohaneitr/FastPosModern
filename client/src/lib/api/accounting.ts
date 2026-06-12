import api from '../api';
import { ProfitAndLossReport, BalanceSheetReport } from '../../types/accounting';

export const getProfitAndLoss = async (startDate?: string, endDate?: string): Promise<ProfitAndLossReport> => {
    const params = new URLSearchParams();
    if (startDate) params.append('start_date', startDate);
    if (endDate) params.append('end_date', endDate);
    
    // API base URL already includes /api/v1
    const response = await api.get(`/accounting/profit-and-loss?${params.toString()}`);
    return response.data;
};

export const getBalanceSheet = async (asOfDate?: string): Promise<BalanceSheetReport> => {
    const params = new URLSearchParams();
    if (asOfDate) params.append('as_of_date', asOfDate);
    
    const response = await api.get(`/accounting/balance-sheet?${params.toString()}`);
    return response.data;
};
