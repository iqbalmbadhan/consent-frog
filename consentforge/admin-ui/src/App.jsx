import { useState } from '@wordpress/element';
import { Card } from './components/shared/Card';
import { ComplianceScore } from './components/Dashboard/ComplianceScore';
import { ConsentStats } from './components/Dashboard/ConsentStats';
import { RecentActivity } from './components/Dashboard/RecentActivity';
import { ScannerPanel } from './components/Scanner/ScannerPanel';
import { CookieList } from './components/Scanner/CookieList';
import { BannerDesigner } from './components/Banner/BannerDesigner';
import { DSARList } from './components/DSAR/DSARList';
import { GeneralSettings } from './components/Settings/GeneralSettings';

const data    = window.CFAdminData || {};
const features = data.features || {};

const TABS = [
    { id: 'dashboard', label: '📊 Dashboard' },
    { id: 'scanner',   label: '🔍 Cookie Scanner' },
    { id: 'banner',    label: '🎨 Banner Designer' },
    { id: 'dsar',      label: '📋 DSAR Requests' },
    { id: 'settings',  label: '⚙️ Settings' },
];

export default function App() {
    const hash    = window.location.hash.replace('#', '') || 'dashboard';
    const initial = TABS.find(t => t.id === hash)?.id || 'dashboard';
    const [tab, setTab] = useState(initial);

    const navStyle = (id) => ({
        padding: '10px 16px',
        borderBottom: tab === id ? '3px solid #2563eb' : '3px solid transparent',
        color: tab === id ? '#2563eb' : '#6b7280',
        fontWeight: tab === id ? 600 : 400,
        fontSize: 13,
        cursor: 'pointer',
        background: 'none',
        border: 'none',
        borderBottom: tab === id ? '3px solid #2563eb' : '3px solid transparent',
        marginBottom: -1,
    });

    return (
        <div style={{ fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif', color: '#111827', maxWidth: 1200, padding: '20px 0' }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: 16, marginBottom: 24 }}>
                <div style={{ background: '#2563eb', color: '#fff', padding: '8px 14px', borderRadius: 8, fontSize: 16, fontWeight: 700 }}>🛡 ConsentForge</div>
                <div style={{ fontSize: 12, color: '#9ca3af' }}>v{data.version} · {data.plan || 'Free'} Plan</div>
            </div>

            <div style={{ display: 'flex', borderBottom: '1px solid #e5e7eb', marginBottom: 24, gap: 4 }}>
                {TABS.map(t => (
                    <button key={t.id} style={navStyle(t.id)} onClick={() => setTab(t.id)}>{t.label}</button>
                ))}
            </div>

            {tab === 'dashboard' && (
                <div>
                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 20, marginBottom: 20 }}>
                        <Card title="Compliance Score" description="Based on your current configuration"><ComplianceScore /></Card>
                        <Card title="Consent Statistics" description="Last 30 days"><ConsentStats /></Card>
                    </div>
                    <Card title="Recent Consent Activity"><RecentActivity /></Card>
                </div>
            )}

            {tab === 'scanner' && (
                <div>
                    <ScannerPanel />
                    <CookieList />
                </div>
            )}

            {tab === 'banner' && <BannerDesigner />}

            {tab === 'dsar' && <DSARList enabled={features.dsar_handling} />}

            {tab === 'settings' && <GeneralSettings />}
        </div>
    );
}
