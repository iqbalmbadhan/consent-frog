export function DataTable({ columns, data, loading, emptyMessage = 'No data found.' }) {
    const th = { padding: '10px 14px', textAlign: 'left', fontSize: 12, fontWeight: 600, color: '#6b7280', textTransform: 'uppercase', letterSpacing: '.04em', borderBottom: '1px solid #e5e7eb', whiteSpace: 'nowrap' };
    const td = { padding: '12px 14px', fontSize: 13, color: '#374151', borderBottom: '1px solid #f3f4f6', verticalAlign: 'middle' };

    if (loading) {
        return <div style={{ padding: 32, textAlign: 'center', color: '#9ca3af', fontSize: 14 }}>Loading…</div>;
    }
    if (!data || data.length === 0) {
        return <div style={{ padding: 32, textAlign: 'center', color: '#9ca3af', fontSize: 14 }}>{emptyMessage}</div>;
    }

    return (
        <div style={{ overflowX: 'auto' }}>
            <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: 13 }}>
                <thead>
                    <tr>{columns.map(c => <th key={c.key} style={th}>{c.label}</th>)}</tr>
                </thead>
                <tbody>
                    {data.map((row, i) => (
                        <tr key={row.id ?? i} style={{ background: i % 2 === 0 ? '#fff' : '#fafafa' }}>
                            {columns.map(c => (
                                <td key={c.key} style={td}>
                                    {c.render ? c.render(row[c.key], row) : (row[c.key] ?? '—')}
                                </td>
                            ))}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
