export function BannerPreview({ settings = {} }) {
    const bg      = settings.background_color || '#ffffff';
    const primary = settings.primary_color    || '#2563eb';
    const text    = settings.text_color       || '#1f2937';
    const accept  = settings.accept_label     || 'Accept All';
    const reject  = settings.reject_label     || 'Reject All';
    const custom  = settings.customize_label  || 'Customize';
    const showRej = settings.show_reject_button !== '0';

    const wrapStyle = {
        position: 'sticky',
        top: 20,
        border: '1px solid #e5e7eb',
        borderRadius: 8,
        overflow: 'hidden',
        background: '#f9fafb',
    };

    const bannerStyle = {
        background: bg,
        padding: '16px 20px',
        borderTop: '1px solid #e5e7eb',
        color: text,
        fontFamily: 'sans-serif',
    };

    return (
        <div style={wrapStyle}>
            <div style={{ padding: '10px 14px', background: '#f3f4f6', fontSize: 11, fontWeight: 600, color: '#6b7280', textTransform: 'uppercase', letterSpacing: '.04em' }}>
                Preview — {settings.position?.replace('_', ' ') || 'bottom bar'}
            </div>
            <div style={{ background: '#eef2f7', height: 120, display: 'flex', alignItems: 'center', justifyContent: 'center', color: '#9ca3af', fontSize: 12 }}>
                Your website content
            </div>
            <div style={bannerStyle}>
                <div style={{ fontSize: 13, fontWeight: 600, marginBottom: 4, color: text }}>We value your privacy</div>
                <div style={{ fontSize: 11, color: '#6b7280', marginBottom: 12 }}>
                    We use cookies for analytics and marketing. Under the Digital Omnibus framework, analytics cookies are opt-out.
                </div>
                <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                    <button style={{ background: primary, color: '#fff', border: 'none', padding: '7px 14px', borderRadius: 5, fontSize: 12, fontWeight: 500, cursor: 'pointer' }}>{accept}</button>
                    {showRej && <button style={{ background: '#fff', color: text, border: '1px solid #d1d5db', padding: '7px 14px', borderRadius: 5, fontSize: 12, fontWeight: 500, cursor: 'pointer' }}>{reject}</button>}
                    <button style={{ background: 'transparent', color: '#6b7280', border: 'none', padding: '7px 10px', borderRadius: 5, fontSize: 11, cursor: 'pointer' }}>{custom}</button>
                </div>
            </div>
        </div>
    );
}
