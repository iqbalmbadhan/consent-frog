import { useState } from '@wordpress/element';
import { useCookies } from '../../hooks/useCookies';
import { Card } from '../shared/Card';
import { DataTable } from '../shared/DataTable';
import { StatusBadge } from '../shared/StatusBadge';

const CATS = ['essential', 'analytics', 'performance', 'marketing', 'unclassified'];

export function CookieList() {
    const [page,   setPage]   = useState(1);
    const [filter, setFilter] = useState('');
    const [search, setSearch] = useState('');
    const { cookies, total, totalPages, loading, updateCookie, deleteCookie } = useCookies(page, filter);

    const filtered = search ? cookies.filter(c => c.cookie_name?.toLowerCase().includes(search.toLowerCase()) || c.provider?.toLowerCase().includes(search.toLowerCase())) : cookies;

    const columns = [
        { key: 'cookie_name', label: 'Cookie Name', render: v => <code style={{ fontSize: 12, background: '#f3f4f6', padding: '2px 6px', borderRadius: 4 }}>{v}</code> },
        { key: 'provider',    label: 'Provider' },
        { key: 'category',    label: 'Category', render: (v, row) => (
            <select
                value={v}
                onChange={e => updateCookie(row.id, { category: e.target.value })}
                style={{ fontSize: 12, padding: '3px 6px', border: '1px solid #d1d5db', borderRadius: 4 }}
            >
                {CATS.map(c => <option key={c} value={c}>{c}</option>)}
            </select>
        )},
        { key: 'duration',    label: 'Duration' },
        { key: 'consent_model', label: 'Model', render: v => <StatusBadge status={v === 'opt_out' ? 'analytics' : v === 'opt_in' ? 'marketing' : 'essential'} /> },
        { key: 'id', label: 'Actions', render: (v, row) => (
            <button
                onClick={() => { if (window.confirm(window.CFAdminData?.i18n?.confirmDelete || 'Delete?')) deleteCookie(v); }}
                style={{ fontSize: 11, color: '#dc2626', background: 'none', border: 'none', cursor: 'pointer', padding: '2px 6px' }}
            >Delete</button>
        )},
    ];

    return (
        <Card title={`Cookie Registry (${total} cookies)`} description="Manage and categorize all discovered cookies.">
            <div style={{ display: 'flex', gap: 10, marginBottom: 16, flexWrap: 'wrap' }}>
                <input
                    type="search"
                    placeholder="Search cookies…"
                    value={search}
                    onChange={e => setSearch(e.target.value)}
                    style={{ padding: '8px 12px', border: '1px solid #d1d5db', borderRadius: 6, fontSize: 13, flex: 1, minWidth: 160 }}
                />
                <select
                    value={filter}
                    onChange={e => { setFilter(e.target.value); setPage(1); }}
                    style={{ padding: '8px 12px', border: '1px solid #d1d5db', borderRadius: 6, fontSize: 13 }}
                >
                    <option value="">All categories</option>
                    {CATS.map(c => <option key={c} value={c}>{c}</option>)}
                </select>
            </div>
            <DataTable columns={columns} data={filtered} loading={loading} emptyMessage="No cookies found. Run a scan first." />
            {totalPages > 1 && (
                <div style={{ display: 'flex', gap: 8, justifyContent: 'center', marginTop: 16 }}>
                    <button onClick={() => setPage(p => Math.max(1, p - 1))} disabled={page === 1} style={{ padding: '6px 14px', border: '1px solid #d1d5db', borderRadius: 6, background: '#fff', cursor: 'pointer', fontSize: 13 }}>← Prev</button>
                    <span style={{ padding: '6px 12px', fontSize: 13, color: '#6b7280' }}>Page {page} of {totalPages}</span>
                    <button onClick={() => setPage(p => Math.min(totalPages, p + 1))} disabled={page === totalPages} style={{ padding: '6px 14px', border: '1px solid #d1d5db', borderRadius: 6, background: '#fff', cursor: 'pointer', fontSize: 13 }}>Next →</button>
                </div>
            )}
        </Card>
    );
}
