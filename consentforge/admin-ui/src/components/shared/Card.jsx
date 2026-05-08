export function Card({ title, description, children, className = '', action }) {
    return (
        <div style={{ background: '#fff', borderRadius: 8, border: '1px solid #e5e7eb', padding: 24, marginBottom: 20 }} className={className}>
            {(title || action) && (
                <div style={{ display: 'flex', alignItems: 'flex-start', justifyContent: 'space-between', marginBottom: 16 }}>
                    <div>
                        {title && <h3 style={{ fontSize: 16, fontWeight: 600, margin: 0, color: '#111827' }}>{title}</h3>}
                        {description && <p style={{ fontSize: 13, color: '#6b7280', margin: '4px 0 0' }}>{description}</p>}
                    </div>
                    {action && <div>{action}</div>}
                </div>
            )}
            {children}
        </div>
    );
}
