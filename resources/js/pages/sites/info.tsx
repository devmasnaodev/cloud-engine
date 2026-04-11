import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useTranslations } from '@/hooks/useTranslations';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import {
    ArrowLeft,
    Database,
    ExternalLink,
    Globe,
    Lock,
    Server,
    Settings,
    Shield,
    Zap,
} from 'lucide-react';
import { useState } from 'react';

interface SiteInfo {
    id?: number;
    site_url?: string;
    site_type?: string;
    site_fs_path?: string;
    site_container_fs_path?: string;
    site_enabled?: number | boolean | string;
    site_ssl?: string;
    site_ssl_wildcard?: number | boolean;
    cache_nginx_browser?: number | boolean;
    cache_nginx_fullpage?: number | boolean;
    cache_mysql_query?: number | boolean;
    cache_app_object?: number | boolean;
    cache_host?: string;
    proxy_cache?: string;
    php_version?: string;
    db_name?: string;
    db_user?: string;
    db_password?: string;
    db_root_password?: string;
    db_host?: string;
    db_port?: string | number;
    app_sub_type?: string;
    app_admin_url?: string;
    app_admin_email?: string;
    app_admin_username?: string;
    app_admin_password?: string;
    app_mail?: string;
    alias_domains?: string;
    mailhog_enabled?: number | boolean;
    admin_tools?: number | boolean;
    created_on?: string;
    modified_on?: string;
    site_auth_scope?: string | null;
    site_auth_username?: string | null;
    site_auth_password?: string | null;
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
    ip_address: string;
    provisioning_engine: string | null;
}

interface Props {
    site: Site;
    server: SiteServer | null;
}

function InfoRow({ label, value }: { label: string; value: React.ReactNode }) {
    return (
        <div className="flex items-center justify-between py-1.5">
            <span className="text-sm text-muted-foreground">{label}</span>
            <span className="text-sm font-medium">
                {value ?? <span className="text-muted-foreground">—</span>}
            </span>
        </div>
    );
}

function BooleanBadge({
    value,
}: {
    value: number | boolean | string | undefined | null;
}) {
    const enabled =
        value === 1 || value === true || value === '1' || value === 'on';
    return (
        <Badge variant={enabled ? 'default' : 'secondary'}>
            {enabled ? 'on' : 'off'}
        </Badge>
    );
}

function SecretField({ value }: { value: string | null | undefined }) {
    const [visible, setVisible] = useState(false);

    if (!value) return <span className="text-muted-foreground">—</span>;

    return (
        <span className="flex items-center gap-2">
            <span className="font-mono text-xs">
                {visible ? value : '••••••••••••'}
            </span>
            <button
                type="button"
                onClick={() => setVisible((v) => !v)}
                className="text-xs text-primary underline underline-offset-2"
            >
                {visible ? 'hide' : 'show'}
            </button>
        </span>
    );
}

export default function SiteInfoPage({ site, server }: Props) {
    const t = useTranslations();
    const info = site.info;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('servers'), href: '/servers' },
        { title: t('sites'), href: '/sites' },
        { title: site.domain, href: `/sites/${site.id}` },
        {
            title: t('full_details', 'Detalhes completos'),
            href: `/sites/${site.id}/info`,
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head
                title={`${site.domain} — ${t('full_details', 'Detalhes completos')}`}
            />
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
                                {site.domain}
                            </h1>
                        </div>
                        <p className="ml-9 text-sm text-muted-foreground">
                            {t('full_details', 'Detalhes completos')} —{' '}
                            {t(
                                'configuration_from_last_refresh',
                                'Configuração do último refresh',
                            )}
                        </p>
                    </div>
                </div>

                {!info ? (
                    <Card>
                        <CardContent className="py-8 text-center text-sm text-muted-foreground">
                            {t(
                                'no_site_info_available',
                                'Nenhuma informação disponível. Faça um refresh do site para obter os dados.',
                            )}
                        </CardContent>
                    </Card>
                ) : (
                    <div className="grid gap-6 md:grid-cols-2">
                        {/* General */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Settings className="size-4" />
                                    {t('general', 'Geral')}
                                </CardTitle>
                                <CardDescription>
                                    {t(
                                        'site_general_info',
                                        'Informações gerais do site',
                                    )}
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="divide-y">
                                <InfoRow
                                    label={t('domain', 'Domínio')}
                                    value={
                                        <span className="flex items-center gap-1 font-mono">
                                            {info.site_url ?? site.domain}
                                            <a
                                                href={`http://${info.site_url ?? site.domain}`}
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
                                    label={t('site_type', 'Tipo do site')}
                                    value={
                                        info.site_type ? (
                                            <Badge variant="outline">
                                                {info.site_type}
                                            </Badge>
                                        ) : null
                                    }
                                />
                                <InfoRow
                                    label={t('app_sub_type', 'Sub-tipo')}
                                    value={
                                        info.app_sub_type ? (
                                            <Badge variant="outline">
                                                {info.app_sub_type}
                                            </Badge>
                                        ) : null
                                    }
                                />
                                <InfoRow
                                    label={t('php_version', 'Versão PHP')}
                                    value={
                                        info.php_version ? (
                                            <Badge variant="outline">
                                                PHP {info.php_version}
                                            </Badge>
                                        ) : null
                                    }
                                />
                                <InfoRow
                                    label={t('status', 'Status')}
                                    value={
                                        <BooleanBadge
                                            value={info.site_enabled}
                                        />
                                    }
                                />
                                <InfoRow
                                    label={t('alias_domains', 'Domínios alias')}
                                    value={
                                        info.alias_domains ? (
                                            <span className="font-mono text-xs">
                                                {info.alias_domains}
                                            </span>
                                        ) : null
                                    }
                                />
                                <InfoRow
                                    label={t('mail', 'E-mail')}
                                    value={
                                        info.app_mail ? (
                                            <span className="font-mono text-xs">
                                                {info.app_mail}
                                            </span>
                                        ) : null
                                    }
                                />
                                <InfoRow
                                    label={t('mailhog_enabled', 'Mailhog')}
                                    value={
                                        <BooleanBadge
                                            value={info.mailhog_enabled}
                                        />
                                    }
                                />
                                <InfoRow
                                    label={t('admin_tools', 'Admin Tools')}
                                    value={
                                        <BooleanBadge
                                            value={info.admin_tools}
                                        />
                                    }
                                />
                                <InfoRow
                                    label={t('created_on', 'Criado em')}
                                    value={info.created_on ?? null}
                                />
                                <InfoRow
                                    label={t('modified_on', 'Modificado em')}
                                    value={info.modified_on ?? null}
                                />
                            </CardContent>
                        </Card>

                        {/* Filesystem */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Server className="size-4" />
                                    {t('filesystem', 'Sistema de Arquivos')}
                                </CardTitle>
                                <CardDescription>
                                    {t('paths_and_ssl', 'Caminhos e SSL')}
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="divide-y">
                                <InfoRow
                                    label={t(
                                        'site_fs_path',
                                        'Caminho no servidor',
                                    )}
                                    value={
                                        info.site_fs_path ? (
                                            <span className="font-mono text-xs">
                                                {info.site_fs_path}
                                            </span>
                                        ) : null
                                    }
                                />
                                <InfoRow
                                    label={t(
                                        'site_container_fs_path',
                                        'Caminho no container',
                                    )}
                                    value={
                                        info.site_container_fs_path ? (
                                            <span className="font-mono text-xs">
                                                {info.site_container_fs_path}
                                            </span>
                                        ) : null
                                    }
                                />
                                <InfoRow
                                    label={t(
                                        'ssl_certificate',
                                        'Certificado SSL',
                                    )}
                                    value={
                                        info.site_ssl ? (
                                            <Badge variant="outline">
                                                {info.site_ssl}
                                            </Badge>
                                        ) : (
                                            <span className="text-muted-foreground">
                                                {t('none', 'Nenhum')}
                                            </span>
                                        )
                                    }
                                />
                                <InfoRow
                                    label={t('ssl_wildcard', 'SSL Wildcard')}
                                    value={
                                        <BooleanBadge
                                            value={info.site_ssl_wildcard}
                                        />
                                    }
                                />
                                <InfoRow
                                    label={t('proxy_cache', 'Proxy Cache')}
                                    value={
                                        info.proxy_cache ? (
                                            <Badge variant="outline">
                                                {info.proxy_cache}
                                            </Badge>
                                        ) : null
                                    }
                                />
                            </CardContent>
                        </Card>

                        {/* Cache */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Zap className="size-4" />
                                    {t('cache', 'Cache')}
                                </CardTitle>
                                <CardDescription>
                                    {t(
                                        'cache_configuration',
                                        'Configuração de cache',
                                    )}
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="divide-y">
                                <InfoRow
                                    label={t(
                                        'cache_nginx_browser',
                                        'Nginx Browser Cache',
                                    )}
                                    value={
                                        <BooleanBadge
                                            value={info.cache_nginx_browser}
                                        />
                                    }
                                />
                                <InfoRow
                                    label={t(
                                        'cache_nginx_fullpage',
                                        'Nginx Full Page Cache',
                                    )}
                                    value={
                                        <BooleanBadge
                                            value={info.cache_nginx_fullpage}
                                        />
                                    }
                                />
                                <InfoRow
                                    label={t(
                                        'cache_mysql_query',
                                        'MySQL Query Cache',
                                    )}
                                    value={
                                        <BooleanBadge
                                            value={info.cache_mysql_query}
                                        />
                                    }
                                />
                                <InfoRow
                                    label={t(
                                        'cache_app_object',
                                        'App Object Cache',
                                    )}
                                    value={
                                        <BooleanBadge
                                            value={info.cache_app_object}
                                        />
                                    }
                                />
                                <InfoRow
                                    label={t('cache_host', 'Cache Host')}
                                    value={
                                        info.cache_host ? (
                                            <span className="font-mono text-xs">
                                                {info.cache_host}
                                            </span>
                                        ) : null
                                    }
                                />
                            </CardContent>
                        </Card>

                        {/* Database */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Database className="size-4" />
                                    {t('database', 'Banco de Dados')}
                                </CardTitle>
                                <CardDescription>
                                    {t(
                                        'database_credentials',
                                        'Credenciais do banco de dados',
                                    )}
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="divide-y">
                                <InfoRow
                                    label={t('db_name', 'Nome do banco')}
                                    value={
                                        info.db_name ? (
                                            <span className="font-mono text-xs">
                                                {info.db_name}
                                            </span>
                                        ) : null
                                    }
                                />
                                <InfoRow
                                    label={t('db_user', 'Usuário')}
                                    value={
                                        info.db_user ? (
                                            <span className="font-mono text-xs">
                                                {info.db_user}
                                            </span>
                                        ) : null
                                    }
                                />
                                <InfoRow
                                    label={t('db_password', 'Senha')}
                                    value={
                                        <SecretField value={info.db_password} />
                                    }
                                />
                                <InfoRow
                                    label={t('db_root_password', 'Senha root')}
                                    value={
                                        <SecretField
                                            value={info.db_root_password}
                                        />
                                    }
                                />
                                <InfoRow
                                    label={t('db_host', 'Host')}
                                    value={
                                        info.db_host ? (
                                            <span className="font-mono text-xs">
                                                {info.db_host}
                                            </span>
                                        ) : null
                                    }
                                />
                                <InfoRow
                                    label={t('db_port', 'Porta')}
                                    value={
                                        info.db_port ? (
                                            <span className="font-mono text-xs">
                                                {info.db_port}
                                            </span>
                                        ) : null
                                    }
                                />
                            </CardContent>
                        </Card>

                        {/* WordPress Admin */}
                        {(info.app_admin_username ||
                            info.app_admin_email ||
                            info.app_admin_password ||
                            info.app_admin_url) && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <Shield className="size-4" />
                                        {t(
                                            'wordpress_admin',
                                            'WordPress Admin',
                                        )}
                                    </CardTitle>
                                    <CardDescription>
                                        {t(
                                            'wp_admin_credentials',
                                            'Credenciais de administrador',
                                        )}
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="divide-y">
                                    <InfoRow
                                        label={t(
                                            'app_admin_url',
                                            'Título do site',
                                        )}
                                        value={info.app_admin_url ?? null}
                                    />
                                    <InfoRow
                                        label={t(
                                            'app_admin_email',
                                            'E-mail admin',
                                        )}
                                        value={
                                            info.app_admin_email ? (
                                                <span className="font-mono text-xs">
                                                    {info.app_admin_email}
                                                </span>
                                            ) : null
                                        }
                                    />
                                    <InfoRow
                                        label={t(
                                            'app_admin_username',
                                            'Usuário admin',
                                        )}
                                        value={
                                            info.app_admin_username ? (
                                                <span className="font-mono text-xs">
                                                    {info.app_admin_username}
                                                </span>
                                            ) : null
                                        }
                                    />
                                    <InfoRow
                                        label={t(
                                            'app_admin_password',
                                            'Senha admin',
                                        )}
                                        value={
                                            <SecretField
                                                value={info.app_admin_password}
                                            />
                                        }
                                    />
                                </CardContent>
                            </Card>
                        )}

                        {/* Site Auth */}
                        {(info.site_auth_scope ||
                            info.site_auth_username ||
                            info.site_auth_password) && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <Lock className="size-4" />
                                        {t('site_auth', 'Autenticação do Site')}
                                    </CardTitle>
                                    <CardDescription>
                                        {t(
                                            'http_basic_auth',
                                            'Autenticação HTTP básica',
                                        )}
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="divide-y">
                                    <InfoRow
                                        label={t('site_auth_scope', 'Escopo')}
                                        value={info.site_auth_scope ?? null}
                                    />
                                    <InfoRow
                                        label={t(
                                            'site_auth_username',
                                            'Usuário',
                                        )}
                                        value={
                                            info.site_auth_username ? (
                                                <span className="font-mono text-xs">
                                                    {info.site_auth_username}
                                                </span>
                                            ) : null
                                        }
                                    />
                                    <InfoRow
                                        label={t('site_auth_password', 'Senha')}
                                        value={
                                            <SecretField
                                                value={info.site_auth_password}
                                            />
                                        }
                                    />
                                </CardContent>
                            </Card>
                        )}

                        {/* Server */}
                        {server && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <Server className="size-4" />
                                        {t('server', 'Servidor')}
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="divide-y">
                                    <InfoRow
                                        label={t('name', 'Nome')}
                                        value={server.name}
                                    />
                                    <InfoRow
                                        label={t('ip_address', 'Endereço IP')}
                                        value={
                                            <span className="font-mono text-sm">
                                                {server.ip_address}
                                            </span>
                                        }
                                    />
                                    <InfoRow
                                        label={t('engine', 'Engine')}
                                        value={
                                            <Badge
                                                variant={
                                                    server.provisioning_engine
                                                        ? 'outline'
                                                        : 'secondary'
                                                }
                                            >
                                                {server.provisioning_engine ??
                                                    t(
                                                        'no_engine',
                                                        'Sem engine',
                                                    )}
                                            </Badge>
                                        }
                                    />
                                </CardContent>
                            </Card>
                        )}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
