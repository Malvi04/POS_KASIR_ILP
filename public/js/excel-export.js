/**
 * Global Excel Table Exporter for POS Koperasi PT Indo Lampung Perkasa.
 * Optimized with lazy loading: downloads 880KB xlsx library only on demand when user exports!
 */
window.exportTableToExcel = async function(config) {
    const filename = config.filename || 'Export_Data.xlsx';
    const cleanFilename = filename.endsWith('.xlsx') ? filename : filename.replace(/\.[^/.]+$/, '') + '.xlsx';
    const sheetName = (config.sheetName || 'Laporan').substring(0, 31);
    const headers = config.headers || [];
    const rows = config.rows || [];
    const totals = config.totals || null;
    const metadata = config.metadata || [];
    const title = config.title || 'PT INDO LAMPUNG PERKASA - MINIMARKET KOPERASI';
    const subtitle = config.subtitle || '';

    // Load XLSX on-demand if not yet available
    if (typeof XLSX === 'undefined') {
        try {
            await new Promise((resolve, reject) => {
                const s = document.createElement('script');
                s.src = '/js/xlsx.full.min.js';
                s.onload = resolve;
                s.onerror = reject;
                document.head.appendChild(s);
            });
        } catch(e) {
            console.warn('XLSX lazy loading failed, using high-compatibility CSV fallback', e);
        }
    }

    if (typeof XLSX !== 'undefined') {
        const wb = XLSX.utils.book_new();
        const aoa = [];

        // 1. Company Header
        if (title) aoa.push([title]);
        if (subtitle) aoa.push([subtitle]);
        if (title || subtitle) aoa.push([]);

        // 2. Metadata Section
        if (metadata && metadata.length > 0) {
            metadata.forEach(m => {
                aoa.push([m[0] + ':', m[1]]);
            });
            aoa.push([]);
        }

        // 3. Table Headers
        aoa.push(headers);

        // 4. Data Rows
        rows.forEach(r => {
            aoa.push(r);
        });

        // 5. Totals / Summary Row
        if (totals && totals.length > 0) {
            aoa.push(totals);
        }

        const ws = XLSX.utils.aoa_to_sheet(aoa);

        // Calculate intelligent auto column widths
        const numCols = headers.length;
        const colWidths = [];
        for (let c = 0; c < numCols; c++) {
            let maxLen = (headers[c] || '').toString().length;
            rows.forEach(row => {
                const val = row[c];
                if (val !== undefined && val !== null) {
                    const len = val.toString().length;
                    if (len > maxLen) maxLen = len;
                }
            });
            if (totals && totals[c] !== undefined && totals[c] !== null) {
                const len = totals[c].toString().length;
                if (len > maxLen) maxLen = len;
            }
            colWidths.push({ wch: Math.min(Math.max(maxLen + 4, 12), 65) });
        }
        ws['!cols'] = colWidths;

        XLSX.utils.book_append_sheet(wb, ws, sheetName);
        XLSX.writeFile(wb, cleanFilename);
        return true;
    } else {
        // High-compatibility CSV fallback with Excel sep=, directive
        const sanitize = (text) => {
            if (text === null || text === undefined) return '""';
            let str = String(text).replace(/"/g, '""');
            return `"${str}"`;
        };
        const lines = [
            'sep=,',
            sanitize(title),
            subtitle ? sanitize(subtitle) : '',
            '',
            ...metadata.map(m => `${sanitize(m[0])},${sanitize(m[1])}`),
            '',
            headers.map(sanitize).join(','),
            ...rows.map(r => r.map(sanitize).join(',')),
            totals ? totals.map(sanitize).join(',') : ''
        ].filter(l => l !== null);

        const blob = new Blob(['\uFEFF' + lines.join('\r\n')], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = cleanFilename.replace(/\.xlsx$/, '.csv');
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
        return true;
    }
};
