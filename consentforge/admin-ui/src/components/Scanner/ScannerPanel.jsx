import { useState, useEffect } from '@wordpress/element';
import { adminApi } from '../../utils/api';
import { Card } from '../shared/Card';
import { DataTable } from '../shared/DataTable';
import { StatusBadge } from '../shared/StatusBadge';

export function ScannerPanel() {
    const [results,  setResults]  = useState([]);
    const [scanning, setScanning] = useState(false);
    const [message,  setMessage]  = useState('');
    const [loading,  setLoading]  = useState(true);

    const fetchResults = () => adminApi.getScannerResults().then(setResults).catch(() => {}).finally(() => setLoading(false));

    useEffect(() => { fetchResults(); }, []);

    const runScan = async () => {
        setScanning(true);
        setMessage('');
        try {
            const r = await adminApi.runScanner();
            setMessage(`Scan complete. Scan ID: ${r.scan_id}`);
            fetchResults();
        } catch (e) {
            setMessage(e?.message || 'Scan failed.');
        } finally {
            setScanning(false);
        }
    };

    const unclassified = results.reduce((sum, r) => sum + (parseInt(r.uncategorized_count) || 0), 0);

    const columns = [
        { key: 'created_at',       label: 'Date',       render: v => new Date(v).toLocaleString() },
        { key: 'scan_type',        label: 'Type',       render: v => <span style={{ textTransform: 'capitalize' }}>{v}</span> },
        { key: 'new_cookies_count',label: 'New Cookies' },
        { key: 'uncategorized_count', label: 'Unclassified' },
        { key: 'scan_duration_ms', label: 'Duration',   render: v => v ? `${v}ms` : '—' },
        { key: 'scan_status',      label: 'Status',     render: v => <StatusBadge status={v} /> },
    ];

    return (
        <div>
            {unclassified > 0 && (
                <div style={{ background: '#fef3c7', border: '1px solid #fcd34d', borderRadius: 8, padding: 16, marginBottom: 20, fontSize: 13, color: '#92400e' }}>
                    ⚠️ <strong>{unclassified} unclassified cookie(s)</strong> detected. Please review and categorize them in the Cookie Registry.
                </div>
            )}
            <Card
                title="Cookie Scanner"
                description="Scan your site pages to discover cookies, tracking scripts, and pixels."
                action={
                    <button
                        onClick={runScan}
                        disabled={scanning}
                        style={{ background: scanning ? '#93c5fd' : '#2563eb', color: '#fff', border: 'none', padding: '9px 18px', borderRadius: 6, fontSize: 13, fontWeight: 500, cursor: scanning ? 'not-allowed' : 'pointer' }}
                    >
                        {scanning ? 'Scanning…' : 'Run Scan Now'}
                    </button>
                }
            >
                {message && <div style={{ fontSize: 13, color: '#374151', marginBottom: 12, padding: '8px 12px', background: '#f0fdf4', borderRadius: 6 }}>{message}</div>}
                <h4 style={{ fontSize: 14, fontWeight: 600, margin: '0 0 12px', color: '#374151' }}>Scan History</h4>
                <DataTable columns={columns} data={results} loading={loading} emptyMessage="No scans yet. Run your first scan above." />
            </Card>
        </div>
    );
}
