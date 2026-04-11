import InputError from '@/components/input-error';
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
import { index as serversIndex, store as serversStore } from '@/routes/servers';
import { type BreadcrumbItem } from '@/types';
import { Form, Head } from '@inertiajs/react';
import { ArrowLeft, Plus, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';

export default function ServersCreate() {
    const t = useTranslations();
    const [sshUsers, setSshUsers] = useState([{ id: 1, username: 'root' }]);
    const [executionUsername, setExecutionUsername] = useState('root');
    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: t('servers'),
            href: serversIndex().url,
        },
        {
            title: t('create_server'),
            href: '#',
        },
    ];

    const hasRemovableUsers = sshUsers.length > 1;

    const selectableExecutionUsers = useMemo(
        () => sshUsers.filter((sshUser) => sshUser.username.trim() !== ''),
        [sshUsers],
    );

    const addSshUser = () => {
        const nextId = Math.max(...sshUsers.map((sshUser) => sshUser.id)) + 1;
        const nextUsername = `user${sshUsers.length + 1}`;

        setSshUsers((currentUsers) => [
            ...currentUsers,
            { id: nextId, username: nextUsername },
        ]);

        if (executionUsername.trim() === '') {
            setExecutionUsername(nextUsername);
        }
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

                const previousUsername = sshUser.username;

                if (previousUsername === executionUsername) {
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
            <Head title={t('create_server')} />
            <div className="flex h-full flex-1 flex-col gap-6 p-6">
                <div className="flex items-center gap-4">
                    <Button variant="outline" size="icon" asChild>
                        <a href={serversIndex().url}>
                            <ArrowLeft className="size-4" />
                        </a>
                    </Button>
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {t('add_server')}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {t(
                                'configure_new_vps_server_connection',
                                'Configure a new VPS server connection',
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
                                'enter_server_connection_details',
                                'Enter the connection details for your VPS server',
                            )}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Form {...serversStore.form()}>
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
                                                placeholder={t(
                                                    'my_production_server',
                                                    'My Production Server',
                                                )}
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
                                                    placeholder="192.168.1.100"
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
                                                    defaultValue="22"
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
                                                            'configure_users_and_private_keys',
                                                            'Configure one or more users and their private keys',
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
                                                                    placeholder="root"
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
                                                                        'private_key',
                                                                        'Private Key',
                                                                    )}
                                                                </Label>
                                                                <textarea
                                                                    id={`ssh_users_${sshUser.id}_private_key`}
                                                                    name={`ssh_users[${index}][private_key]`}
                                                                    className="flex min-h-[160px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50"
                                                                    placeholder="-----BEGIN OPENSSH PRIVATE KEY-----&#10;...&#10;-----END OPENSSH PRIVATE KEY-----"
                                                                    required
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
                                                {selectableExecutionUsers.map(
                                                    (sshUser) => (
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
                                                                {sshUser.username ||
                                                                    t(
                                                                        'empty_username',
                                                                        '(empty username)',
                                                                    )}
                                                            </span>
                                                        </label>
                                                    ),
                                                )}
                                            </div>
                                            <InputError
                                                message={
                                                    formErrors.ssh_execution_username
                                                }
                                            />
                                        </div>

                                        <div className="space-y-2">
                                            <Label htmlFor="provisioning_engine">
                                                {t('engine')}
                                            </Label>
                                            <Select
                                                name="provisioning_engine"
                                                defaultValue="none"
                                            >
                                                <SelectTrigger id="provisioning_engine">
                                                    <SelectValue
                                                        placeholder={t(
                                                            'select_engine_optional',
                                                            'Select engine (optional)',
                                                        )}
                                                    />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="none">
                                                        {t(
                                                            'none_configure_later',
                                                            'None (configure later)',
                                                        )}
                                                    </SelectItem>
                                                    <SelectItem value="easyengine">
                                                        EasyEngine
                                                    </SelectItem>
                                                </SelectContent>
                                            </Select>
                                            <InputError
                                                message={
                                                    errors.provisioning_engine
                                                }
                                            />
                                        </div>

                                        <p className="text-xs text-muted-foreground">
                                            {t(
                                                'private_keys_encrypted_before_storage',
                                                'All private keys are encrypted before storage.',
                                            )}
                                        </p>

                                        <div className="flex gap-2">
                                            <Button
                                                type="submit"
                                                disabled={processing}
                                            >
                                                {processing
                                                    ? t('creating')
                                                    : t('create_server')}
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                asChild
                                            >
                                                <a href={serversIndex().url}>
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
