export interface AccountEntry {
    account_name: string;
    code: string;
    balance: string;
}

export interface ProfitAndLossReport {
    totals: {
        revenue: string;
        cogs: string;
        gross_profit: string;
        operating_expenses: string;
        net_profit: string;
    };
    breakdown: {
        revenue_accounts: AccountEntry[];
        cogs_accounts: AccountEntry[];
        expense_accounts: AccountEntry[];
    };
}

export interface BalanceSheetReport {
    totals: {
        assets: string;
        liabilities: string;
        core_equity: string;
        retained_earnings: string;
        total_equity: string;
        liabilities_and_equity: string;
    };
    breakdown: {
        asset_accounts: AccountEntry[];
        liability_accounts: AccountEntry[];
        equity_accounts: AccountEntry[];
    };
}
