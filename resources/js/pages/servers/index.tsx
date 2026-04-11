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
import {
    create as serversCreate,
    index as serversIndex,
} from '@/routes/servers';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { Plus, Server as ServerIcon } from 'lucide-react';

interface Server {
    id: number;
    name: string;
    ip_address: string;
    ssh_port: number;
    ssh_execution_username: string;
    ssh_users_count: number;
    provisioning_engine: string;
    is_active: boolean;
    last_connected_at: string | null;
    created_at: string;
}

interface Props {
    servers: Server[];
}

export default function ServersIndex({ servers }: Props) {
    const t = useTranslations();

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: t('servers'),
            href: serversIndex().url,
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('servers')} />
            <div className="flex h-full flex-1 flex-col gap-6 p-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {t('servers')}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {t('manage_your_vps_servers')}
                        </p>
                    </div>
                    <Button asChild>
                        <Link href={serversCreate().url}>
                            <Plus className="size-4" />
                            {t('add_server')}
                        </Link>
                    </Button>
                </div>

                {servers.length === 0 ? (
                    <Card>
                        <CardContent className="flex flex-col items-center justify-center py-12">
                            <ServerIcon className="size-12 text-muted-foreground" />
                            <h3 className="mt-4 text-lg font-semibold">
                                {t('no_servers_yet')}
                            </h3>
                            <p className="mb-4 text-sm text-muted-foreground">
                                {t('get_started_by_adding')}
                            </p>
                            <Button asChild>
                                <Link href={serversCreate().url}>
                                    <Plus className="size-4" />
                                    {t('add_server')}
                                </Link>
                            </Button>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                        {servers.map((server) => (
                            <Link
                                key={server.id}
                                href={`/servers/${server.id}`}
                                className="transition-transform hover:scale-[1.02]"
                            >
                                <Card className="h-full">
                                    <CardHeader>
                                        <div className="flex items-start justify-between">
                                            <CardTitle className="flex items-center gap-2">
                                                <ServerIcon className="size-5" />
                                                {server.name}
                                            </CardTitle>
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
                                        <CardDescription>
                                            {server.ip_address}:
                                            {server.ssh_port}
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent className="space-y-2">
                                        <div className="flex items-center justify-between text-sm">
                                            <span className="text-muted-foreground">
                                                {t('engine')}:
                                            </span>
                                            <Badge variant="outline">
                                                {server.provisioning_engine}
                                            </Badge>
                                        </div>
                                        <div className="flex items-center justify-between text-sm">
                                            <span className="text-muted-foreground">
                                                {t('ssh_user')}:
                                            </span>
                                            <span className="font-mono">
                                                {server.ssh_execution_username}
                                            </span>
                                        </div>
                                        <div className="flex items-center justify-between text-sm">
                                            <span className="text-muted-foreground">
                                                {t('ssh_users')}:
                                            </span>
                                            <span>
                                                {server.ssh_users_count}
                                            </span>
                                        </div>
                                        <div className="flex items-center justify-between text-sm">
                                            <span className="text-muted-foreground">
                                                {t('last_connected')}:
                                            </span>
                                            <span>
                                                {server.last_connected_at ||
                                                    t('never')}
                                            </span>
                                        </div>
                                        <div className="flex items-center justify-between text-sm">
                                            <span className="text-muted-foreground">
                                                {t('created')}:
                                            </span>
                                            <span>{server.created_at}</span>
                                        </div>
                                    </CardContent>
                                </Card>
                            </Link>
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
