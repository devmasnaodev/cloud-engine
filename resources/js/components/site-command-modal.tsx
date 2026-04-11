import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { AlertCircle, CheckCircle2, Loader2 } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

interface CommandRunStatus {
    id: number;
    action: string;
    domain: string;
    status: 'pending' | 'running' | 'completed' | 'failed';
    partial_stdout: string | null;
    stdout: string | null;
    stderr: string | null;
    exit_status: number | null;
    duration: number | null;
    started_at: string | null;
    completed_at: string | null;
}

interface Props {
    runId: number | null;
    onClose: () => void;
    onSuccess?: (action: string) => void;
    onFailure?: () => void;
}

const ACTION_LABELS: Record<
    string,
    { running: string; completed: string; failed: string }
> = {
    create_site: {
        running: 'Creating Site',
        completed: 'Site Created',
        failed: 'Site Creation Failed',
    },
    delete_site: {
        running: 'Deleting Site',
        completed: 'Site Deleted',
        failed: 'Site Deletion Failed',
    },
    enable_site: {
        running: 'Enabling Site',
        completed: 'Site Enabled',
        failed: 'Site Enable Failed',
    },
    disable_site: {
        running: 'Disabling Site',
        completed: 'Site Disabled',
        failed: 'Site Disable Failed',
    },
    update_site: {
        running: 'Updating Site',
        completed: 'Site Updated',
        failed: 'Site Update Failed',
    },
};

function getActionLabel(
    action: string,
    status: CommandRunStatus['status'],
): string {
    const labels = ACTION_LABELS[action];
    if (!labels) return action;
    if (status === 'completed') return labels.completed;
    if (status === 'failed') return labels.failed;
    return labels.running;
}

export function SiteCommandModal({
    runId,
    onClose,
    onSuccess,
    onFailure,
}: Props) {
    const [run, setRun] = useState<CommandRunStatus | null>(null);
    const outputRef = useRef<HTMLPreElement>(null);
    const pollRef = useRef<ReturnType<typeof setInterval> | null>(null);
    const successFiredRef = useRef(false);

    // Auto-scroll terminal to bottom when output changes.
    useEffect(() => {
        if (outputRef.current) {
            outputRef.current.scrollTop = outputRef.current.scrollHeight;
        }
    }, [run?.partial_stdout, run?.stdout]);

    // Start/stop polling based on runId and status.
    useEffect(() => {
        if (runId === null) {
            setRun(null);
            successFiredRef.current = false;
            return;
        }

        successFiredRef.current = false;

        const poll = async () => {
            try {
                const response = await fetch(`/site-command-runs/${runId}`, {
                    headers: { Accept: 'application/json' },
                });

                if (!response.ok) return;

                const data: CommandRunStatus = await response.json();
                setRun(data);

                if (data.status === 'completed' && !successFiredRef.current) {
                    successFiredRef.current = true;
                    clearInterval(pollRef.current!);
                    setTimeout(() => onSuccess?.(data.action), 1500);
                } else if (data.status === 'failed') {
                    clearInterval(pollRef.current!);
                    onFailure?.();
                }
            } catch {
                // Network error — keep polling silently.
            }
        };

        poll();
        pollRef.current = setInterval(poll, 2000);

        return () => {
            if (pollRef.current) clearInterval(pollRef.current);
        };
    }, [runId]);

    if (runId === null) return null;

    const isFinished = run?.status === 'completed' || run?.status === 'failed';
    const title = run
        ? getActionLabel(run.action, run.status)
        : 'Running Command…';
    const displayOutput =
        run?.status === 'completed' || run?.status === 'failed'
            ? (run.stdout ?? run.stderr ?? '')
            : (run?.partial_stdout ?? '');

    return (
        <Dialog
            open={true}
            onOpenChange={(open) => {
                if (!open && isFinished) onClose();
            }}
        >
            <DialogContent
                className="max-w-2xl"
                onPointerDownOutside={(e) => {
                    if (!isFinished) e.preventDefault();
                }}
            >
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        {run?.status === 'completed' && (
                            <CheckCircle2 className="size-5 text-green-500" />
                        )}
                        {run?.status === 'failed' && (
                            <AlertCircle className="size-5 text-destructive" />
                        )}
                        {(!run ||
                            run.status === 'pending' ||
                            run.status === 'running') && (
                            <Loader2 className="size-5 animate-spin text-muted-foreground" />
                        )}
                        {title}
                    </DialogTitle>
                    {run && (
                        <DialogDescription className="font-mono text-xs">
                            {run.domain}
                            {run.duration != null &&
                                run.status === 'completed' && (
                                    <span className="ml-2 text-muted-foreground">
                                        ({run.duration.toFixed(1)}s)
                                    </span>
                                )}
                        </DialogDescription>
                    )}
                </DialogHeader>

                {/* Status message */}
                {run?.status === 'completed' && (
                    <p className="text-sm font-medium text-green-600 dark:text-green-400">
                        Command completed successfully.
                    </p>
                )}
                {run?.status === 'failed' && (
                    <p className="text-sm font-medium text-destructive">
                        Command failed. See output below for details.
                    </p>
                )}

                {/* Terminal output */}
                <pre
                    ref={outputRef}
                    className="max-h-96 min-h-24 overflow-auto rounded-md bg-zinc-950 p-4 font-mono text-xs leading-relaxed text-zinc-100 dark:bg-zinc-900"
                >
                    {displayOutput ||
                        (run?.status === 'pending'
                            ? 'Waiting for job to start…'
                            : 'Waiting for output…')}
                </pre>

                {/* Show stderr separately on failure if different from stdout */}
                {run?.status === 'failed' &&
                    run.stderr &&
                    run.stderr !== run.stdout && (
                        <div className="space-y-1">
                            <p className="text-xs font-medium text-muted-foreground">
                                Error
                            </p>
                            <pre className="max-h-32 overflow-auto rounded-md bg-red-950/20 p-3 font-mono text-xs text-red-400">
                                {run.stderr}
                            </pre>
                        </div>
                    )}

                {/* Close button — only shown when finished */}
                {isFinished && (
                    <div className="flex justify-end">
                        <Button variant="outline" onClick={onClose}>
                            Close
                        </Button>
                    </div>
                )}
            </DialogContent>
        </Dialog>
    );
}
