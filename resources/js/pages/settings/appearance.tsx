import { Head } from '@inertiajs/react';

import AppearanceTabs from '@/components/appearance-tabs';
import HeadingSmall from '@/components/heading-small';
import { useTranslations } from '@/hooks/useTranslations';
import { type BreadcrumbItem } from '@/types';

import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { edit as editAppearance } from '@/routes/appearance';

export default function Appearance() {
    const t = useTranslations();

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: t('appearance_settings'),
            href: editAppearance().url,
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('appearance_settings')} />

            <SettingsLayout>
                <div className="space-y-6">
                    <HeadingSmall
                        title={t('appearance_settings')}
                        description={t(
                            'update_account_appearance_settings',
                            "Update your account's appearance settings",
                        )}
                    />
                    <AppearanceTabs />
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
