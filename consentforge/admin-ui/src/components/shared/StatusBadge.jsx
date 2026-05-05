import { CATEGORIES, STATUS_COLORS } from '../../utils/constants';

export function StatusBadge({ status }) {
    const cat   = CATEGORIES[status];
    const color = cat ? { background: cat.bg, color: cat.text } : (STATUS_COLORS[status] || { background: '#f3f4f6', color: '#374151' });
    const label = cat ? cat.label : (status || '—');
    return (
        <span style={{
            ...color,
            display: 'inline-block',
            fontSize: 11,
            fontWeight: 600,
            padding: '2px 8px',
            borderRadius: 12,
            textTransform: 'capitalize',
            letterSpacing: '.02em',
        }}>
            {label}
        </span>
    );
}
