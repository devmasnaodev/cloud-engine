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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTranslations } from '@/hooks/useTranslations';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import {
    AlertCircle,
    ArrowLeft,
    Cpu,
    Globe,
    Loader2,
    Network,
    Plus,
    Save,
    X,
    Zap,
} from 'lucide-react';
import { useMemo, useState } from 'react';

interface SiteInfo {
    site_type?: string;
    php_version?: string;
    ssl?: string | boolean;
    cache_nginx_fullpage?: string;
    cache_nginx_fastcgi?: string;
    alias_domains?: string;
    [key: string]: unknown;
}

interface Site {
    id: number;
    domain: string;
    info: SiteInfo | null;
}

interface SiteServer {
    id: number;
    name: string;
    provisioning_engine: string | null;
}

interface Props {
    site: Site;
    server: SiteServer | null;
    phpVersions: string[];
}

/**
 * Build one preview command string per option group, mirroring the server-side
 * EasyEngineCommandBuilder::buildUpdateSiteCommands() execution order.
 */
function buildPreviewCommands(
    domain: string,
    data: {
        ssl: string;
        wildcard: boolean;
        php_version: string;
        proxy_cache: string;
        proxy_cache_max_size: string;
        proxy_cache_max_time: string;
    },
    addedAliases: string[],
    removedAliases: string[],
): string[] {
    const commands: string[] = [];

    const php = data.php_version !== '__none__' ? data.php_version : '';
    if (php) {
        commands.push(`sudo ee site update ${domain} \\\n  --php=${php}`);
    }

    const cache = data.proxy_cache !== '__none__' ? data.proxy_cache : '';
    if (cache) {
        let cmd = `sudo ee site update ${domain} \\\n  --proxy-cache=${cache}`;
        if (cache === 'on' && data.proxy_cache_max_size)
            cmd += ` \\\n  --proxy-cache-max-size=${data.proxy_cache_max_size}`;
        if (cache === 'on' && data.proxy_cache_max_time)
            cmd += ` \\\n  --proxy-cache-max-time=${data.proxy_cache_max_time}`;
        commands.push(cmd);
    }

    if (removedAliases.length > 0) {
        commands.push(
            `sudo ee site update ${domain} \\\n  --delete-alias-domains='${removedAliases.join(',')}'`,
        );
    }

    if (addedAliases.length > 0) {
        commands.push(
            `sudo ee site update ${domain} \\\n  --add-alias-domains='${addedAliases.join(',')}'`,
        );
    }

    return commands;
}

function hasChanges(
    data: {
        ssl: string;
        wildcard: boolean;
        php_version: string;
        proxy_cache: string;
        proxy_cache_max_size: string;
        proxy_cache_max_time: string;
    },
    addedAliases: string[],
    removedAliases: string[],
): boolean {
    return (
        data.ssl !== '__none__' ||
        data.php_version !== '__none__' ||
        data.proxy_cache !== '__none__' ||
        addedAliases.length > 0 ||
        removedAliases.length > 0
    );
}

export default function SiteEdit({ site, server, phpVersions }: Props) {
    const t = useTranslations();
    const info = site.info;
    const siteType = info?.site_type ?? 'html';
    const isPhpOrWp = siteType === 'php' || siteType === 'wp';
    const currentProxyCache =
        info?.cache_nginx_fullpage === 'enabled' ? 'on' : 'off';

    const PROXY_CACHE_OPTIONS = [
        { value: '__none__', label: t('no_change') },
        { value: 'on', label: t('enable') },
        { value: 'off', label: t('disable') },
    ];

    const { data, setData, patch, transform, processing, errors } = useForm({
        ssl: '__none__' as string,
        wildcard: false,
        php_version: '__none__' as string,
        proxy_cache: '__none__' as string,
        proxy_cache_max_size: '' as string,
        proxy_cache_max_time: '' as string,
        add_alias_domains: '' as string,
        delete_alias_domains: '' as string,
    });

    // --- Alias domain tag management ---
    const originalAliases = useMemo((): string[] => {
        if (!info?.alias_domains) return [];
        return String(info.alias_domains)
            .split(',')
            .map((d) => d.trim())
            .filter(Boolean);
    }, [info?.alias_domains]);

    const [aliases, setAliases] = useState<string[]>(originalAliases);
    const [aliasInput, setAliasInput] = useState('');

    const addedAliases = aliases.filter((a) => !originalAliases.includes(a));
    const removedAliases = originalAliases.filter(
        (a) => !aliases.includes(a) && a !== site.domain,
    );

    const handleAddAlias = () => {
        const trimmed = aliasInput.trim().toLowerCase();
        if (!trimmed || aliases.includes(trimmed)) return;
        setAliases((prev) => [...prev, trimmed]);
        setAliasInput('');
    };

    const handleRemoveAlias = (alias: string) => {
        if (alias === site.domain) return;
        setAliases((prev) => prev.filter((a) => a !== alias));
    };

    // Inject alias diffs via transform so they are always fresh on submit
    transform((d) => ({
        ...d,
        ssl: d.ssl === '__none__' ? '' : d.ssl,
        php_version: d.php_version === '__none__' ? '' : d.php_version,
        proxy_cache: d.proxy_cache === '__none__' ? '' : d.proxy_cache,
        add_alias_domains: addedAliases.join(','),
        delete_alias_domains: removedAliases.join(','),
    }));
    // --- end alias management ---

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('servers'), href: '/servers' },
        { title: t('sites'), href: '/sites' },
        { title: site.domain, href: `/sites/${site.id}` },
        { title: t('edit'), href: `/sites/${site.id}/edit` },
    ];

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        patch(`/sites/${site.id}`);
    };

    const previewCommands = buildPreviewCommands(
        site.domain,
        data,
        addedAliases,
        removedAliases,
    );
    const changed = hasChanges(data, addedAliases, removedAliases);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${t('edit_site')} — ${site.domain}`} />
            <div className="flex h-full flex-1 flex-col gap-6 p-6">
                {/* Header */}
                <div className="flex items-center gap-4">
                    <Button variant="outline" size="icon" asChild>
                        <a href={`/sites/${site.id}`}>
                            <ArrowLeft className="size-4" />
                        </a>
                    </Button>
                    <div>
                        <div className="flex items-center gap-2">
                            <Globe className="size-5 text-muted-foreground" />
                            <h1 className="text-2xl font-semibold tracking-tight">
                                {t('edit_site')}
                            </h1>
                        </div>
                        <p className="ml-9 font-mono text-sm text-muted-foreground">
                            {site.domain}
                        </p>
                    </div>
                    {server && (
                        <Badge variant="outline" className="ml-auto">
                            {server.provisioning_engine ?? t('no_engine')}
                        </Badge>
                    )}
                </div>

                {!server && (
                    <Alert variant="destructive">
                        <AlertCircle className="size-4" />
                        <AlertDescription>
                            {t('no_server_associated')}
                        </AlertDescription>
                    </Alert>
                )}

                <form onSubmit={handleSubmit} className="space-y-6">
                    {/* PHP Version — only for php/wp sites */}
                    {isPhpOrWp && (
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Cpu className="size-4" />
                                    {t('php_version')}
                                </CardTitle>
                                <CardDescription>
                                    {t('current')}:{' '}
                                    <span className="font-mono">
                                        {info?.php_version ?? 'unknown'}
                                    </span>
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-2">
                                    <Label htmlFor="php_version">
                                        {t('php_version')}
                                    </Label>
                                    <Select
                                        value={data.php_version}
                                        onValueChange={(v) =>
                                            setData('php_version', v)
                                        }
                                    >
                                        <SelectTrigger id="php_version">
                                            <SelectValue
                                                placeholder={t('no_change')}
                                            />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="__none__">
                                                {t('no_change')}
                                            </SelectItem>
                                            {phpVersions.map((v) => (
                                                <SelectItem key={v} value={v}>
                                                    {v}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {errors.php_version && (
                                        <p className="text-xs text-destructive">
                                            {errors.php_version}
                                        </p>
                                    )}
                                </div>
                            </CardContent>
                        </Card>
                    )}

                    {/* Proxy Cache — only for php/wp sites */}
                    {isPhpOrWp && (
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Zap className="size-4" />
                                    {t('proxy_cache')}
                                </CardTitle>
                                <CardDescription>
                                    {t('current')}:{' '}
                                    <span className="font-mono">
                                        {currentProxyCache}
                                    </span>
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="space-y-2">
                                    <Label htmlFor="proxy_cache">
                                        {t('proxy_cache')}
                                    </Label>
                                    <Select
                                        value={data.proxy_cache}
                                        onValueChange={(v) =>
                                            setData('proxy_cache', v)
                                        }
                                    >
                                        <SelectTrigger id="proxy_cache">
                                            <SelectValue
                                                placeholder={t('no_change')}
                                            />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {PROXY_CACHE_OPTIONS.map((opt) => (
                                                <SelectItem
                                                    key={opt.value}
                                                    value={opt.value}
                                                >
                                                    {opt.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>

                                {data.proxy_cache === 'on' && (
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <div className="space-y-2">
                                            <Label htmlFor="proxy_cache_max_size">
                                                {t('cache_max_size')}
                                                <span className="ml-1 text-xs text-muted-foreground">
                                                    (ex: 1g, 512m)
                                                </span>
                                            </Label>
                                            <Input
                                                id="proxy_cache_max_size"
                                                value={
                                                    data.proxy_cache_max_size
                                                }
                                                onChange={(e) =>
                                                    setData(
                                                        'proxy_cache_max_size',
                                                        e.target.value,
                                                    )
                                                }
                                                placeholder="1g"
                                            />
                                            {errors.proxy_cache_max_size && (
                                                <p className="text-xs text-destructive">
                                                    {
                                                        errors.proxy_cache_max_size
                                                    }
                                                </p>
                                            )}
                                        </div>
                                        <div className="space-y-2">
                                            <Label htmlFor="proxy_cache_max_time">
                                                {t('cache_max_time')}
                                                <span className="ml-1 text-xs text-muted-foreground">
                                                    (ex: 30s, 5m)
                                                </span>
                                            </Label>
                                            <Input
                                                id="proxy_cache_max_time"
                                                value={
                                                    data.proxy_cache_max_time
                                                }
                                                onChange={(e) =>
                                                    setData(
                                                        'proxy_cache_max_time',
                                                        e.target.value,
                                                    )
                                                }
                                                placeholder="30s"
                                            />
                                            {errors.proxy_cache_max_time && (
                                                <p className="text-xs text-destructive">
                                                    {
                                                        errors.proxy_cache_max_time
                                                    }
                                                </p>
                                            )}
                                        </div>
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    )}

                    {/* Alias Domains — tag-based UI */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Network className="size-4" />
                                {t('alias_domains')}
                            </CardTitle>
                            <CardDescription>
                                {t(
                                    'alias_domains_description',
                                    'Gerencie os domínios alias do site. O domínio principal não pode ser removido.',
                                )}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {/* Active aliases as tags */}
                            {(aliases.length > 0 ||
                                removedAliases.length > 0) && (
                                <div className="flex flex-wrap gap-2">
                                    {aliases.map((alias) => {
                                        const isPrimary = alias === site.domain;
                                        const isNew =
                                            addedAliases.includes(alias);
                                        return (
                                            <span
                                                key={alias}
                                                className={[
                                                    'inline-flex items-center gap-1 rounded-md border px-2 py-1 font-mono text-xs',
                                                    isPrimary
                                                        ? 'border-border bg-muted text-muted-foreground'
                                                        : isNew
                                                          ? 'border-green-500/50 bg-green-500/10 text-green-700 dark:text-green-400'
                                                          : 'border-border bg-background',
                                                ].join(' ')}
                                            >
                                                {alias}
                                                {isPrimary && (
                                                    <span className="ml-1 text-[10px] opacity-60">
                                                        (primary)
                                                    </span>
                                                )}
                                                {!isPrimary && (
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            handleRemoveAlias(
                                                                alias,
                                                            )
                                                        }
                                                        className="ml-1 rounded hover:text-destructive focus:outline-none"
                                                        aria-label={`Remove ${alias}`}
                                                    >
                                                        <X className="size-3" />
                                                    </button>
                                                )}
                                            </span>
                                        );
                                    })}

                                    {/* Removed aliases shown with strikethrough + restore button */}
                                    {removedAliases.map((alias) => (
                                        <span
                                            key={`rm-${alias}`}
                                            className="inline-flex items-center gap-1 rounded-md border border-destructive/50 bg-destructive/10 px-2 py-1 font-mono text-xs text-destructive line-through"
                                        >
                                            {alias}
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    setAliases((prev) => [
                                                        ...prev,
                                                        alias,
                                                    ])
                                                }
                                                className="ml-1 rounded no-underline hover:text-foreground focus:outline-none"
                                                aria-label={`Restore ${alias}`}
                                            >
                                                <Plus className="size-3" />
                                            </button>
                                        </span>
                                    ))}
                                </div>
                            )}

                            {/* Add new alias input */}
                            <div className="flex gap-2">
                                <Input
                                    value={aliasInput}
                                    onChange={(e) =>
                                        setAliasInput(e.target.value)
                                    }
                                    onKeyDown={(e) => {
                                        if (e.key === 'Enter') {
                                            e.preventDefault();
                                            handleAddAlias();
                                        }
                                    }}
                                    placeholder="novo-alias.com"
                                    className="font-mono text-sm"
                                />
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={handleAddAlias}
                                    disabled={
                                        !aliasInput.trim() ||
                                        aliases.includes(
                                            aliasInput.trim().toLowerCase(),
                                        )
                                    }
                                >
                                    <Plus className="size-4" />
                                    {t('add', 'Adicionar')}
                                </Button>
                            </div>

                            {/* Diff summary */}
                            {(addedAliases.length > 0 ||
                                removedAliases.length > 0) && (
                                <div className="space-y-1 rounded-md border bg-muted/40 p-3 text-xs">
                                    {addedAliases.length > 0 && (
                                        <p className="text-green-700 dark:text-green-400">
                                            + {t('to_add', 'A adicionar')}:{' '}
                                            <span className="font-mono">
                                                {addedAliases.join(', ')}
                                            </span>
                                        </p>
                                    )}
                                    {removedAliases.length > 0 && (
                                        <p className="text-destructive">
                                            − {t('to_remove', 'A remover')}:{' '}
                                            <span className="font-mono">
                                                {removedAliases.join(', ')}
                                            </span>
                                        </p>
                                    )}
                                </div>
                            )}

                            {(errors.add_alias_domains ||
                                errors.delete_alias_domains) && (
                                <p className="text-xs text-destructive">
                                    {errors.add_alias_domains ||
                                        errors.delete_alias_domains}
                                </p>
                            )}
                        </CardContent>
                    </Card>

                    {/* Command Preview — one block per command */}
                    {changed && (
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-sm">
                                    {t('command_preview')}
                                </CardTitle>
                                <CardDescription>
                                    {t(
                                        'commands_run_sequentially',
                                        'Cada alteração é executada como um comando separado, em ordem.',
                                    )}
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-1">
                                <pre className="overflow-x-auto rounded-md bg-zinc-950 p-3 font-mono text-xs leading-relaxed text-green-400">
                                    {previewCommands
                                        .map((cmd, i) =>
                                            i === 0 ? cmd : `\n\n${cmd}`,
                                        )
                                        .join('')}
                                </pre>
                                {previewCommands.length > 1 && (
                                    <p className="text-xs text-muted-foreground">
                                        {previewCommands.length}{' '}
                                        {t(
                                            'separate_commands',
                                            'comandos separados',
                                        )}
                                    </p>
                                )}
                            </CardContent>
                        </Card>
                    )}

                    {/* Submit */}
                    <div className="flex justify-end gap-3">
                        <Button type="button" variant="outline" asChild>
                            <a href={`/sites/${site.id}`}>{t('cancel')}</a>
                        </Button>
                        <Button
                            type="submit"
                            disabled={processing || !server || !changed}
                        >
                            {processing ? (
                                <Loader2 className="size-4 animate-spin" />
                            ) : (
                                <Save className="size-4" />
                            )}
                            {t('apply_changes')}
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
