import { SiteCommandModal } from '@/components/site-command-modal';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslations } from '@/hooks/useTranslations';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, router, usePage } from '@inertiajs/react';
import {
    AlertCircle,
    ArrowLeft,
    CheckCircle2,
    ChevronDown,
    Clock,
    ExternalLink,
    Globe,
    History,
    Loader2,
    PauseCircle,
    Pencil,
    PlayCircle,
    RefreshCw,
    Server,
    Trash2,
    XCircle,
} from 'lucide-react';
import { useEffect, useState } from 'react';

interface SiteInfo {
    site?: string;
    site_type?: string;
    php_version?: string;
    ssl?: string | boolean;
    cache_nginx_fullpage?: string;
    cache_nginx_fastcgi?: string;
    db_name?: string;
    db_user?: string;
    site_enabled?: boolean | string;
    [key: string]: unknown;
}

interface Site {
    id: number;
    domain: string;
    info: SiteInfo | null;
    created_at: string;
}

interface SiteCommandRun {
    id: number;
    action: string;
    domain: string;
    status: 'pending' | 'running' | 'completed' | 'failed';
    stdout: string | null;
    stderr: string | null;
    partialStdout: string | null;
    exitStatus: number | null;
    duration: number | null;
    startedAt: string | null;
    completedAt: string | null;
}

interface SiteServer {
    id: number;
    name: string;
    ip_address: string;
    provisioning_engine: string | null;
}

interface Props {
    site: Site;
    server: SiteServer | null;
    siteCommandRuns: SiteCommandRun[];
}

function InfoRow({ label, value }: { label: string; value: React.ReactNode }) {
    return (
        <div className="flex items-center justify-between py-1.5">
            <span className="text-sm text-muted-foreground">{label}</span>
            <span className="text-sm font-medium">{value}</span>
        </div>
    );
}

function RunStatusIcon({ status }: { status: string }) {
    if (status === 'completed')
        return <CheckCircle2 className="size-4 shrink-0 text-green-500" />;
    if (status === 'failed')
        return <XCircle className="size-4 shrink-0 text-destructive" />;
    if (status === 'running' || status === 'pending')
        return (
            <Loader2 className="size-4 shrink-0 animate-spin text-primary" />
        );
    return <Clock className="size-4 shrink-0 text-muted-foreground" />;
}

function siteStatusVariant(
    info: SiteInfo | null,
): 'default' | 'secondary' | 'destructive' {
    if (!info) return 'secondary';
    const enabled = normalizeEnabledValue(info.site_enabled);
    if (enabled) return 'default';
    return 'secondary';
}

function siteStatusLabel(
    info: SiteInfo | null,
    t: (key: string, fallback?: string) => string,
): string {
    if (!info) return t('unknown');
    const enabled = normalizeEnabledValue(info.site_enabled);
    if (enabled) return t('enabled');
    return t('disabled');
}

function isEnabled(info: SiteInfo | null): boolean {
    if (!info) return false;
    return normalizeEnabledValue(
        info.site_enabled ?? info.status ?? info.site_status ?? info.state,
    );
}

function normalizeEnabledValue(value: unknown): boolean {
    if (value === true || value === 1 || value === '1') {
        return true;
    }

    if (typeof value !== 'string') {
        return false;
    }

    const normalized = value.trim().toLowerCase();

    return ['true', 'enabled', 'active', 'activated', 'on', 'yes'].includes(
        normalized,
    );
}

export default function SiteShow({ site, server, siteCommandRuns }: Props) {
    const t = useTranslations();
    const { flash } = usePage<SharedData>().props;
    const [commandRunId, setCommandRunId] = useState<number | null>(null);
    const [openRunId, setOpenRunId] = useState<number | null>(null);
    const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
    const [deleteConfirmation, setDeleteConfirmation] = useState('');

    useEffect(() => {
        if (flash?.commandRunId) {
            setCommandRunId(flash.commandRunId);
        }
    }, [flash?.commandRunId]);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('servers'), href: '/servers' },
        { title: t('sites'), href: '/sites' },
        { title: site.domain, href: `/sites/${site.id}` },
    ];

    const handleEnable = () => {
        router.post(`/sites/${site.id}/enable`, {}, { preserveScroll: true });
    };

    const handleDisable = () => {
        router.post(`/sites/${site.id}/disable`, {}, { preserveScroll: true });
    };

    const handleClean = () => {
        router.post(`/sites/${site.id}/clean`, {}, { preserveScroll: true });
    };

    const handleRefresh = () => {
        if (!server) return;
        fetch(
            `/servers/${server.id}/sites/${encodeURIComponent(site.domain)}`,
            {
                headers: { Accept: 'application/json' },
            },
        ).then(() => {
            router.reload();
        });
    };

    const info = site.info;
    const siteIsEnabled = isEnabled(info);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={site.domain} />
            <div className="flex h-full flex-1 flex-col gap-6 p-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Button variant="outline" size="icon" asChild>
                            <a href="/sites">
                                <ArrowLeft className="size-4" />
                            </a>
                        </Button>
                        <div>
                            <div className="flex items-center gap-2">
                                <Globe className="size-5 text-muted-foreground" />
                                <h1 className="text-2xl font-semibold tracking-tight">
                                    {site.domain}
                                </h1>
                                <Badge variant={siteStatusVariant(info)}>
                                    {siteStatusLabel(info, t)}
                                </Badge>
                            </div>
                            {server && (
                                <p className="ml-9 text-sm text-muted-foreground">
                                    {server.name} — {server.ip_address}
                                </p>
                            )}
                        </div>
                    </div>

                    {/* Actions */}
                    <div className="flex gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={handleRefresh}
                            disabled={!server}
                        >
                            <RefreshCw className="size-4" />
                            {t('refresh_info')}
                        </Button>

                        {server && (
                            <Button variant="outline" size="sm" asChild>
                                <a href={`/sites/${site.id}/edit`}>
                                    <Pencil className="size-4" />
                                    {t('edit')}
                                </a>
                            </Button>
                        )}

                        {server &&
                            (siteIsEnabled ? (
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={handleDisable}
                                >
                                    <PauseCircle className="size-4" />
                                    {t('disable')}
                                </Button>
                            ) : (
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={handleEnable}
                                >
                                    <PlayCircle className="size-4" />
                                    {t('enable')}
                                </Button>
                            ))}

                        {server && (
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={handleClean}
                            >
                                <RefreshCw className="size-4" />
                                {t('clear_site_cache', 'Clear site cache')}
                            </Button>
                        )}

                        <Dialog
                            open={deleteDialogOpen}
                            onOpenChange={(open) => {
                                setDeleteDialogOpen(open);
                                if (!open) setDeleteConfirmation('');
                            }}
                        >
                            <DialogTrigger asChild>
                                <Button variant="destructive" size="sm">
                                    <Trash2 className="size-4" />
                                    {t('delete')}
                                </Button>
                            </DialogTrigger>
                            <DialogContent>
                                <DialogHeader>
                                    <DialogTitle>
                                        {t('delete_site')}
                                    </DialogTitle>
                                    <DialogDescription>
                                        {t('delete_site_warning_1')}{' '}
                                        <strong>{site.domain}</strong>{' '}
                                        {t('delete_site_warning_2')}
                                    </DialogDescription>
                                </DialogHeader>
                                <div className="grid gap-2 py-2">
                                    <Label htmlFor="delete-confirm">
                                        {t(
                                            'type_domain_to_confirm',
                                            'Type the site domain to confirm',
                                        )}{' '}
                                        <strong>{site.domain}</strong>
                                    </Label>
                                    <Input
                                        id="delete-confirm"
                                        value={deleteConfirmation}
                                        onChange={(e) =>
                                            setDeleteConfirmation(
                                                e.target.value,
                                            )
                                        }
                                        placeholder={site.domain}
                                        autoComplete="off"
                                    />
                                </div>
                                <DialogFooter>
                                    <Button
                                        variant="outline"
                                        onClick={() => {
                                            setDeleteDialogOpen(false);
                                            setDeleteConfirmation('');
                                        }}
                                    >
                                        {t('cancel')}
                                    </Button>
                                    <Button
                                        variant="destructive"
                                        disabled={
                                            deleteConfirmation !== site.domain
                                        }
                                        onClick={() => {
                                            router.delete(`/sites/${site.id}`, {
                                                preserveScroll: true,
                                            });
                                            setDeleteDialogOpen(false);
                                            setDeleteConfirmation('');
                                        }}
                                    >
                                        {t('delete_site')}
                                    </Button>
                                </DialogFooter>
                            </DialogContent>
                        </Dialog>
                    </div>
                </div>

                {/* Flash messages */}
                {flash?.success && (
                    <Alert>
                        <AlertDescription>{flash.success}</AlertDescription>
                    </Alert>
                )}
                {flash?.error && (
                    <Alert variant="destructive">
                        <AlertCircle className="size-4" />
                        <AlertDescription>{flash.error}</AlertDescription>
                    </Alert>
                )}

                <div className="grid gap-6 md:grid-cols-2">
                    {/* Site Details */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Globe className="size-4" />
                                {t('site_details')}
                            </CardTitle>
                            <CardDescription>
                                {t('configuration_from_last_refresh')}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="divide-y">
                            <InfoRow
                                label={t('domain')}
                                value={
                                    <span className="flex items-center gap-1 font-mono">
                                        {site.domain}
                                        <a
                                            href={`http://${site.domain}`}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            className="text-muted-foreground hover:text-foreground"
                                        >
                                            <ExternalLink className="size-3" />
                                        </a>
                                    </span>
                                }
                            />
                            <InfoRow
                                label={t('site_type')}
                                value={
                                    info?.site_type ?? (
                                        <span className="text-muted-foreground">
                                            —
                                        </span>
                                    )
                                }
                            />
                            <InfoRow
                                label={t('php_version')}
                                value={
                                    info?.php_version ? (
                                        <Badge variant="outline">
                                            PHP {info.php_version}
                                        </Badge>
                                    ) : (
                                        <span className="text-muted-foreground">
                                            —
                                        </span>
                                    )
                                }
                            />
                            <InfoRow
                                label={t('ssl_certificate')}
                                value={
                                    info?.ssl ? (
                                        <Badge variant="outline">
                                            {String(info.ssl)}
                                        </Badge>
                                    ) : (
                                        <span className="text-muted-foreground">
                                            {t('none')}
                                        </span>
                                    )
                                }
                            />
                            <InfoRow
                                label={t('cache_nginx')}
                                value={
                                    info?.cache_nginx_fullpage ??
                                    info?.cache_nginx_fastcgi ?? (
                                        <span className="text-muted-foreground">
                                            —
                                        </span>
                                    )
                                }
                            />
                            <InfoRow
                                label={t('database')}
                                value={
                                    info?.db_name ? (
                                        <span className="font-mono text-xs">
                                            {info.db_name}
                                        </span>
                                    ) : (
                                        <span className="text-muted-foreground">
                                            —
                                        </span>
                                    )
                                }
                            />
                            <InfoRow
                                label={t('added')}
                                value={site.created_at}
                            />
                        </CardContent>
                        {info && (
                            <div className="px-6 pb-4">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    asChild
                                    className="w-full"
                                >
                                    <a href={`/sites/${site.id}/info`}>
                                        <ExternalLink className="size-3" />
                                        {t(
                                            'see_full_details',
                                            'Ver detalhes completos',
                                        )}
                                    </a>
                                </Button>
                            </div>
                        )}
                    </Card>

                    {/* Server */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Server className="size-4" />
                                {t('server')}
                            </CardTitle>
                            <CardDescription>
                                {t('server_hosting_this_site')}
                            </CardDescription>
                        </CardHeader>
                        {server ? (
                            <CardContent className="space-y-4">
                                <div className="divide-y">
                                    <InfoRow
                                        label={t('name')}
                                        value={server.name}
                                    />
                                    <InfoRow
                                        label={t('ip_address')}
                                        value={
                                            <span className="font-mono text-sm">
                                                {server.ip_address}
                                            </span>
                                        }
                                    />
                                    <InfoRow
                                        label={t('engine')}
                                        value={
                                            <Badge
                                                variant={
                                                    server.provisioning_engine
                                                        ? 'outline'
                                                        : 'secondary'
                                                }
                                            >
                                                {server.provisioning_engine ??
                                                    t('no_engine')}
                                            </Badge>
                                        }
                                    />
                                </div>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    asChild
                                    className="w-full"
                                >
                                    <a href={`/servers/${server.id}`}>
                                        {t('open_server')}
                                        <ExternalLink className="size-3" />
                                    </a>
                                </Button>
                            </CardContent>
                        ) : (
                            <CardContent>
                                <p className="text-sm text-muted-foreground">
                                    {t('no_server_associated')}
                                </p>
                            </CardContent>
                        )}
                    </Card>
                </div>

                {/* Site Command History */}
                {siteCommandRuns.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <History className="size-4" />
                                {t('site_command_history')}
                            </CardTitle>
                            <CardDescription>
                                {t('recent_commands_executed')}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-2">
                            {siteCommandRuns.map((run) => {
                                const isRunning =
                                    run.status === 'pending' ||
                                    run.status === 'running';
                                const output =
                                    run.stdout ?? run.partialStdout ?? '';
                                return (
                                    <Collapsible key={run.id}>
                                        <div className="rounded-lg border">
                                            <CollapsibleTrigger className="flex w-full items-center justify-between p-3 text-left hover:bg-muted/50">
                                                <div className="flex items-center gap-3">
                                                    <RunStatusIcon
                                                        status={run.status}
                                                    />
                                                    <div>
                                                        <span className="text-sm font-medium capitalize">
                                                            {run.action.replace(
                                                                /_/g,
                                                                ' ',
                                                            )}
                                                        </span>
                                                    </div>
                                                </div>
                                                <div className="flex items-center gap-3">
                                                    {isRunning && (
                                                        <button
                                                            type="button"
                                                            className="text-xs text-primary underline underline-offset-2"
                                                            onClick={(e) => {
                                                                e.stopPropagation();
                                                                setOpenRunId(
                                                                    run.id,
                                                                );
                                                            }}
                                                        >
                                                            {t('watch_live')}
                                                        </button>
                                                    )}
                                                    <span className="text-xs text-muted-foreground">
                                                        {run.completedAt
                                                            ? new Date(
                                                                  run.completedAt,
                                                              ).toLocaleString()
                                                            : run.startedAt
                                                              ? new Date(
                                                                    run.startedAt,
                                                                ).toLocaleString()
                                                              : '—'}
                                                    </span>
                                                    {run.duration != null && (
                                                        <span className="text-xs text-muted-foreground">
                                                            {run.duration.toFixed(
                                                                1,
                                                            )}
                                                            s
                                                        </span>
                                                    )}
                                                    <ChevronDown className="size-4 text-muted-foreground transition-transform [[data-state=open]_&]:rotate-180" />
                                                </div>
                                            </CollapsibleTrigger>
                                            {output && (
                                                <CollapsibleContent>
                                                    <div className="border-t bg-zinc-950 p-3">
                                                        <pre className="max-h-64 overflow-y-auto font-mono text-xs break-all whitespace-pre-wrap text-green-400">
                                                            {output}
                                                        </pre>
                                                    </div>
                                                </CollapsibleContent>
                                            )}
                                            {run.stderr &&
                                                run.status === 'failed' && (
                                                    <CollapsibleContent>
                                                        <div className="border-t bg-red-950/20 p-3">
                                                            <pre className="max-h-40 overflow-y-auto font-mono text-xs break-all whitespace-pre-wrap text-red-400">
                                                                {run.stderr}
                                                            </pre>
                                                        </div>
                                                    </CollapsibleContent>
                                                )}
                                        </div>
                                    </Collapsible>
                                );
                            })}
                        </CardContent>
                    </Card>
                )}

                {/* Raw Info */}
                {info && (
                    <Card>
                        <Collapsible>
                            <CollapsibleTrigger asChild>
                                <CardHeader className="cursor-pointer select-none">
                                    <div className="flex items-center justify-between">
                                        <div>
                                            <CardTitle className="text-base">
                                                {t('raw_site_info')}
                                            </CardTitle>
                                            <CardDescription>
                                                {t('full_json_from_easyengine')}
                                            </CardDescription>
                                        </div>
                                        <ChevronDown className="size-4 text-muted-foreground" />
                                    </div>
                                </CardHeader>
                            </CollapsibleTrigger>
                            <CollapsibleContent>
                                <CardContent>
                                    <pre className="overflow-x-auto rounded-md bg-muted p-4 text-xs">
                                        {JSON.stringify(info, null, 2)}
                                    </pre>
                                </CardContent>
                            </CollapsibleContent>
                        </Collapsible>
                    </Card>
                )}
            </div>

            <SiteCommandModal
                runId={commandRunId ?? openRunId}
                onClose={() => {
                    setCommandRunId(null);
                    setOpenRunId(null);
                }}
                onSuccess={(action) => {
                    setCommandRunId(null);
                    setOpenRunId(null);
                    if (action === 'delete_site') {
                        router.visit('/sites');
                    } else {
                        router.reload();
                    }
                }}
            />
        </AppLayout>
    );
}
