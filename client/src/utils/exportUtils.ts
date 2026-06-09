export const exportToCSV = (filename: string, rows: Record<string, string | number>[]) => {
    if (!rows || !rows.length) return;

    const headers = Object.keys(rows[0]);
    const csvContent = [
        headers.join(','), // Header row
        ...rows.map(row => 
            headers.map(header => {
                const cell = row[header] === null ? '' : row[header].toString();
                // Escape quotes and wrap in quotes to prevent comma breaking
                return `"${cell.replace(/"/g, '""')}"`;
            }).join(',')
        )
    ].join('\n');

    // Add BOM for Excel UTF-8 compatibility
    const blob = new Blob(['\uFEFF' + csvContent], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    
    const link = document.createElement('a');
    link.href = url;
    link.setAttribute('download', `${filename}_${new Date().toISOString().split('T')[0]}.csv`);
    document.body.appendChild(link);
    link.click();
    
    // Cleanup
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
};
