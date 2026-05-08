import { useState, useEffect } from '@wordpress/element';
import { adminApi } from '../../utils/api';

export function ComplianceScore() {
    const [score,   setScore]   = useState(0);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        adminApi.getDashboardStats()
            .then(d => setScore(d.compliance_score ?? 0))
            .catch(() => {})
            .finally(() => setLoading(false));
    }, []);

    const r    = 52;
    const circ = 2 * Math.PI * r;
    const dash = circ - (score / 100) * circ;
    const color = score >= 80 ? '#059669' : score >= 50 ? '#d97706' : '#dc2626';

    const items = [
        { label: 'Cookies scanned',        points: 20 },
        { label: 'All cookies categorized', points: 20 },
        { label: 'Banner configured',       points: 15 },
        { label: 'GPC enabled',             points: 15 },
        { label: 'Consent receipts (Pro)',   points: 15 },
        { label: 'DSAR handler (Business)', points: 15 },
    ];

    if (loading) return <div style={{ padding: 24, color: '#9ca3af', fontSize: 13 }}>Loading…</div>;

    return (
        <div style={{ display: 'flex', gap: 32, alignItems: 'flex-start', flexWrap: 'wrap' }}>
            <div style={{ textAlign: 'center', flexShrink: 0 }}>
                <svg width={128} height={128} viewBox="0 0 128 128">
                    <circle cx={64} cy={64} r={r} fill="none" stroke="#e5e7eb" strokeWidth={12} />
                    <circle
                        cx={64} cy={64} r={r}
                        fill="none"
                        stroke={color}
                        strokeWidth={12}
                        strokeDasharray={circ}
                        strokeDashoffset={dash}
                        strokeLinecap="round"
                        transform="rotate(-90 64 64)"
                        style={{ transition: 'stroke-dashoffset .6s ease' }}
                    />
                    <text x={64} y={70} textAnchor="middle" fontSize={24} fontWeight={700} fill={color}>{score}</text>
                </svg>
                <div style={{ fontSize: 12, color: '#6b7280', marginTop: 4 }}>Compliance Score</div>
            </div>
            <div style={{ flex: 1, minWidth: 200 }}>
                {items.map(item => (
                    <div key={item.label} style={{ display: 'flex', justifyContent: 'space-between', padding: '6px 0', borderBottom: '1px solid #f3f4f6', fontSize: 13 }}>
                        <span style={{ color: '#374151' }}>{item.label}</span>
                        <span style={{ fontWeight: 600, color: '#6b7280' }}>+{item.points}</span>
                    </div>
                ))}
            </div>
        </div>
    );
}
