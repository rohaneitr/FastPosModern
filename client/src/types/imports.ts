export interface ImportStatusResponse {
    id: number;
    status: 'pending' | 'processing' | 'completed' | 'partial_success' | 'failed';
    total_rows: number;
    processed_rows: number;
    successful_rows: number;
    failed_rows: number;
    errors: Record<string, string>;
}
