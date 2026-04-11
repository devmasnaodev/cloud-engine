import { SiteCommandModal } from '@/components/site-command-modal';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useTranslations } from '@/hooks/useTranslations';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, router, usePage } from '@inertiajs/react';
import { Globe } from 'lucide-react';
import React, { useEffect, useState } from 'react';

interface SiteItem {
    id: number;
    domain: string;
    info: Record<string, unknown> | null;
    server: { id: number; name: string } | null;
    created_at: string;
}

interface Props {
    sites: SiteItem[];
}

export default function SitesIndex({ sites }: Props) {
    const t = useTranslations();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('servers'), href: '/servers' },
        { title: t('sites'), href: '/sites' },
    ];

    const { flash } = usePage<SharedData>().props;
    const [commandRunId, setCommandRunId] = useState<number | null>(null);
    const [deleting, setDeleting] = React.useState<number | null>(null);
    const [deleteModalId, setDeleteModalId] = React.useState<number | null>(
        null,
    );

    useEffect(() => {
        if (flash?.commandRunId) {
            setCommandRunId(flash.commandRunId);
        }
    }, [flash?.commandRunId]);

    const handleDelete = (id: number) => {
        setDeleteModalId(id);
    };

    const confirmDelete = (id: number) => {
        setDeleting(id);

        router.delete(`/sites/${id}`, {
            preserveScroll: true,
            onFinish: () => {
                setDeleting(null);
                setDeleteModalId(null);
            },
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('sites')} />

            <div className="p-6">
                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <div>
                                <CardTitle>{t('sites')}</CardTitle>
                                <CardDescription>
                                    {t('all_sites_across_servers')}
                                </CardDescription>
                            </div>
                            <Button size="sm" asChild>
                                <a href="/sites/create">{t('create_site')}</a>
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent>
                        {sites.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                {t('no_sites_found')}
                            </p>
                        ) : (
                            <div className="space-y-2">
                                {sites.map((site) => (
                                    <div
                                        key={site.id}
                                        className="flex items-center justify-between rounded border p-3"
                                    >
                                        <div>
                                            <div className="flex items-center gap-2">
                                                <Globe className="size-4 text-muted-foreground" />
                                                <span className="font-mono">
                                                    {site.domain}
                                                </span>
                                            </div>
                                            <div className="mt-0.5 text-sm text-muted-foreground">
                                                {site.server?.name ??
                                                    t('unknown_server')}{' '}
                                                — {site.created_at}
                                            </div>
                                        </div>

                                        <div className="flex gap-2">
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                asChild
                                            >
                                                <a
                                                    href={`/servers/${site.server?.id}`}
                                                >
                                                    {t('open_server')}
                                                </a>
                                            </Button>
                                            <Button size="sm" asChild>
                                                <a href={`/sites/${site.id}`}>
                                                    {t('manage')}
                                                </a>
                                            </Button>
                                            <Button
                                                size="sm"
                                                variant="destructive"
                                                onClick={() =>
                                                    handleDelete(site.id)
                                                }
                                                disabled={deleting === site.id}
                                            >
                                                {deleting === site.id
                                                    ? t('deleting')
                                                    : t('delete')}
                                            </Button>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>

                <Dialog
                    open={Boolean(deleteModalId)}
                    onOpenChange={(open) => {
                        if (!open) setDeleteModalId(null);
                    }}
                >
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>
                                {t('delete_site_confirm')}
                            </DialogTitle>
                        </DialogHeader>
                        <p className="text-sm text-muted-foreground">
                            {t('delete_site_warning')}
                        </p>
                        <DialogFooter>
                            <Button
                                variant="outline"
                                onClick={() => setDeleteModalId(null)}
                            >
                                {t('cancel')}
                            </Button>
                            <Button
                                variant="destructive"
                                onClick={() =>
                                    confirmDelete(deleteModalId as number)
                                }
                                disabled={deleting !== null}
                            >
                                {deleting !== null
                                    ? t('deleting')
                                    : t('delete')}
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>

            <SiteCommandModal
                runId={commandRunId}
                onClose={() => setCommandRunId(null)}
                onSuccess={() => {
                    setCommandRunId(null);
                    router.reload();
                }}
            />
        </AppLayout>
    );
}
