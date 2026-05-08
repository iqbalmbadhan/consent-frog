import { useSettings } from '../../hooks/useSettings';
import { Card } from '../shared/Card';
import { Toggle } from '../shared/Toggle';
import { BannerPreview } from './BannerPreview';

const POSITIONS = [
    { value: 'bottom_bar',    label: 'Bottom Bar' },
    { value: 'center_modal',  label: 'Center Modal' },
    { value: 'sidebar',       label: 'Sidebar' },
    { value: 'minimal',       label: 'Minimal' },
];

const ANIMATIONS = [
    { value: 'slide_up', label: 'Slide Up' },
    { value: 'fade',     label: 'Fade In' },
    { value: 'none',     label: 'No Animation' },
];

function Field({ label, children, hint }) {
    return (
        <div style={{ marginBottom: 16 }}>
            <label style={{ display: 'block', fontSize: 13, fontWeight: 500, color: '#374151', marginBottom: 6 }}>{label}</label>
            {children}
            {hint && <div style={{ fontSize: 11, color: '#9ca3af', marginTop: 4 }}>{hint}</div>}
        </div>
    );
}

const input = { padding: '8px 12px', border: '1px solid #d1d5db', borderRadius: 6, fontSize: 13, width: '100%', boxSizing: 'border-box' };

export function BannerDesigner() {
    const { settings, saving, saved, error, updateSettings } = useSettings('banner');

    const set = (key, value) => updateSettings({ [key]: value });

    return (
        <div style={{ display: 'grid', gridTemplateColumns: '1fr 400px', gap: 20, alignItems: 'start' }}>
            <div>
                <Card title="Banner Position">
                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 8 }}>
                        {POSITIONS.map(p => (
                            <button
                                key={p.value}
                                onClick={() => set('position', p.value)}
                                style={{
                                    padding: '12px 16px',
                                    borderRadius: 8,
                                    border: `2px solid ${settings.position === p.value ? '#2563eb' : '#e5e7eb'}`,
                                    background: settings.position === p.value ? '#eff6ff' : '#fff',
                                    color: settings.position === p.value ? '#1d4ed8' : '#374151',
                                    fontWeight: 500,
                                    fontSize: 13,
                                    cursor: 'pointer',
                                    textAlign: 'center',
                                }}
                            >{p.label}</button>
                        ))}
                    </div>
                </Card>

                <Card title="Colors">
                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 16 }}>
                        {[
                            ['primary_color', 'Primary Color'],
                            ['background_color', 'Background'],
                            ['text_color', 'Text Color'],
                        ].map(([key, label]) => (
                            <Field key={key} label={label}>
                                <div style={{ display: 'flex', gap: 8, alignItems: 'center' }}>
                                    <input type="color" value={settings[key] || '#000000'} onChange={e => set(key, e.target.value)} style={{ width: 40, height: 36, border: '1px solid #d1d5db', borderRadius: 6, padding: 2, cursor: 'pointer' }} />
                                    <input type="text" value={settings[key] || ''} onChange={e => set(key, e.target.value)} style={{ ...input, width: 'auto', flex: 1 }} />
                                </div>
                            </Field>
                        ))}
                    </div>
                </Card>

                <Card title="Button Labels">
                    {[
                        ['accept_label', 'Accept Button'],
                        ['reject_label', 'Reject Button'],
                        ['customize_label', 'Customize Button'],
                        ['save_label', 'Save Preferences Button'],
                    ].map(([key, label]) => (
                        <Field key={key} label={label}>
                            <input type="text" value={settings[key] || ''} onChange={e => set(key, e.target.value)} style={input} />
                        </Field>
                    ))}
                </Card>

                <Card title="Options">
                    <div style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
                        <Toggle label="Show Reject All button" enabled={settings.show_reject_button === '1'} onChange={v => set('show_reject_button', v ? '1' : '0')} />
                        <Field label="Animation">
                            <select value={settings.animation || 'slide_up'} onChange={e => set('animation', e.target.value)} style={{ ...input, width: 'auto' }}>
                                {ANIMATIONS.map(a => <option key={a.value} value={a.value}>{a.label}</option>)}
                            </select>
                        </Field>
                    </div>
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
            <BannerPreview settings={settings} />
        </div>
    );
}
