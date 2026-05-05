export function Toggle({ enabled, onChange, label, disabled = false }) {
    return (
        <label style={{ display: 'flex', alignItems: 'center', gap: 10, cursor: disabled ? 'not-allowed' : 'pointer', userSelect: 'none' }}>
            <span style={{
                position: 'relative',
                display: 'inline-block',
                width: 40,
                height: 22,
                flexShrink: 0,
            }}>
                <input
                    type="checkbox"
                    checked={!!enabled}
                    onChange={e => !disabled && onChange(e.target.checked)}
                    disabled={disabled}
                    style={{ opacity: 0, width: 0, height: 0, position: 'absolute' }}
                />
                <span style={{
                    position: 'absolute',
                    inset: 0,
                    background: enabled ? '#2563eb' : '#d1d5db',
                    borderRadius: 11,
                    transition: 'background .2s',
                }}>
                    <span style={{
                        position: 'absolute',
                        top: 3,
                        left: enabled ? 21 : 3,
                        width: 16,
                        height: 16,
                        background: '#fff',
                        borderRadius: '50%',
                        transition: 'left .2s',
                        boxShadow: '0 1px 3px rgba(0,0,0,.2)',
                    }} />
                </span>
            </span>
            {label && <span style={{ fontSize: 14, color: disabled ? '#9ca3af' : '#111827' }}>{label}</span>}
        </label>
    );
}
