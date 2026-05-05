import { useState, useEffect } from '@wordpress/element';
import { adminApi } from '../../utils/api';

function Stat({ label, value, sub, color = '#2563eb' }) {
    return (
        <div style={{ background: '#f9fafb', borderRadius: 8, padding: '16px 20px', flex: 1, minWidth: 120 }}>
            <div style={{ fontSize: 28, fontWeight: 700, color }}>{value}</div>
            <div style={{ fontSize: 13, fontWeight: 600, color: '#374151', marginTop: 2 }}>{label}</div>
            {sub && <div style={{ fontSize: 11, color: '#9ca3af', marginTop: 2 }}>{sub}</div>}
        </div>
    );
}

export function ConsentStats() {
    const [stats,   setStats]   = useState(null);
    const [trend,   setTrend]   = useState([]);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        adminApi.getDashboardStats()
            .then(d => { setStats(d.stats); setTrend(d.trend || []); })
            .catch(() => {})
            .finally(() => setLoading(false));
    }, []);

    if (loading) return <div style={{ color: '#9ca3af', fontSize: 13 }}>Loading…</div>;
    if (!stats)  return <div style={{ color: '#ef4444', fontSize: 13 }}>Failed to load stats.</div>;

    const rate = stats.total > 0 ? Math.round((stats.accepted / stats.total) * 100) : 0;
    const maxCount = Math.max(...trend.map(d => d.count || 0), 1);

    return (
        <div>
            <div style={{ display: 'flex', gap: 12, flexWrap: 'wrap', marginBottom: 20 }}>
                <Stat label="Total Consents"   value={stats.total?.toLocaleString() ?? 0} color="#111827" />
                <Stat label="Accept Rate"      value={rate + '%'}                          color="#059669" />
                <Stat label="Rejected"         value={stats.rejected?.toLocaleString() ?? 0} color="#dc2626" />
                <Stat label="GPC Auto-Applied" value={stats.gpc?.toLocaleString() ?? 0}   color="#7c3aed" />
            </div>
            {trend.length > 0 && (
                <div>
                    <div style={{ fontSize: 12, fontWeight: 600, color: '#6b7280', marginBottom: 8, textTransform: 'uppercase', letterSpacing: '.04em' }}>Last 30 Days</div>
                    <div style={{ display: 'flex', alignItems: 'flex-end', gap: 3, height: 60 }}>
                        {trend.map((d, i) => (
                            <div key={i} title={`${d.date}: ${d.count}`} style={{
                                flex: 1,
                                height: Math.max(3, (d.count / maxCount) * 60),
                                background: '#2563eb',
                                borderRadius: 2,
                                opacity: .7 + (i / trend.length) * .3,
                                cursor: 'default',
                            }} />
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
}
