export interface CashRegisterSetting {
    pos_enforce_device_lock: boolean;
    pos_enforce_strict_cash_control: boolean;
}

export interface CashRegisterSession {
    id: number;
    business_id: number;
    device_hash: string;
    opened_by_user_id: number;
    status: 'open' | 'suspending' | 'closed';
    opening_balance: string;
    closing_balance_expected: string | null;
    closing_balance_counted: string | null;
    discrepancy_amount: string | null;
    created_at: string;
    updated_at: string;
    closed_at: string | null;
}

export interface RegisterStatusHandshakeResponse {
    is_open: boolean;
    status?: 'open' | 'suspending' | 'closed';
    settings: CashRegisterSetting;
    register?: CashRegisterSession | null;
    cash_sales?: string | number;
    cash_expenses?: string | number;
    expected_cash?: string | number;
}
