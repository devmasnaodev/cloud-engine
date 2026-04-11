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
import { useTranslations } from '@/hooks/useTranslations';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import {
    ArrowLeft,
    CheckCircle2,
    ChevronDown,
    Clock,
    Loader2,
    Terminal,
    XCircle,
} from 'lucide-react';

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

function normalizeOutput(text: string | null | undefined): string | null {
    if (!text) return null;
    const trimmed = text.trim();
    if (!trimmed || /^\d+$/.test(trimmed)) return null;
    return trimmed;
}

interface ProvisioningRun {
    id: number;
    recipeId: string;
    recipeName: string;
    executionUsername: string | null;
    status: string;
    steps: StepResult[];
    failureReason: string | null;
    totalDuration: number | null;
    startedAt: string | null;
    completedAt: string | null;
    createdAt: string;
}

interface Props {
    server: { id: number; name: string };
    runs: ProvisioningRun[];
}

function statusVariant(
    status: string,
): 'default' | 'secondary' | 'destructive' | 'outline' {
    if (status === 'completed') return 'default';
    if (status === 'failed') return 'destructive';
    if (status === 'running') return 'secondary';
    return 'outline';
}

function StatusIcon({ status }: { status: string }) {
    if (status === 'completed')
        return <CheckCircle2 className="size-4 shrink-0 text-green-500" />;
    if (status === 'failed')
        return <XCircle className="size-4 shrink-0 text-destructive" />;
    if (status === 'running' || status === 'pending')
        return <Loader2 className="size-4 shrink-0 animate-spin" />;
    return <Clock className="size-4 shrink-0 text-muted-foreground" />;
}

function formatDate(iso: string | null): string {
    if (!iso) return '—';
    return new Date(iso).toLocaleString();
}

export default function ProvisioningHistory({ server, runs }: Props) {
    const t = useTranslations();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('servers'), href: '/servers' },
        { title: server.name, href: `/servers/${server.id}` },
        {
            title: t('provisioning_history'),
            href: `/servers/${server.id}/provisioning`,
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${server.name} — ${t('provisioning_history')}`} />
            <div className="flex h-full flex-1 flex-col gap-6 p-6">
                {/* Header */}
                <div className="flex items-center gap-4">
                    <Button variant="outline" size="icon" asChild>
                        <a href={`/servers/${server.id}`}>
                            <ArrowLeft className="size-4" />
                        </a>
                    </Button>
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {t('provisioning_history')}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {server.name}
                        </p>
                    </div>
                </div>

                {runs.length === 0 ? (
                    <Card>
                        <CardContent className="flex flex-col items-center justify-center py-16 text-center">
                            <Terminal className="mb-3 size-10 text-muted-foreground" />
                            <p className="text-sm text-muted-foreground">
                                {t(
                                    'no_provisioning_runs_for_server',
                                    'No provisioning runs found for this server.',
                                )}
                            </p>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="space-y-4">
                        {runs.map((run) => (
                            <Card key={run.id}>
                                <CardHeader className="pb-3">
                                    <div className="flex items-start justify-between gap-4">
                                        <div className="flex items-center gap-3">
                                            <StatusIcon status={run.status} />
                                            <div>
                                                <CardTitle className="flex items-center gap-2 text-base">
                                                    {run.recipeName}
                                                    <Badge
                                                        variant={statusVariant(
                                                            run.status,
                                                        )}
                                                    >
                                                        {run.status}
                                                    </Badge>
                                                </CardTitle>
                                                <CardDescription className="mt-0.5 flex items-center gap-3 text-xs">
                                                    {run.executionUsername && (
                                                        <span>
                                                            {t(
                                                                'execution_user',
                                                                'Execution User',
                                                            )}
                                                            :{' '}
                                                            <span className="font-mono">
                                                                {
                                                                    run.executionUsername
                                                                }
                                                            </span>
                                                        </span>
                                                    )}
                                                    <span>
                                                        {t(
                                                            'started',
                                                            'Started',
                                                        )}
                                                        :{' '}
                                                        {formatDate(
                                                            run.startedAt,
                                                        )}
                                                    </span>
                                                    {run.completedAt && (
                                                        <span>
                                                            {t(
                                                                'completed',
                                                                'Completed',
                                                            )}
                                                            :{' '}
                                                            {formatDate(
                                                                run.completedAt,
                                                            )}
                                                        </span>
                                                    )}
                                                    {run.totalDuration !=
                                                        null && (
                                                        <span>
                                                            {t(
                                                                'duration',
                                                                'Duration',
                                                            )}
                                                            :{' '}
                                                            {run.totalDuration}s
                                                        </span>
                                                    )}
                                                </CardDescription>
                                            </div>
                                        </div>
                                    </div>

                                    {run.failureReason && (
                                        <p className="mt-2 text-sm text-destructive">
                                            {run.failureReason}
                                        </p>
                                    )}
                                </CardHeader>

                                {run.steps.length > 0 && (
                                    <CardContent className="pt-0">
                                        <div className="space-y-2 border-t pt-3">
                                            {run.steps.map((step) => {
                                                const output = normalizeOutput(
                                                    step.stdout,
                                                );
                                                const errOutput =
                                                    normalizeOutput(
                                                        step.stderr,
                                                    );
                                                const hasOutput = Boolean(
                                                    output || errOutput,
                                                );
                                                return (
                                                    <Collapsible
                                                        key={`${run.id}-${step.stepId}`}
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
                                                                {step.duration}s
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
                                                        {step.validationError && (
                                                            <p className="mt-1 text-xs text-muted-foreground">
                                                                {t(
                                                                    'note',
                                                                    'Note',
                                                                )}
                                                                :{' '}
                                                                {
                                                                    step.validationError
                                                                }
                                                            </p>
                                                        )}
                                                    </Collapsible>
                                                );
                                            })}
                                        </div>
                                    </CardContent>
                                )}
                            </Card>
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
