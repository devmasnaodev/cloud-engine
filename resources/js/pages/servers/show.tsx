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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
// Checkbox removed — site admin moved to /sites
import { SiteCommandModal } from '@/components/site-command-modal';
import { useTranslations } from '@/hooks/useTranslations';
import AppLayout from '@/layouts/app-layout';
import { index as serversIndex } from '@/routes/servers';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, router, usePage } from '@inertiajs/react';
import {
    AlertCircle,
    ArrowLeft,
    CheckCircle2,
    ChevronDown,
    Clock,
    Edit,
    Globe,
    Loader2,
    Play,
    RefreshCw,
    Trash2,
    XCircle,
} from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';

interface Server {
    id: number;
    name: string;
    ip_address: string;
    ssh_port: number;
    ssh_execution_username: string;
    ssh_users: Array<{ username: string }>;
    provisioning_engine: string | null;
    is_active: boolean;
    last_connected_at: string | null;
    created_at: string;
}

interface Site {
    id: number | null;
    domain: string;
    status: string;
    imported: boolean;
    local_site_id: number | null;
}

interface LastRun {
    id: number;
    executionUsername: string | null;
    status: string;
    startedAt: string | null;
    completedAt: string | null;
    failureReason: string | null;
}

interface ProvisioningRecipeInfo {
    id: string;
    name: string;
    description: string;
    defaultExecutionUsername: string;
    allowsExecutionUserSelection: boolean;
    lastRun: LastRun | null;
}

interface StepResult {
    stepId: string;
    stepName: string;
    description: string;
    stdout: string;
    stderr: string;
    exitStatus: number;
    duration: number;
    successful: boolean;
    validationError?: string | null;
}

interface CurrentStep {
    stepId: string;
    stepName: string;
    stepIndex: number;
    totalSteps: number;
    partial_stdout: string;
}

interface ActiveRun {
    id: number;
    recipeId: string;
    recipeName: string;
    executionUsername: string | null;
    status: string;
    steps: StepResult[] | null;
    currentStep: CurrentStep | null;
    startedAt: string | null;
    completedAt: string | null;
    totalDuration: number | null;
    failureReason: string | null;
}

interface Props {
    server: Server;
    sites: Site[];
    error: string | null;
    recipes: ProvisioningRecipeInfo[];
    activeRun: ActiveRun | null;
}

function statusVariant(
    status: string,
): 'default' | 'secondary' | 'destructive' | 'outline' {
    if (status === 'completed') return 'default';
    if (status === 'failed') return 'destructive';
    if (status === 'running') return 'secondary';
    return 'outline';
}

/**
 * Return the trimmed output if it looks like real terminal output; return null
 * if the string is empty, whitespace-only, or just a bare number (phpseclib
 * sometimes leaks the SSH exit code — e.g. "1" — as the exec() return value
 * when the connection is closed before any stdout arrives, e.g. `reboot now`).
 */
function normalizeOutput(text: string | null | undefined): string | null {
    if (!text) return null;
    const trimmed = text.trim();
    if (!trimmed || /^\d+$/.test(trimmed)) return null;
    return trimmed;
}

function StatusIcon({ status }: { status: string }) {
    if (status === 'completed')
        return <CheckCircle2 className="size-4 text-green-500" />;
    if (status === 'failed')
        return <XCircle className="size-4 text-destructive" />;
    if (status === 'running' || status === 'pending')
        return <Loader2 className="size-4 animate-spin" />;
    return <Clock className="size-4 text-muted-foreground" />;
}

export default function ServersShow({
    server,
    sites,
    error,
    recipes,
    activeRun: initialActiveRun,
}: Props) {
    const t = useTranslations();
    const { flash } = usePage<SharedData>().props;
    const [commandRunId, setCommandRunId] = useState<number | null>(null);
    const [activeRun, setActiveRun] = useState<ActiveRun | null>(
        initialActiveRun,
    );
    const [selectedExecutionUsers, setSelectedExecutionUsers] = useState<
        Record<string, string>
    >(() =>
        Object.fromEntries(
            recipes.flatMap((recipe) => {
                if (!recipe.allowsExecutionUserSelection) {
                    return [];
                }

                const hasDefaultUser = server.ssh_users.some(
                    (sshUser) =>
                        sshUser.username === recipe.defaultExecutionUsername,
                );

                return hasDefaultUser
                    ? [[recipe.id, recipe.defaultExecutionUsername]]
                    : [];
            }),
        ),
    );
    const pollingRef = useRef<ReturnType<typeof setInterval> | null>(null);
    const [submitting, setSubmitting] = useState(false);
    const [importingDomain, setImportingDomain] = useState<string | null>(null);
    const configuredSshUsernames = server.ssh_users.map(
        (sshUser) => sshUser.username,
    );
    const hasProvisioningEngine = server.provisioning_engine !== null;

    // Open SiteCommandModal when a site command run ID arrives in flash.
    useEffect(() => {
        if (flash?.commandRunId) {
            setCommandRunId(flash.commandRunId);
        }
    }, [flash?.commandRunId]);

    const stopPolling = useCallback(() => {
        if (pollingRef.current !== null) {
            clearInterval(pollingRef.current);
            pollingRef.current = null;
        }
    }, []);

    // Sync Inertia prop → local state on every page visit (e.g. after router.post
    // dispatches a recipe and redirects back, or after router.reload completes).
    useEffect(() => {
        setActiveRun(initialActiveRun);
    }, [initialActiveRun?.id, initialActiveRun?.status]);

    const pollRun = useCallback(
        async (runId: number) => {
            try {
                const res = await fetch(
                    `/servers/${server.id}/provisioning/${runId}`,
                    {
                        headers: { Accept: 'application/json' },
                    },
                );
                if (!res.ok) return;
                const data = (await res.json()) as ActiveRun;
                setActiveRun(data);
                if (data.status === 'completed' || data.status === 'failed') {
                    stopPolling();
                    // Refresh only recipes and server info — do NOT reload activeRun so
                    // the completed step list remains visible until the user navigates away.
                    router.reload({ only: ['server', 'recipes'] });
                }
            } catch {
                // network error — keep polling
            }
        },
        [server.id, stopPolling],
    );

    // Start/stop polling whenever the active run id, status, or poll callback changes.
    // pollRun and stopPolling are stable memoized callbacks so they won't cause extra re-runs.
    useEffect(() => {
        stopPolling();
        if (
            activeRun &&
            (activeRun.status === 'pending' || activeRun.status === 'running')
        ) {
            pollingRef.current = setInterval(
                () => void pollRun(activeRun.id),
                2000,
            );
        }
        return () => stopPolling();
    }, [activeRun?.id, activeRun?.status, pollRun, stopPolling]);

    const runRecipe = (recipe: ProvisioningRecipeInfo) => {
        const requestedExecutionUsername = recipe.allowsExecutionUserSelection
            ? selectedExecutionUsers[recipe.id] || undefined
            : undefined;

        setSubmitting(true);
        router.post(
            `/servers/${server.id}/provisioning`,
            {
                recipe_id: recipe.id,
                execution_username: requestedExecutionUsername,
            },
            { preserveScroll: true, onFinish: () => setSubmitting(false) },
        );
    };

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('servers'), href: serversIndex().url },
        { title: server.name, href: `/servers/${server.id}` },
    ];

    const handleTestConnection = () => {
        router.post(
            `/servers/${server.id}/test-connection`,
            {},
            { preserveScroll: true },
        );
    };

    const handleImportSite = (domain: string) => {
        setImportingDomain(domain);
        router.post(
            `/servers/${server.id}/sites/${encodeURIComponent(domain)}/import`,
            {},
            {
                preserveScroll: true,
                onFinish: () => setImportingDomain(null),
            },
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={server.name} />
            <div className="flex h-full flex-1 flex-col gap-6 p-6">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Button variant="outline" size="icon" asChild>
                            <a href={serversIndex().url}>
                                <ArrowLeft className="size-4" />
                            </a>
                        </Button>
                        <div>
                            <div className="flex items-center gap-2">
                                <h1 className="text-2xl font-semibold tracking-tight">
                                    {server.name}
                                </h1>
                                <Badge
                                    variant={
                                        server.is_active
                                            ? 'default'
                                            : 'secondary'
                                    }
                                >
                                    {server.is_active
                                        ? t('active')
                                        : t('inactive')}
                                </Badge>
                            </div>
                            <p className="text-sm text-muted-foreground">
                                {server.ip_address}:{server.ssh_port}
                            </p>
                        </div>
                    </div>

                    <div className="flex gap-2">
                        <Button
                            variant="outline"
                            onClick={handleTestConnection}
                        >
                            <RefreshCw className="size-4" />
                            {t('test_connection', 'Test Connection')}
                        </Button>
                        <Button variant="outline" asChild>
                            <a href={`/servers/${server.id}/edit`}>
                                <Edit className="size-4" />
                                {t('edit')}
                            </a>
                        </Button>

                        <Dialog>
                            <DialogTrigger asChild>
                                <Button variant="destructive">
                                    <Trash2 className="size-4" />
                                    {t('delete')}
                                </Button>
                            </DialogTrigger>
                            <DialogContent>
                                <DialogHeader>
                                    <DialogTitle>
                                        {t('delete_server')}
                                    </DialogTitle>
                                    <DialogDescription>
                                        {t('delete_server_warning')}
                                    </DialogDescription>
                                </DialogHeader>
                                <DialogFooter>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() => {}}
                                    >
                                        {t('cancel')}
                                    </Button>
                                    <Button
                                        variant="destructive"
                                        onClick={() => {
                                            router.delete(
                                                `/servers/${server.id}`,
                                                {
                                                    preserveScroll: true,
                                                },
                                            );
                                        }}
                                    >
                                        {t('delete_server')}
                                    </Button>
                                </DialogFooter>
                            </DialogContent>
                        </Dialog>
                    </div>
                </div>

                {error && (
                    <Alert variant="destructive">
                        <AlertCircle className="size-4" />
                        <AlertDescription>{error}</AlertDescription>
                    </Alert>
                )}

                {(!server.is_active || !server.last_connected_at) && !error && (
                    <Alert variant="destructive">
                        <AlertCircle className="size-4" />
                        <AlertDescription>
                            {t(
                                'unable_establish_ssh_communication',
                                'Could not establish SSH communication with this server. Check credentials and network connectivity.',
                            )}
                        </AlertDescription>
                    </Alert>
                )}

                <div className="grid gap-6 md:grid-cols-2">
                    {/* Server Information */}
                    <Card>
                        <CardHeader>
                            <CardTitle>
                                {t('server_information', 'Server Information')}
                            </CardTitle>
                            <CardDescription>
                                {t(
                                    'connection_and_configuration_details',
                                    'Connection and configuration details',
                                )}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="flex items-center justify-between">
                                <span className="text-sm text-muted-foreground">
                                    {t('ip_address')}
                                </span>
                                <span className="font-mono text-sm">
                                    {server.ip_address}
                                </span>
                            </div>
                            <div className="flex items-center justify-between">
                                <span className="text-sm text-muted-foreground">
                                    {t('ssh_port', 'SSH Port')}
                                </span>
                                <span className="font-mono text-sm">
                                    {server.ssh_port}
                                </span>
                            </div>
                            <div className="flex items-center justify-between">
                                <span className="text-sm text-muted-foreground">
                                    {t('execution_user', 'Execution User')}
                                </span>
                                <span className="font-mono text-sm">
                                    {server.ssh_execution_username}
                                </span>
                            </div>
                            <div className="space-y-2">
                                <span className="text-sm text-muted-foreground">
                                    {t(
                                        'configured_ssh_users',
                                        'Configured SSH Users',
                                    )}
                                </span>
                                <div className="flex flex-wrap gap-2">
                                    {server.ssh_users.map((sshUser) => (
                                        <Badge
                                            key={sshUser.username}
                                            variant={
                                                sshUser.username ===
                                                server.ssh_execution_username
                                                    ? 'default'
                                                    : 'outline'
                                            }
                                        >
                                            {sshUser.username}
                                        </Badge>
                                    ))}
                                </div>
                            </div>
                            <div className="flex items-center justify-between">
                                <span className="text-sm text-muted-foreground">
                                    {t('engine')}
                                </span>
                                <Badge
                                    variant={
                                        server.provisioning_engine
                                            ? 'outline'
                                            : 'secondary'
                                    }
                                >
                                    {server.provisioning_engine ??
                                        t('none', 'None')}
                                </Badge>
                            </div>
                            <div className="flex items-center justify-between">
                                <span className="text-sm text-muted-foreground">
                                    {t('last_connected')}
                                </span>
                                <span className="text-sm">
                                    {server.last_connected_at ?? t('never')}
                                </span>
                            </div>
                            <div className="flex items-center justify-between">
                                <span className="text-sm text-muted-foreground">
                                    {t('created_at', 'Created At')}
                                </span>
                                <span className="text-sm">
                                    {server.created_at}
                                </span>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Sites */}
                    <Card>
                        <CardHeader className="flex items-center justify-between">
                            <div>
                                <CardTitle className="flex items-center gap-2">
                                    <Globe className="size-5" />
                                    {t('sites')}
                                </CardTitle>
                                <CardDescription>
                                    {t(
                                        'websites_managed_on_server',
                                        'Websites managed on this server',
                                    )}
                                </CardDescription>
                            </div>
                            {hasProvisioningEngine && (
                                <Button size="sm" asChild>
                                    <a
                                        href={`/sites/create?server_id=${server.id}`}
                                    >
                                        {t('create_site')}
                                    </a>
                                </Button>
                            )}
                        </CardHeader>
                        <CardContent>
                            {sites.length === 0 ? (
                                <div className="flex flex-col items-center justify-center py-8 text-center">
                                    <Globe className="mb-2 size-8 text-muted-foreground" />
                                    <p className="text-sm text-muted-foreground">
                                        {hasProvisioningEngine
                                            ? t(
                                                  'no_remote_sites_found',
                                                  'No sites found on this server.',
                                              )
                                            : t(
                                                  'configure_engine_manage_sites',
                                                  'Configure a provisioning engine to manage sites',
                                              )}
                                    </p>
                                </div>
                            ) : (
                                <div className="space-y-2">
                                    {sites.map((site) => (
                                        <div
                                            key={site.domain}
                                            className="flex items-center justify-between rounded-lg border p-3"
                                        >
                                            <div className="flex items-center gap-4">
                                                <span className="font-mono text-sm">
                                                    {site.domain}
                                                </span>
                                                <Badge
                                                    variant={
                                                        site.status ===
                                                        'enabled'
                                                            ? 'default'
                                                            : 'secondary'
                                                    }
                                                >
                                                    {site.status}
                                                </Badge>
                                                <Badge
                                                    variant={
                                                        site.imported
                                                            ? 'default'
                                                            : 'outline'
                                                    }
                                                >
                                                    {site.imported
                                                        ? t(
                                                              'imported',
                                                              'Imported',
                                                          )
                                                        : t(
                                                              'not_imported',
                                                              'Not imported',
                                                          )}
                                                </Badge>
                                            </div>
                                            {site.imported &&
                                            site.local_site_id ? (
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    asChild
                                                >
                                                    <a
                                                        href={`/sites/${site.local_site_id}`}
                                                    >
                                                        {t('manage')}
                                                    </a>
                                                </Button>
                                            ) : (
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    disabled={
                                                        importingDomain ===
                                                        site.domain
                                                    }
                                                    onClick={() =>
                                                        handleImportSite(
                                                            site.domain,
                                                        )
                                                    }
                                                >
                                                    {importingDomain ===
                                                    site.domain
                                                        ? t('creating')
                                                        : t('import', 'Import')}
                                                </Button>
                                            )}
                                        </div>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>

                {/* Provisioning */}
                <Card>
                    <CardHeader className="flex flex-row items-start justify-between">
                        <div>
                            <CardTitle>{t('provisioning')}</CardTitle>
                            <CardDescription>
                                {t(
                                    'run_recipes_configure_server',
                                    'Run recipes to configure and set up this server',
                                )}
                            </CardDescription>
                        </div>
                        <Button variant="outline" size="sm" asChild>
                            <a href={`/servers/${server.id}/provisioning`}>
                                {t('provisioning_history')}
                            </a>
                        </Button>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {/* Active run progress banner */}
                        {activeRun &&
                            (activeRun.status === 'pending' ||
                                activeRun.status === 'running') && (
                                <Alert>
                                    <Loader2 className="size-4 animate-spin" />
                                    <AlertDescription>
                                        <span className="font-medium">
                                            {activeRun.recipeName}
                                        </span>{' '}
                                        {t('is_running', 'is running…')}
                                        {activeRun.executionUsername && (
                                            <>
                                                {' '}
                                                {t('as_user', 'as user')}{' '}
                                                <span className="font-mono">
                                                    {activeRun.executionUsername}
                                                </span>
                                            </>
                                        )}
                                    </AlertDescription>
                                </Alert>
                            )}

                        {/* Recipe list */}
                        <div className="space-y-3">
                            {recipes.map((recipe) => {
                                const isThisActive =
                                    activeRun !== null &&
                                    activeRun.recipeId === recipe.id;
                                const isThisRunning =
                                    isThisActive &&
                                    (activeRun!.status === 'pending' ||
                                        activeRun!.status === 'running');
                                const hasActiveRun =
                                    activeRun !== null &&
                                    (activeRun.status === 'pending' ||
                                        activeRun.status === 'running');
                                const selectedExecutionUsername =
                                    selectedExecutionUsers[recipe.id] ?? '';
                                const defaultUserConfigured =
                                    configuredSshUsernames.includes(
                                        recipe.defaultExecutionUsername,
                                    );
                                const canRunRecipe =
                                    !hasActiveRun &&
                                    !submitting &&
                                    (recipe.allowsExecutionUserSelection
                                        ? selectedExecutionUsername !== ''
                                        : defaultUserConfigured);

                                // Show step list when this recipe was the active run and has results,
                                // regardless of whether it is still running or already finished.
                                const showSteps =
                                    isThisActive &&
                                    ((activeRun?.steps?.length ?? 0) > 0 ||
                                        activeRun?.currentStep != null);

                                return (
                                    <div
                                        key={recipe.id}
                                        className="rounded-lg border p-4"
                                    >
                                        <div className="flex items-start justify-between gap-4">
                                            <div className="min-w-0 flex-1">
                                                <div className="flex items-center gap-2">
                                                    <span className="font-medium">
                                                        {recipe.name}
                                                    </span>
                                                    {/* While the run is active show the live status; otherwise show last run badge */}
                                                    {isThisActive ? (
                                                        <Badge
                                                            variant={statusVariant(
                                                                activeRun!
                                                                    .status,
                                                            )}
                                                        >
                                                            {activeRun!.status}
                                                        </Badge>
                                                    ) : recipe.lastRun ? (
                                                        <Badge
                                                            variant={statusVariant(
                                                                recipe.lastRun
                                                                    .status,
                                                            )}
                                                        >
                                                            {
                                                                recipe.lastRun
                                                                    .status
                                                            }
                                                        </Badge>
                                                    ) : null}
                                                </div>
                                                <p className="mt-0.5 text-sm text-muted-foreground">
                                                    {recipe.description}
                                                </p>
                                                <div className="mt-3 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                                    <div className="text-xs text-muted-foreground">
                                                        {recipe.allowsExecutionUserSelection
                                                            ? t(
                                                                  'provisioning_execution_user_hint',
                                                                  'Execution user: root by default, or choose another configured SSH user.',
                                                              )
                                                            : `${t('execution_user', 'Execution User')}: ${recipe.defaultExecutionUsername}`}
                                                    </div>
                                                    {recipe.allowsExecutionUserSelection && (
                                                        <div className="w-full sm:w-52">
                                                            <Select
                                                                value={
                                                                    selectedExecutionUsername ||
                                                                    undefined
                                                                }
                                                                onValueChange={(
                                                                    value,
                                                                ) =>
                                                                    setSelectedExecutionUsers(
                                                                        (
                                                                            current,
                                                                        ) => ({
                                                                            ...current,
                                                                            [recipe.id]:
                                                                                value,
                                                                        }),
                                                                    )
                                                                }
                                                            >
                                                                <SelectTrigger className="w-full">
                                                                    <SelectValue
                                                                        placeholder={t(
                                                                            'select_execution_user',
                                                                            'Select execution user',
                                                                        )}
                                                                    />
                                                                </SelectTrigger>
                                                                <SelectContent>
                                                                    {configuredSshUsernames.map(
                                                                        (
                                                                            username,
                                                                        ) => (
                                                                            <SelectItem
                                                                                key={
                                                                                    username
                                                                                }
                                                                                value={
                                                                                    username
                                                                                }
                                                                            >
                                                                                {
                                                                                    username
                                                                                }
                                                                            </SelectItem>
                                                                        ),
                                                                    )}
                                                                </SelectContent>
                                                            </Select>
                                                        </div>
                                                    )}
                                                </div>
                                                {!recipe.allowsExecutionUserSelection &&
                                                    !defaultUserConfigured && (
                                                        <p className="mt-2 text-xs text-destructive">
                                                            {t(
                                                                'missing_recipe_execution_user',
                                                                'This recipe requires the root SSH user to be configured on the server.',
                                                            )}
                                                        </p>
                                                    )}
                                                {recipe.allowsExecutionUserSelection &&
                                                    selectedExecutionUsername ===
                                                        '' && (
                                                        <p className="mt-2 text-xs text-destructive">
                                                            {t(
                                                                'select_execution_user_before_running',
                                                                'Select an SSH user before running this recipe.',
                                                            )}
                                                        </p>
                                                    )}
                                                {recipe.lastRun
                                                    ?.executionUsername && (
                                                    <p className="mt-2 text-xs text-muted-foreground">
                                                        {t(
                                                            'last_run_execution_user',
                                                            'Last run executed as',
                                                        )}{' '}
                                                        <span className="font-mono">
                                                            {
                                                                recipe.lastRun
                                                                    .executionUsername
                                                            }
                                                        </span>
                                                    </p>
                                                )}
                                            </div>
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                disabled={!canRunRecipe}
                                                onClick={() =>
                                                    runRecipe(recipe)
                                                }
                                            >
                                                {isThisRunning ? (
                                                    <Loader2 className="size-4 animate-spin" />
                                                ) : (
                                                    <Play className="size-4" />
                                                )}
                                                {isThisRunning
                                                    ? t(
                                                          'running_now',
                                                          'Running…',
                                                      )
                                                    : t('run', 'Run')}
                                            </Button>
                                        </div>

                                        {/* Step list: appears while running and persists after completion */}
                                        {showSteps && (
                                            <div className="mt-4 space-y-2 border-t pt-4">
                                                {/* Completed steps */}
                                                {(activeRun!.steps ?? []).map(
                                                    (step) => {
                                                        const output =
                                                            normalizeOutput(
                                                                step.stdout,
                                                            );
                                                        const errOutput =
                                                            normalizeOutput(
                                                                step.stderr,
                                                            );
                                                        const hasOutput =
                                                            Boolean(
                                                                output ||
                                                                    errOutput,
                                                            );
                                                        return (
                                                            <Collapsible
                                                                key={
                                                                    step.stepId
                                                                }
                                                            >
                                                                <div className="flex items-center gap-2 text-sm">
                                                                    <StatusIcon
                                                                        status={
                                                                            step.successful
                                                                                ? 'completed'
                                                                                : 'failed'
                                                                        }
                                                                    />
                                                                    <div className="min-w-0 flex-1">
                                                                        <span
                                                                            className={
                                                                                step.successful
                                                                                    ? ''
                                                                                    : 'text-destructive'
                                                                            }
                                                                        >
                                                                            {
                                                                                step.stepName
                                                                            }
                                                                        </span>
                                                                        {step.description && (
                                                                            <p className="truncate text-xs text-muted-foreground">
                                                                                {
                                                                                    step.description
                                                                                }
                                                                            </p>
                                                                        )}
                                                                    </div>
                                                                    <span className="shrink-0 text-xs text-muted-foreground">
                                                                        {
                                                                            step.duration
                                                                        }
                                                                        s
                                                                    </span>
                                                                    {hasOutput ? (
                                                                        <CollapsibleTrigger
                                                                            asChild
                                                                        >
                                                                            <Button
                                                                                variant="ghost"
                                                                                size="icon"
                                                                                className="size-5 shrink-0"
                                                                            >
                                                                                <ChevronDown className="size-3" />
                                                                            </Button>
                                                                        </CollapsibleTrigger>
                                                                    ) : (
                                                                        <span className="shrink-0 text-xs text-muted-foreground italic">
                                                                            {step.successful
                                                                                ? t(
                                                                                      'no_output',
                                                                                      'no output',
                                                                                  )
                                                                                : ''}
                                                                        </span>
                                                                    )}
                                                                </div>
                                                                {hasOutput && (
                                                                    <CollapsibleContent>
                                                                        <pre className="mt-1 max-h-64 overflow-auto rounded bg-zinc-950 p-2 font-mono text-xs text-green-400">
                                                                            {output ||
                                                                                errOutput}
                                                                        </pre>
                                                                    </CollapsibleContent>
                                                                )}
                                                            </Collapsible>
                                                        );
                                                    },
                                                )}

                                                {/* Currently executing step — live output */}
                                                {isThisRunning &&
                                                    activeRun?.currentStep && (
                                                        <div className="space-y-1">
                                                            <div className="flex items-center gap-2 text-sm">
                                                                <Loader2 className="size-4 shrink-0 animate-spin text-primary" />
                                                                <span className="font-medium">
                                                                    {
                                                                        activeRun
                                                                            .currentStep
                                                                            .stepName
                                                                    }
                                                                </span>
                                                                <span className="ml-auto text-xs text-muted-foreground">
                                                                    {
                                                                        activeRun
                                                                            .currentStep
                                                                            .stepIndex
                                                                    }
                                                                    /
                                                                    {
                                                                        activeRun
                                                                            .currentStep
                                                                            .totalSteps
                                                                    }
                                                                </span>
                                                            </div>
                                                            {activeRun
                                                                .currentStep
                                                                .partial_stdout && (
                                                                <pre className="max-h-48 overflow-auto rounded bg-muted p-2 text-xs text-muted-foreground">
                                                                    {
                                                                        activeRun
                                                                            .currentStep
                                                                            .partial_stdout
                                                                    }
                                                                </pre>
                                                            )}
                                                        </div>
                                                    )}

                                                {/* Summary line after completion */}
                                                {!isThisRunning &&
                                                    activeRun && (
                                                        <div className="flex items-center gap-2 pt-1 text-xs text-muted-foreground">
                                                            <StatusIcon
                                                                status={
                                                                    activeRun.status
                                                                }
                                                            />
                                                            <span>
                                                                {activeRun.status ===
                                                                'completed'
                                                                    ? t(
                                                                          'completed',
                                                                          'Completed',
                                                                      )
                                                                    : t(
                                                                          'failed',
                                                                          'Failed',
                                                                      )}
                                                                {activeRun.totalDuration !=
                                                                    null &&
                                                                    ` in ${activeRun.totalDuration}s`}
                                                                {activeRun.completedAt &&
                                                                    ` · ${new Date(activeRun.completedAt).toLocaleString()}`}
                                                            </span>
                                                            {activeRun.failureReason && (
                                                                <span className="truncate text-destructive">
                                                                    —{' '}
                                                                    {
                                                                        activeRun.failureReason
                                                                    }
                                                                </span>
                                                            )}
                                                        </div>
                                                    )}
                                            </div>
                                        )}

                                        {/* Last run metadata — shown when this recipe was never the active run */}
                                        {!showSteps && recipe.lastRun && (
                                            <div className="mt-3 flex items-center gap-2 text-xs text-muted-foreground">
                                                <StatusIcon
                                                    status={
                                                        recipe.lastRun.status
                                                    }
                                                />
                                                <span>
                                                    {t('last_run', 'Last run')}:{' '}
                                                    {recipe.lastRun.completedAt
                                                        ? new Date(
                                                              recipe.lastRun.completedAt,
                                                          ).toLocaleString()
                                                        : recipe.lastRun
                                                                .startedAt
                                                          ? new Date(
                                                                recipe.lastRun.startedAt,
                                                            ).toLocaleString()
                                                          : '—'}
                                                </span>
                                                {recipe.lastRun
                                                    .failureReason && (
                                                    <span className="text-destructive">
                                                        —{' '}
                                                        {
                                                            recipe.lastRun
                                                                .failureReason
                                                        }
                                                    </span>
                                                )}
                                            </div>
                                        )}
                                    </div>
                                );
                            })}
                        </div>
                    </CardContent>
                </Card>
            </div>

            {/* Live command output modal — opens when a site command is dispatched */}
            <SiteCommandModal
                runId={commandRunId}
                onClose={() => setCommandRunId(null)}
                onSuccess={() => {
                    setCommandRunId(null);
                    if (hasProvisioningEngine) {
                        router.reload({ only: ['sites'] });
                    }
                }}
            />
        </AppLayout>
    );
}
