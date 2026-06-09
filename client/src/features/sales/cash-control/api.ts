import api from '../../../lib/api';
import { RegisterStatusHandshakeResponse } from './types';

export const cashControlApi = {
    /**
     * Fetches the active register status and global tenant cash control settings.
     */
    async fetchRegisterStatus(): Promise<RegisterStatusHandshakeResponse> {
        const { data } = await api.get<RegisterStatusHandshakeResponse>('/api/v1/register/status');
        return data;
    },

    /**
     * Opens a new cash register shift specifically locked to this device.
     */
    async openRegisterSession(openingBalance: string): Promise<{ message: string, register_id: number }> {
        const { data } = await api.post('/api/v1/register/open', { opening_balance: openingBalance });
        return data;
    },

    /**
     * Freezes the active register to prevent discrepancy drift while counting physically.
     */
    async suspendRegisterSession(): Promise<{ message: string, expected_cash: string | number }> {
        const { data } = await api.post('/api/v1/register/suspend');
        return data;
    },

    /**
     * Submits the final physical cash count, resolves discrepancies, and posts to the GL.
     */
    async closeRegisterSession(countedBalance: string): Promise<{
        message: string;
        closing_balance_expected: string | number;
        closing_balance_counted: string | number;
        discrepancy_amount: string | number;
    }> {
        const { data } = await api.post('/api/v1/register/close', { closing_balance_counted: countedBalance });
        return data;
    }
};
