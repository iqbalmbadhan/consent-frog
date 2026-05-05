import { useState, useEffect, useCallback } from '@wordpress/element';
import { adminApi } from '../utils/api';

export function useSettings(group) {
    const [settings, setSettings] = useState({});
    const [loading,  setLoading]  = useState(true);
    const [saving,   setSaving]   = useState(false);
    const [error,    setError]    = useState(null);
    const [saved,    setSaved]    = useState(false);

    const fetchSettings = useCallback(async () => {
        setLoading(true);
        setError(null);
        try {
            const data = await adminApi.getSettings(group);
            setSettings(data || {});
        } catch (e) {
            setError(e?.message || 'Failed to load settings.');
        } finally {
            setLoading(false);
        }
    }, [group]);

    const updateSettings = useCallback(async (data) => {
        setSaving(true);
        setSaved(false);
        setError(null);
        const prev = { ...settings };
        setSettings(s => ({ ...s, ...data })); // optimistic
        try {
            await adminApi.updateSettings(group, data);
            setSaved(true);
            setTimeout(() => setSaved(false), 2000);
        } catch (e) {
            setSettings(prev); // revert
            setError(e?.message || 'Failed to save settings.');
        } finally {
            setSaving(false);
        }
    }, [group, settings]);

    useEffect(() => { fetchSettings(); }, [fetchSettings]);

    return { settings, loading, saving, saved, error, updateSettings, refetch: fetchSettings };
}
