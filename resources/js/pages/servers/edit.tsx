import { update as serverUpdate } from '@/actions/App/Http/Controllers/ServerController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslations } from '@/hooks/useTranslations';
import AppLayout from '@/layouts/app-layout';
import { index as serversIndex } from '@/routes/servers';
import { type BreadcrumbItem } from '@/types';
import { Form, Head } from '@inertiajs/react';
import { ArrowLeft, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';

interface Server {
    id: number;
    name: string;
    ip_address: string;
    ssh_port: number;
    ssh_execution_username: string;
    ssh_users: Array<{ username: string }>;
    provisioning_engine: string;
    is_active: boolean;
}

interface Props {
    server: Server;
}

export default function ServersEdit({ server }: Props) {
    const t = useTranslations();
    const [sshUsers, setSshUsers] = useState(() =>
        server.ssh_users.length > 0
            ? server.ssh_users.map((sshUser, index) => ({
                  id: index + 1,
                  username: sshUser.username,
              }))
            : [{ id: 1, username: 'root' }],
    );
    const [executionUsername, setExecutionUsername] = useState(
        server.ssh_execution_username,
    );

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: t('servers'),
            href: serversIndex().url,
        },
        {
            title: server.name,
            href: `/servers/${server.id}`,
        },
        {
            title: t('edit'),
            href: '#',
        },
    ];

    const hasRemovableUsers = sshUsers.length > 1;

    const addSshUser = () => {
        const nextId = Math.max(...sshUsers.map((sshUser) => sshUser.id)) + 1;
        const nextUsername = `user${sshUsers.length + 1}`;

        setSshUsers((currentUsers) => [
            ...currentUsers,
            { id: nextId, username: nextUsername },
        ]);
    };

    const removeSshUser = (userId: number) => {
        setSshUsers((currentUsers) => {
            const userToRemove = currentUsers.find(
                (sshUser) => sshUser.id === userId,
            );
            const nextUsers = currentUsers.filter(
                (sshUser) => sshUser.id !== userId,
            );

            if (
                userToRemove &&
                userToRemove.username === executionUsername &&
                nextUsers.length > 0
            ) {
                setExecutionUsername(nextUsers[0].username);
            }

            return nextUsers.length > 0 ? nextUsers : currentUsers;
        });
    };

    const updateUsername = (userId: number, username: string) => {
        setSshUsers((currentUsers) =>
            currentUsers.map((sshUser) => {
                if (sshUser.id !== userId) {
                    return sshUser;
                }

                if (sshUser.username === executionUsername) {
                    setExecutionUsername(username);
                }

                return {
                    ...sshUser,
                    username,
                };
            }),
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${t('edit')} ${server.name}`} />
            <div className="flex h-full flex-1 flex-col gap-6 p-6">
                <div className="flex items-center gap-4">
                    <Button variant="outline" size="icon" asChild>
                        <a href={`/servers/${server.id}`}>
                            <ArrowLeft className="size-4" />
                        </a>
                    </Button>
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {t('edit_server')}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {t(
                                'update_server_connection_details',
                                'Update server connection details',
                            )}
                        </p>
                    </div>
                </div>

                <Card className="max-w-2xl">
                    <CardHeader>
                        <CardTitle>
                            {t('server_details', 'Server Details')}
                        </CardTitle>
                        <CardDescription>
                            {t(
                                'update_connection_details_for_server',
                                'Update the connection details for this server',
                            )}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Form {...serverUpdate.form(server.id)}>
                            {({ errors, processing }) => {
                                const formErrors = errors as Record<
                                    string,
                                    string
                                >;

                                return (
                                    <div className="space-y-6">
                                        <div className="space-y-2">
                                            <Label htmlFor="name">
                                                {t('server_name')}
                                            </Label>
                                            <Input
                                                id="name"
                                                name="name"
                                                type="text"
                                                defaultValue={server.name}
                                                required
                                            />
                                            <InputError message={errors.name} />
                                        </div>

                                        <div className="grid gap-4 sm:grid-cols-2">
                                            <div className="space-y-2">
                                                <Label htmlFor="ip_address">
                                                    {t('ip_address')}
                                                </Label>
                                                <Input
                                                    id="ip_address"
                                                    name="ip_address"
                                                    type="text"
                                                    defaultValue={
                                                        server.ip_address
                                                    }
                                                    required
                                                />
                                                <InputError
                                                    message={errors.ip_address}
                                                />
                                            </div>

                                            <div className="space-y-2">
                                                <Label htmlFor="ssh_port">
                                                    {t('ssh_port', 'SSH Port')}
                                                </Label>
                                                <Input
                                                    id="ssh_port"
                                                    name="ssh_port"
                                                    type="number"
                                                    defaultValue={
                                                        server.ssh_port
                                                    }
                                                    min="1"
                                                    max="65535"
                                                    required
                                                />
                                                <InputError
                                                    message={errors.ssh_port}
                                                />
                                            </div>
                                        </div>

                                        <div className="space-y-4">
                                            <div className="flex items-center justify-between gap-4">
                                                <div>
                                                    <Label>
                                                        {t('ssh_users')}
                                                    </Label>
                                                    <p className="text-xs text-muted-foreground">
                                                        {t(
                                                            'manage_ssh_users_and_keys',
                                                            'Add, remove or rotate SSH users. Leave private key empty to keep current key.',
                                                        )}
                                                    </p>
                                                </div>
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    onClick={addSshUser}
                                                >
                                                    <Plus className="size-4" />
                                                    {t('add_user', 'Add user')}
                                                </Button>
                                            </div>

                                            <div className="space-y-4">
                                                {sshUsers.map(
                                                    (sshUser, index) => (
                                                        <div
                                                            key={sshUser.id}
                                                            className="space-y-3 rounded-lg border p-4"
                                                        >
                                                            <div className="flex items-center justify-between gap-4">
                                                                <p className="text-sm font-medium">
                                                                    {t(
                                                                        'ssh_user',
                                                                    )}{' '}
                                                                    {index + 1}
                                                                </p>
                                                                <Button
                                                                    type="button"
                                                                    variant="ghost"
                                                                    disabled={
                                                                        !hasRemovableUsers
                                                                    }
                                                                    onClick={() =>
                                                                        removeSshUser(
                                                                            sshUser.id,
                                                                        )
                                                                    }
                                                                >
                                                                    <Trash2 className="size-4" />
                                                                    {t(
                                                                        'remove',
                                                                        'Remove',
                                                                    )}
                                                                </Button>
                                                            </div>

                                                            <div className="space-y-2">
                                                                <Label
                                                                    htmlFor={`ssh_users_${sshUser.id}_username`}
                                                                >
                                                                    {t(
                                                                        'username',
                                                                        'Username',
                                                                    )}
                                                                </Label>
                                                                <Input
                                                                    id={`ssh_users_${sshUser.id}_username`}
                                                                    name={`ssh_users[${index}][username]`}
                                                                    type="text"
                                                                    value={
                                                                        sshUser.username
                                                                    }
                                                                    onChange={(
                                                                        event,
                                                                    ) =>
                                                                        updateUsername(
                                                                            sshUser.id,
                                                                            event
                                                                                .target
                                                                                .value,
                                                                        )
                                                                    }
                                                                    required
                                                                />
                                                                <InputError
                                                                    message={
                                                                        formErrors[
                                                                            `ssh_users.${index}.username`
                                                                        ]
                                                                    }
                                                                />
                                                            </div>

                                                            <div className="space-y-2">
                                                                <Label
                                                                    htmlFor={`ssh_users_${sshUser.id}_private_key`}
                                                                >
                                                                    {t(
                                                                        'new_private_key',
                                                                        'New Private Key',
                                                                    )}
                                                                    (
                                                                    {t(
                                                                        'optional',
                                                                    )}
                                                                    )
                                                                </Label>
                                                                <textarea
                                                                    id={`ssh_users_${sshUser.id}_private_key`}
                                                                    name={`ssh_users[${index}][private_key]`}
                                                                    className="flex min-h-[140px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50"
                                                                    placeholder={t(
                                                                        'leave_empty_keep_existing_key',
                                                                        'Leave empty to keep existing key',
                                                                    )}
                                                                />
                                                                <InputError
                                                                    message={
                                                                        formErrors[
                                                                            `ssh_users.${index}.private_key`
                                                                        ]
                                                                    }
                                                                />
                                                            </div>
                                                        </div>
                                                    ),
                                                )}
                                            </div>

                                            <InputError
                                                message={formErrors.ssh_users}
                                            />
                                        </div>

                                        <div className="space-y-2">
                                            <Label htmlFor="ssh_execution_username">
                                                {t(
                                                    'command_execution_user',
                                                    'Command Execution User',
                                                )}
                                            </Label>
                                            <input
                                                type="hidden"
                                                name="ssh_execution_username"
                                                value={executionUsername}
                                            />
                                            <div className="space-y-2 rounded-md border p-3">
                                                {sshUsers
                                                    .filter(
                                                        (sshUser) =>
                                                            sshUser.username.trim() !==
                                                            '',
                                                    )
                                                    .map((sshUser) => (
                                                        <label
                                                            key={sshUser.id}
                                                            className="flex items-center gap-2 text-sm"
                                                        >
                                                            <input
                                                                type="radio"
                                                                checked={
                                                                    executionUsername ===
                                                                    sshUser.username
                                                                }
                                                                onChange={() =>
                                                                    setExecutionUsername(
                                                                        sshUser.username,
                                                                    )
                                                                }
                                                            />
                                                            <span>
                                                                {
                                                                    sshUser.username
                                                                }
                                                            </span>
                                                        </label>
                                                    ))}
                                            </div>
                                            <InputError
                                                message={
                                                    formErrors.ssh_execution_username
                                                }
                                            />
                                        </div>

                                        <div className="flex items-center gap-2">
                                            <Checkbox
                                                id="is_active"
                                                name="is_active"
                                                defaultChecked={
                                                    server.is_active
                                                }
                                            />
                                            <Label
                                                htmlFor="is_active"
                                                className="font-normal"
                                            >
                                                {t(
                                                    'server_is_active',
                                                    'Server is active',
                                                )}
                                            </Label>
                                        </div>

                                        <div className="flex gap-2">
                                            <Button
                                                type="submit"
                                                disabled={processing}
                                            >
                                                {processing
                                                    ? t('updating')
                                                    : t(
                                                          'update_server',
                                                          'Update Server',
                                                      )}
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                asChild
                                            >
                                                <a
                                                    href={`/servers/${server.id}`}
                                                >
                                                    {t('cancel')}
                                                </a>
                                            </Button>
                                        </div>
                                    </div>
                                );
                            }}
                        </Form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
