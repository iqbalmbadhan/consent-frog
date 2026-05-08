import { useState, useEffect } from '@wordpress/element';
import { adminApi } from '../../utils/api';
import { Card } from '../shared/Card';
import { DataTable } from '../shared/DataTable';
import { StatusBadge } from '../shared/StatusBadge';

export function DSARList({ enabled = false }) {
    const [requests, setRequests] = useState([]);
    const [loading,  setLoading]  = useState(true);
    const [filter,   setFilter]   = useState('');

    const fetch = () => adminApi.getDsarRequests().then(setRequests).catch(() => {}).finally(() => setLoading(false));
    useEffect(() => { if (enabled) fetch(); else setLoading(false); }, [enabled]);

    const process = async (id) => {
        await adminApi.processDsar(id);
        fetch();
    };

    if (!enabled) {
        return (
            <Card title="DSAR Requests">
                <div style={{ textAlign: 'center', padding: 40 }}>
                    <div style={{ fontSize: 32, marginBottom: 12 }}>🔒</div>
                    <div style={{ fontWeight: 600, fontSize: 15, color: '#374151' }}>Business Plan Required</div>
                    <div style={{ fontSize: 13, color: '#9ca3af', marginTop: 8 }}>DSAR automation is available on the Business plan and above.</div>
                    <a href="https://consentforge.com/pricing/" target="_blank" rel="noreferrer" style={{ display: 'inline-block', marginTop: 16, background: '#2563eb', color: '#fff', padding: '9px 20px', borderRadius: 6, fontSize: 13, fontWeight: 500, textDecoration: 'none' }}>Upgrade to Business</a>
                </div>
            </Card>
        );
    }

    const filtered = filter ? requests.filter(r => r.status === filter) : requests;

    const columns = [
        { key: 'requester_email', label: 'Email' },
        { key: 'request_type',    label: 'Type',   render: v => <span style={{ textTransform: 'capitalize' }}>{v}</span> },
        { key: 'status',          label: 'Status', render: v => <StatusBadge status={v} /> },
        { key: 'created_at',      label: 'Submitted', render: v => new Date(v).toLocaleDateString() },
        { key: 'id', label: 'Actions', render: (v, row) => row.status === 'verified' ? (
            <button onClick={() => process(v)} style={{ fontSize: 12, background: '#2563eb', color: '#fff', border: 'none', padding: '5px 12px', borderRadius: 5, cursor: 'pointer' }}>Process</button>
        ) : null },
    ];

    return (
        <Card title={`DSAR Requests (${requests.length})`} description="Data Subject Access Requests from your visitors.">
            <div style={{ marginBottom: 12 }}>
                <select value={filter} onChange={e => setFilter(e.target.value)} style={{ padding: '7px 12px', border: '1px solid #d1d5db', borderRadius: 6, fontSize: 13 }}>
                    <option value="">All statuses</option>
                    {['pending','verified','processing','completed','rejected'].map(s => <option key={s} value={s}>{s}</option>)}
                </select>
            </div>
            <DataTable columns={columns} data={filtered} loading={loading} emptyMessage="No DSAR requests yet." />
        </Card>
    );
}
