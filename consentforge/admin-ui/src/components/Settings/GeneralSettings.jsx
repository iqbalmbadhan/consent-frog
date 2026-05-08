import { useSettings } from '../../hooks/useSettings';
import { Card } from '../shared/Card';
import { Toggle } from '../shared/Toggle';

const input = { padding: '8px 12px', border: '1px solid #d1d5db', borderRadius: 6, fontSize: 13 };

function Field({ label, hint, children }) {
    return (
        <div style={{ marginBottom: 18 }}>
            <label style={{ display: 'block', fontSize: 13, fontWeight: 500, color: '#374151', marginBottom: 6 }}>{label}</label>
            {children}
            {hint && <div style={{ fontSize: 11, color: '#9ca3af', marginTop: 4 }}>{hint}</div>}
        </div>
    );
}

export function GeneralSettings() {
    const { settings, saving, saved, error, updateSettings } = useSettings('general');
    const { settings: compliance, updateSettings: updateCompliance } = useSettings('compliance');

    const set = (key, value) => updateSettings({ [key]: value });

    return (
        <div>
            <Card title="Digital Omnibus Consent Model" description="Under the EU Digital Omnibus (Feb 2026), analytics cookies use opt-out and marketing cookies use opt-in.">
                <div style={{ background: '#eff6ff', border: '1px solid #bfdbfe', borderRadius: 8, padding: 16, marginBottom: 16, fontSize: 13, color: '#1d4ed8' }}>
                    <strong>✓ Digital Omnibus mode is active.</strong> Analytics and performance cookies default to ON (opt-out). Marketing cookies default to OFF (opt-in).
                </div>
                <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
                    <Toggle label="Enable GPC (Global Privacy Control) — legally binding opt-out signal" enabled={settings.gpc_enabled === '1'} onChange={v => set('gpc_enabled', v ? '1' : '0')} />
                    <Toggle label="Respect DNT (Do Not Track) as secondary signal" enabled={settings.respect_dnt === '1'} onChange={v => set('respect_dnt', v ? '1' : '0')} />
                </div>
            </Card>

            <Card title="Cookie & Data Settings">
                <Field label="Consent cookie lifetime (days)" hint="Default: 365 days. After this period, the consent banner will reappear.">
                    <input type="number" value={settings.cookie_lifetime || '365'} onChange={e => set('cookie_lifetime', e.target.value)} style={{ ...input, width: 120 }} min={1} max={730} />
                </Field>
                <Field label="Data retention period (days)" hint="Consent logs older than this will be automatically deleted. Default: 1095 (3 years).">
                    <input type="number" value={compliance.data_retention_days || '1095'} onChange={e => updateCompliance({ data_retention_days: e.target.value })} style={{ ...input, width: 120 }} min={90} />
                </Field>
            </Card>

            <Card title="Data Controller Information" description="Required for tamper-evident consent receipts (ISO/IEC 29184).">
                {[
                    ['controller_name',    'Organisation Name',   'text'],
                    ['controller_email',   'DPO Email Address',   'email'],
                    ['controller_address', 'Business Address',    'text'],
                ].map(([key, label, type]) => (
                    <Field key={key} label={label}>
                        <input type={type} value={settings[key] || ''} onChange={e => set(key, e.target.value)} style={{ ...input, width: '100%', boxSizing: 'border-box' }} />
                    </Field>
                ))}
            </Card>

            <div style={{ display: 'flex', gap: 10, alignItems: 'center' }}>
                <button
                    onClick={() => updateSettings(settings)}
                    disabled={saving}
                    style={{ background: saving ? '#93c5fd' : '#2563eb', color: '#fff', border: 'none', padding: '10px 24px', borderRadius: 6, fontSize: 14, fontWeight: 500, cursor: saving ? 'not-allowed' : 'pointer' }}
                >{saving ? 'Saving…' : 'Save Changes'}</button>
                {saved && <span style={{ color: '#059669', fontSize: 13 }}>✓ Saved!</span>}
                {error && <span style={{ color: '#dc2626', fontSize: 13 }}>{error}</span>}
            </div>
        </div>
    );
}
