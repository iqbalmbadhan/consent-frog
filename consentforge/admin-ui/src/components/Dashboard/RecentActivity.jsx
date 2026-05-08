import { useState, useEffect } from '@wordpress/element';
import { adminApi } from '../../utils/api';
import { StatusBadge } from '../shared/StatusBadge';
import { CONSENT_TYPES } from '../../utils/constants';

export function RecentActivity() {
    const [logs,    setLogs]    = useState([]);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        adminApi.getConsentLogs(1)
            .then(d => setLogs(Array.isArray(d) ? d.slice(0, 20) : []))
            .catch(() => {})
            .finally(() => setLoading(false));
    }, []);

    if (loading) return <div style={{ color: '#9ca3af', fontSize: 13 }}>Loading…</div>;
    if (!logs.length) return <div style={{ color: '#9ca3af', fontSize: 13 }}>No consent events yet.</div>;

    return (
        <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
            {logs.map(log => {
                const cats   = JSON.parse(log.categories_accepted || '[]');
                const status = cats.includes('marketing') ? 'granted' : cats.length > 1 ? 'partial' : 'denied';
                return (
                    <div key={log.id} style={{ display: 'flex', alignItems: 'center', gap: 12, padding: '8px 0', borderBottom: '1px solid #f3f4f6', fontSize: 13 }}>
                        <div style={{ flex: 1 }}>
                            <span style={{ fontWeight: 500, color: '#111827' }}>{CONSENT_TYPES[log.consent_type] || log.consent_type}</span>
                            {log.gpc_detected === '1' && <span style={{ marginLeft: 6, fontSize: 10, background: '#ede9fe', color: '#5b21b6', padding: '1px 5px', borderRadius: 4, fontWeight: 600 }}>GPC</span>}
                            {log.ip_country && <span style={{ marginLeft: 6, fontSize: 11, color: '#9ca3af' }}>{log.ip_country}</span>}
                        </div>
                        <StatusBadge status={status} />
                        <div style={{ fontSize: 11, color: '#9ca3af', whiteSpace: 'nowrap' }}>{new Date(log.created_at).toLocaleDateString()}</div>
                    </div>
                );
            })}
        </div>
    );
}
