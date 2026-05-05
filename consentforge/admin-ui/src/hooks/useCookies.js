import { useState, useEffect, useCallback } from '@wordpress/element';
import { adminApi } from '../utils/api';

export function useCookies(page = 1, categoryFilter = '') {
    const [cookies,     setCookies]     = useState([]);
    const [total,       setTotal]       = useState(0);
    const [totalPages,  setTotalPages]  = useState(1);
    const [loading,     setLoading]     = useState(true);
    const [error,       setError]       = useState(null);

    const fetchCookies = useCallback(async () => {
        setLoading(true);
        setError(null);
        try {
            const data = await adminApi.getCookies(page, categoryFilter);
            setCookies(data.cookies || []);
            setTotal(data.total || 0);
            setTotalPages(data.total_pages || 1);
        } catch (e) {
            setError(e?.message || 'Failed to load cookies.');
        } finally {
            setLoading(false);
        }
    }, [page, categoryFilter]);

    const createCookie = useCallback(async (data) => {
        await adminApi.createCookie(data);
        fetchCookies();
    }, [fetchCookies]);

    const updateCookie = useCallback(async (id, data) => {
        setCookies(cs => cs.map(c => c.id === id ? { ...c, ...data } : c));
        try {
            await adminApi.updateCookie(id, data);
        } catch {
            fetchCookies(); // revert on error
        }
    }, [fetchCookies]);

    const deleteCookie = useCallback(async (id) => {
        setCookies(cs => cs.filter(c => c.id !== id));
        try {
            await adminApi.deleteCookie(id);
        } catch {
            fetchCookies();
        }
    }, [fetchCookies]);

    useEffect(() => { fetchCookies(); }, [fetchCookies]);

    return { cookies, total, totalPages, loading, error, createCookie, updateCookie, deleteCookie, refetch: fetchCookies };
}
