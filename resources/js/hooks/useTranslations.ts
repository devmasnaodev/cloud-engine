import { SharedData } from '@/types';
import { usePage } from '@inertiajs/react';

/**
 * Hook to access translated strings throughout the application.
 *
 * Usage:
 *   const t = useTranslations();
 *   <button>{t('save')}</button>
 *   <h1>{t('profile_settings')}</h1>
 *
 * @returns Function to retrieve translated string by key
 */
export function useTranslations() {
    const { props } = usePage<{ props: SharedData }>();
    const translations = (props as unknown as SharedData).translations || {};

    return (key: string, fallback?: string): string => {
        return translations[key] || fallback || key;
    };
}
