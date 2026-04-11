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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { useTranslations } from '@/hooks/useTranslations';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, usePage } from '@inertiajs/react';
import {
    AlertCircle,
    ArrowLeft,
    ChevronDown,
    Globe,
    Loader2,
    Network,
    Plus,
    Server,
    X,
} from 'lucide-react';
import { useState } from 'react';

interface SiteServer {
    id: number;
    name: string;
    ip_address: string;
    provisioning_engine: string | null;
}

interface Props {
    servers: SiteServer[];
    defaultServerId?: number | null;
}

interface FormData {
    server_id: string;
    domain: string;
    type: 'wp' | 'php' | 'html';
    php_version: string;
    title: string;
    public_dir: string;
    ssl: string;
    cache: boolean;
    skip_install: boolean;
    skip_content: boolean;
    locale: string;
    admin_user: string;
    admin_pass: string;
    admin_email: string;
    mu: string;
    local_db: boolean;
    alias_domains: string;
}

const PHP_VERSIONS = [
    '8.4',
    '8.3',
    '8.2',
    '8.1',
    '8.0',
    '7.4',
    '7.3',
    '7.2',
    '7.0',
    '5.6',
    'latest',
];

const DEFAULTS: FormData = {
    server_id: '',
    domain: '',
    type: 'wp',
    php_version: '8.4',
    title: '',
    public_dir: '',
    ssl: 'none',
    cache: true,
    skip_install: false,
    skip_content: false,
    locale: 'pt_BR',
    admin_user: '',
    admin_pass: '',
    admin_email: '',
    mu: '',
    local_db: false,
    alias_domains: '',
};

interface SharedData {
    flash?: { success?: string; error?: string };
    errors?: Record<string, string>;
}

export default function SiteCreate({ servers, defaultServerId }: Props) {
    const t = useTranslations();
    const { flash, errors } = usePage<{ props: SharedData }>()
        .props as unknown as SharedData;
    const [form, setForm] = useState<FormData>({
        ...DEFAULTS,
        server_id: defaultServerId ? String(defaultServerId) : '',
    });
    const [submitting, setSubmitting] = useState(false);
    const [adminOpen, setAdminOpen] = useState(false);
    const [advancedOpen, setAdvancedOpen] = useState(false);
    const [aliases, setAliases] = useState<string[]>([]);
    const [aliasInput, setAliasInput] = useState('');

    const handleAddAlias = () => {
        const trimmed = aliasInput.trim().toLowerCase();
        if (!trimmed || aliases.includes(trimmed)) return;
        setAliases((prev) => [...prev, trimmed]);
        setAliasInput('');
    };

    const handleRemoveAlias = (alias: string) => {
        setAliases((prev) => prev.filter((a) => a !== alias));
    };

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('servers'), href: '/servers' },
        { title: t('sites'), href: '/sites' },
        { title: t('create_site'), href: '/sites/create' },
    ];

    const set = <K extends keyof FormData>(key: K, value: FormData[K]) => {
        setForm((prev) => ({ ...prev, [key]: value }));
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setSubmitting(true);
        router.post(
            '/sites',
            {
                ...form,
                alias_domains: aliases.join(','),
            } as unknown as Parameters<typeof router.post>[1],
            {
                onFinish: () => setSubmitting(false),
            },
        );
    };

    const selectedServer = servers.find((s) => s.id === Number(form.server_id));
    const isWp = form.type === 'wp';
    const isPhpOrWp = form.type === 'php' || form.type === 'wp';

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('create_site')} />
            <div className="flex h-full flex-1 flex-col gap-6 p-6">
                {/* Header */}
                <div className="flex items-center gap-4">
                    <Button variant="outline" size="icon" asChild>
                        <a href="/sites">
                            <ArrowLeft className="size-4" />
                        </a>
                    </Button>
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {t('create_site')}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {t('configure_and_deploy_new_site')}
                        </p>
                    </div>
                </div>

                {flash?.error && (
                    <Alert variant="destructive">
                        <AlertCircle className="size-4" />
                        <AlertDescription>{flash.error}</AlertDescription>
                    </Alert>
                )}

                <form onSubmit={handleSubmit} className="space-y-6">
                    {/* Basic Configuration */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Globe className="size-4" />
                                {t('basic_configuration')}
                            </CardTitle>
                            <CardDescription>
                                {t('server_domain_and_site_type')}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {/* Server */}
                            <div className="space-y-2">
                                <Label htmlFor="server_id">{t('server')}</Label>
                                <Select
                                    value={form.server_id}
                                    onValueChange={(v) => set('server_id', v)}
                                >
                                    <SelectTrigger id="server_id">
                                        <SelectValue
                                            placeholder={t('select_a_server')}
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {servers.map((server) => (
                                            <SelectItem
                                                key={server.id}
                                                value={String(server.id)}
                                            >
                                                <span className="flex items-center gap-2">
                                                    <Server className="size-3.5 text-muted-foreground" />
                                                    {server.name}
                                                    <span className="text-xs text-muted-foreground">
                                                        {server.ip_address}
                                                    </span>
                                                    {server.provisioning_engine && (
                                                        <Badge
                                                            variant="outline"
                                                            className="text-xs"
                                                        >
                                                            {
                                                                server.provisioning_engine
                                                            }
                                                        </Badge>
                                                    )}
                                                </span>
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {errors?.server_id && (
                                    <p className="text-xs text-destructive">
                                        {errors.server_id}
                                    </p>
                                )}
                                {selectedServer &&
                                    !selectedServer.provisioning_engine && (
                                        <p className="text-xs text-amber-600">
                                            {t('server_no_provisioning_engine')}
                                        </p>
                                    )}
                            </div>

                            {/* Domain */}
                            <div className="space-y-2">
                                <Label htmlFor="domain">{t('domain')}</Label>
                                <Input
                                    id="domain"
                                    type="text"
                                    placeholder="example.com"
                                    value={form.domain}
                                    onChange={(e) =>
                                        set('domain', e.target.value)
                                    }
                                    className="font-mono"
                                />
                                {errors?.domain && (
                                    <p className="text-xs text-destructive">
                                        {errors.domain}
                                    </p>
                                )}
                            </div>

                            {/* Site Type */}
                            <div className="space-y-2">
                                <Label>{t('site_type')}</Label>
                                <div className="flex gap-2">
                                    {(['wp', 'php', 'html'] as const).map(
                                        (type) => (
                                            <button
                                                key={type}
                                                type="button"
                                                onClick={() =>
                                                    set('type', type)
                                                }
                                                className={[
                                                    'rounded-md border px-4 py-2 text-sm font-medium transition-colors',
                                                    form.type === type
                                                        ? 'border-primary bg-primary text-primary-foreground'
                                                        : 'hover:bg-muted',
                                                ].join(' ')}
                                            >
                                                {type === 'wp'
                                                    ? t('wordpress')
                                                    : type === 'php'
                                                      ? t('php')
                                                      : t('html')}
                                            </button>
                                        ),
                                    )}
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* WordPress Settings */}
                    {isWp && (
                        <Card>
                            <CardHeader>
                                <CardTitle>{t('wordpress_settings')}</CardTitle>
                                <CardDescription>
                                    {t('site_title_locale_and_multisite')}
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label htmlFor="title">
                                            {t('site_title')}
                                        </Label>
                                        <Input
                                            id="title"
                                            placeholder={t('my_wordpress_site')}
                                            value={form.title}
                                            onChange={(e) =>
                                                set('title', e.target.value)
                                            }
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="locale">
                                            {t('wordpress_locale')}{' '}
                                            <span className="text-xs text-muted-foreground">
                                                (ex: pt_BR, en_US)
                                            </span>
                                        </Label>
                                        <Input
                                            id="locale"
                                            placeholder="en_US"
                                            value={form.locale}
                                            onChange={(e) =>
                                                set('locale', e.target.value)
                                            }
                                        />
                                    </div>
                                </div>

                                <div className="space-y-2">
                                    <Label>{t('multisite')}</Label>
                                    <div className="flex gap-2">
                                        {[
                                            { value: '', label: t('disabled') },
                                            {
                                                value: 'subdir',
                                                label: t(
                                                    'multisite_subdirectory',
                                                ),
                                            },
                                            {
                                                value: 'subdom',
                                                label: t('multisite_subdomain'),
                                            },
                                        ].map((opt) => (
                                            <button
                                                key={opt.value}
                                                type="button"
                                                onClick={() =>
                                                    set('mu', opt.value)
                                                }
                                                className={[
                                                    'rounded-md border px-3 py-1.5 text-sm transition-colors',
                                                    form.mu === opt.value
                                                        ? 'border-primary bg-primary text-primary-foreground'
                                                        : 'hover:bg-muted',
                                                ].join(' ')}
                                            >
                                                {opt.label}
                                            </button>
                                        ))}
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    )}

                    {/* Environment — PHP version, public directory, cache (php/wp only) */}
                    {isPhpOrWp && (
                        <Card>
                            <CardHeader>
                                <CardTitle>{t('environment')}</CardTitle>
                                <CardDescription>
                                    {t(
                                        'php_version_public_directory_and_caching',
                                    )}
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label htmlFor="php_version">
                                            {t('php_version')}
                                        </Label>
                                        <Select
                                            value={form.php_version}
                                            onValueChange={(v) =>
                                                set('php_version', v)
                                            }
                                        >
                                            <SelectTrigger id="php_version">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {PHP_VERSIONS.map((v) => (
                                                    <SelectItem
                                                        key={v}
                                                        value={v}
                                                    >
                                                        PHP {v}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="public_dir">
                                            {t('public_directory')}{' '}
                                            <span className="text-xs text-muted-foreground">
                                                ({t('inside_htdocs')})
                                            </span>
                                        </Label>
                                        <Input
                                            id="public_dir"
                                            placeholder="current/web"
                                            value={form.public_dir}
                                            onChange={(e) =>
                                                set(
                                                    'public_dir',
                                                    e.target.value,
                                                )
                                            }
                                            className="font-mono"
                                        />
                                    </div>
                                </div>

                                <div className="grid gap-4 sm:grid-cols-3">
                                    <div className="flex items-center justify-between rounded-lg border p-3">
                                        <div>
                                            <p className="text-sm font-medium">
                                                {t('cache')}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {t('redis_nginx_cache')}
                                            </p>
                                        </div>
                                        <Switch
                                            checked={form.cache}
                                            onCheckedChange={(v: boolean) =>
                                                set('cache', v)
                                            }
                                        />
                                    </div>

                                    {isWp && (
                                        <>
                                            <div className="flex items-center justify-between rounded-lg border p-3">
                                                <div>
                                                    <p className="text-sm font-medium">
                                                        {t('skip_installation')}
                                                    </p>
                                                    <p className="text-xs text-muted-foreground">
                                                        {t(
                                                            'skip_wp_core_install',
                                                        )}
                                                    </p>
                                                </div>
                                                <Switch
                                                    checked={form.skip_install}
                                                    onCheckedChange={(
                                                        v: boolean,
                                                    ) => set('skip_install', v)}
                                                />
                                            </div>

                                            <div className="flex items-center justify-between rounded-lg border p-3">
                                                <div>
                                                    <p className="text-sm font-medium">
                                                        {t('skip_content')}
                                                    </p>
                                                    <p className="text-xs text-muted-foreground">
                                                        {t(
                                                            'no_default_themes_plugins',
                                                        )}
                                                    </p>
                                                </div>
                                                <Switch
                                                    checked={form.skip_content}
                                                    onCheckedChange={(
                                                        v: boolean,
                                                    ) => set('skip_content', v)}
                                                />
                                            </div>
                                        </>
                                    )}
                                </div>
                            </CardContent>
                        </Card>
                    )}

                    {/* SSL */}
                    {/* <Card>
                        <CardHeader>
                            <CardTitle>SSL</CardTitle>
                            <CardDescription>Certificate configuration</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="flex gap-2">
                                {[
                                    { value: 'none', label: 'None' },
                                    { value: 'le', label: "Let's Encrypt" },
                                    { value: 'self', label: 'Self-Signed' },
                                ].map((opt) => (
                                    <button
                                        key={opt.value}
                                        type="button"
                                        onClick={() => set('ssl', opt.value)}
                                        className={[
                                            'rounded-md border px-3 py-1.5 text-sm transition-colors',
                                            form.ssl === opt.value
                                                ? 'border-primary bg-primary text-primary-foreground'
                                                : 'hover:bg-muted',
                                        ].join(' ')}
                                    >
                                        {opt.label}
                                    </button>
                                ))}
                            </div>
                        </CardContent>
                    </Card> */}

                    {/* Admin Credentials (WordPress, collapsible) */}
                    {isWp && (
                        <Card>
                            <Collapsible
                                open={adminOpen}
                                onOpenChange={setAdminOpen}
                            >
                                <CollapsibleTrigger asChild>
                                    <CardHeader className="cursor-pointer select-none">
                                        <div className="flex items-center justify-between">
                                            <div>
                                                <CardTitle className="text-base">
                                                    {t('admin_credentials')}
                                                </CardTitle>
                                                <CardDescription>
                                                    {t(
                                                        'admin_credentials_optional',
                                                    )}
                                                </CardDescription>
                                            </div>
                                            <ChevronDown
                                                className={[
                                                    'size-4 text-muted-foreground transition-transform',
                                                    adminOpen
                                                        ? 'rotate-180'
                                                        : '',
                                                ].join(' ')}
                                            />
                                        </div>
                                    </CardHeader>
                                </CollapsibleTrigger>
                                <CollapsibleContent>
                                    <CardContent className="space-y-4 pt-0">
                                        <div className="grid gap-4 sm:grid-cols-2">
                                            <div className="space-y-2">
                                                <Label htmlFor="admin_user">
                                                    {t('admin_user')}
                                                </Label>
                                                <Input
                                                    id="admin_user"
                                                    placeholder="admin"
                                                    value={form.admin_user}
                                                    onChange={(e) =>
                                                        set(
                                                            'admin_user',
                                                            e.target.value,
                                                        )
                                                    }
                                                />
                                            </div>
                                            <div className="space-y-2">
                                                <Label htmlFor="admin_pass">
                                                    {t('admin_password')}
                                                </Label>
                                                <Input
                                                    id="admin_pass"
                                                    type="password"
                                                    placeholder="••••••••"
                                                    value={form.admin_pass}
                                                    onChange={(e) =>
                                                        set(
                                                            'admin_pass',
                                                            e.target.value,
                                                        )
                                                    }
                                                />
                                            </div>
                                        </div>
                                        <div className="space-y-2">
                                            <Label htmlFor="admin_email">
                                                {t('admin_email')}
                                            </Label>
                                            <Input
                                                id="admin_email"
                                                type="email"
                                                placeholder="admin@example.com"
                                                value={form.admin_email}
                                                onChange={(e) =>
                                                    set(
                                                        'admin_email',
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                        </div>
                                    </CardContent>
                                </CollapsibleContent>
                            </Collapsible>
                        </Card>
                    )}

                    {/* Advanced */}
                    <Card>
                        <Collapsible
                            open={advancedOpen}
                            onOpenChange={setAdvancedOpen}
                        >
                            <CollapsibleTrigger asChild>
                                <CardHeader className="cursor-pointer select-none">
                                    <div className="flex items-center justify-between">
                                        <div>
                                            <CardTitle className="text-base">
                                                {t('advanced')}
                                            </CardTitle>
                                            <CardDescription>
                                                {t(
                                                    'alias_domains_and_local_database',
                                                )}
                                            </CardDescription>
                                        </div>
                                        <ChevronDown
                                            className={[
                                                'size-4 text-muted-foreground transition-transform',
                                                advancedOpen
                                                    ? 'rotate-180'
                                                    : '',
                                            ].join(' ')}
                                        />
                                    </div>
                                </CardHeader>
                            </CollapsibleTrigger>
                            <CollapsibleContent>
                                <CardContent className="space-y-4 pt-0">
                                    {/* Alias Domains — tag-based UI */}
                                    <div className="space-y-3">
                                        <div>
                                            <Label className="flex items-center gap-2">
                                                <Network className="size-3.5" />
                                                {t('alias_domains')}
                                            </Label>
                                            <p className="mt-0.5 text-xs text-muted-foreground">
                                                {t(
                                                    'alias_domains_description',
                                                    'Domínios alias adicionais para este site.',
                                                )}
                                            </p>
                                        </div>

                                        {/* Tag list */}
                                        {aliases.length > 0 && (
                                            <div className="flex flex-wrap gap-2">
                                                {aliases.map((alias) => (
                                                    <span
                                                        key={alias}
                                                        className="inline-flex items-center gap-1 rounded-md border border-green-500/50 bg-green-500/10 px-2 py-1 font-mono text-xs text-green-700 dark:text-green-400"
                                                    >
                                                        {alias}
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
                                                    </span>
                                                ))}
                                            </div>
                                        )}

                                        {/* Add input */}
                                        <div className="flex gap-2">
                                            <Input
                                                value={aliasInput}
                                                onChange={(e) =>
                                                    setAliasInput(
                                                        e.target.value,
                                                    )
                                                }
                                                onKeyDown={(e) => {
                                                    if (e.key === 'Enter') {
                                                        e.preventDefault();
                                                        handleAddAlias();
                                                    }
                                                }}
                                                placeholder="alias.example.com"
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
                                                        aliasInput
                                                            .trim()
                                                            .toLowerCase(),
                                                    )
                                                }
                                            >
                                                <Plus className="size-4" />
                                                {t('add', 'Adicionar')}
                                            </Button>
                                        </div>

                                        {errors?.alias_domains && (
                                            <p className="text-xs text-destructive">
                                                {errors.alias_domains}
                                            </p>
                                        )}
                                    </div>

                                    <div className="flex items-center justify-between rounded-lg border p-3">
                                        <div>
                                            <p className="text-sm font-medium">
                                                {t('local_database')}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {t('isolated_db_container')}
                                            </p>
                                        </div>
                                        <Switch
                                            checked={form.local_db}
                                            onCheckedChange={(v: boolean) =>
                                                set('local_db', v)
                                            }
                                        />
                                    </div>
                                </CardContent>
                            </CollapsibleContent>
                        </Collapsible>
                    </Card>

                    {/* Generated command preview */}
                    {form.domain && (
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-sm">
                                    {t('command_preview')}
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <pre className="overflow-x-auto rounded-md bg-muted p-3 text-xs leading-relaxed">
                                    {[
                                        `sudo ee site create ${form.domain || '<domain>'}`,
                                        `  --type=${form.type}`,
                                        form.title
                                            ? `  --title="${form.title}"`
                                            : null,
                                        isPhpOrWp && form.php_version
                                            ? `  --php=${form.php_version}`
                                            : null,
                                        form.public_dir
                                            ? `  --public-dir=${form.public_dir}`
                                            : null,
                                        form.ssl && form.ssl !== 'none'
                                            ? `  --ssl=${form.ssl}`
                                            : null,
                                        isPhpOrWp && form.cache
                                            ? '  --cache'
                                            : null,
                                        isWp && form.skip_install
                                            ? '  --skip-install'
                                            : null,
                                        isWp && form.skip_content
                                            ? '  --skip-content'
                                            : null,
                                        isWp && form.locale
                                            ? `  --locale=${form.locale}`
                                            : null,
                                        isWp && form.mu
                                            ? `  --mu=${form.mu}`
                                            : null,
                                        isWp && form.admin_user
                                            ? `  --admin-user=${form.admin_user}`
                                            : null,
                                        isWp && form.admin_pass
                                            ? '  --admin-pass=••••••••'
                                            : null,
                                        isWp && form.admin_email
                                            ? `  --admin-email=${form.admin_email}`
                                            : null,
                                        aliases.length > 0
                                            ? `  --alias-domains='${aliases.join(',')}'`
                                            : null,
                                    ]
                                        .filter(Boolean)
                                        .join(' \\\n')}
                                </pre>
                            </CardContent>
                        </Card>
                    )}

                    {/* Submit */}
                    <div className="flex justify-end gap-3">
                        <Button variant="outline" type="button" asChild>
                            <a href="/sites">{t('cancel')}</a>
                        </Button>
                        <Button
                            type="submit"
                            disabled={
                                submitting || !form.server_id || !form.domain
                            }
                        >
                            {submitting && (
                                <Loader2 className="size-4 animate-spin" />
                            )}
                            {submitting
                                ? t('creating') + '…'
                                : t('create_site')}
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
