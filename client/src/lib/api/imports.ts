import api from '../api';
import { ImportStatusResponse } from '../../types/imports';

export const uploadProductsCSV = async (file: File): Promise<{ import_id: number; total_rows: number }> => {
    const formData = new FormData();
    formData.append('file', file);

    const response = await api.post('/data-migration/import/products', formData, {
        headers: {
            'Content-Type': 'multipart/form-data'
        }
    });
    return response.data;
};

export const getImportStatus = async (id: number): Promise<{ data: ImportStatusResponse }> => {
    const response = await api.get(`/data-migration/status/${id}`);
    return response.data;
};
