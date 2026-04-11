<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Engines\EasyEngine\EasyEngineEngine;
use App\Core\Engines\Exceptions\CommandExecutionException;
use App\Core\Provisioning\Registry\RecipeRegistry;
use App\Core\Servers\Contracts\RemoteCommandExecutorInterface;
use App\Core\Servers\Models\Server as DomainServer;
use App\Http\Requests\ServerStoreRequest;
use App\Http\Requests\ServerUpdateRequest;
use App\Models\ProvisioningRun;
use App\Models\Server;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class ServerController extends Controller
{
    public function __construct(
        private readonly EasyEngineEngine $engine,
        private readonly RemoteCommandExecutorInterface $remoteCommandExecutor,
        private readonly RecipeRegistry $recipeRegistry,
    ) {}

    /**
     * Display a listing of servers.
     */
    public function index(): Response
    {
        $servers = Server::query()
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn (Server $server) => [
                'id' => $server->id,
                'name' => $server->name,
                'ip_address' => $server->ip_address,
                'ssh_port' => $server->ssh_port,
                'ssh_execution_username' => $this->resolveExecutionUsername($server),
                'ssh_users_count' => count($this->resolveStoredSshUsers($server)),
                'provisioning_engine' => $server->provisioning_engine,
                'is_active' => $server->is_active,
                'last_connected_at' => $server->last_connected_at?->diffForHumans(),
                'created_at' => $server->created_at->diffForHumans(),
            ]);

        return Inertia::render('servers/index', [
            'servers' => $servers,
        ]);
    }

    /**
     * Show the form for creating a new server.
     */
    public function create(): Response
    {
        return Inertia::render('servers/create');
    }

    /**
     * Store a newly created server.
     */
    public function store(ServerStoreRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $sshUsers = $this->buildEncryptedSshUsers($validated['ssh_users']);
        $executionUsername = $validated['ssh_execution_username'];
        $executionUser = collect($sshUsers)->firstWhere('username', $executionUsername);

        if ($executionUser === null) {
            throw ValidationException::withMessages([
                'ssh_execution_username' => 'The selected execution SSH user was not found in the configured users.',
            ]);
        }

        // Test SSH connectivity using an in-memory domain model BEFORE persisting,
        // so we never save a server that cannot be reached.
        $candidateServer = new \App\Core\Servers\Models\Server(
            id: 0,
            name: $validated['name'],
            ipAddress: $validated['ip_address'],
            sshPort: (int) $validated['ssh_port'],
            sshUsers: $sshUsers,
            sshExecutionUsername: $executionUsername,
            provisioningEngine: null,
            isActive: true,
            createdAt: new \DateTimeImmutable,
        );

        try {
            $connected = $this->remoteCommandExecutor->testConnection($candidateServer);
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([
                'ip_address' => 'SSH connection failed: '.$e->getMessage(),
            ]);
        }

        if (! $connected) {
            throw ValidationException::withMessages([
                'ip_address' => 'Could not establish an SSH connection to this server. Please check the IP address, port, and private key.',
            ]);
        }

        $server = Server::create([
            'name' => $validated['name'],
            'ip_address' => $validated['ip_address'],
            'ssh_port' => $validated['ssh_port'],
            'ssh_users' => $sshUsers,
            'ssh_execution_username' => $executionUsername,
            'ssh_username' => $executionUsername,
            'encrypted_private_key' => $executionUser['encrypted_private_key'],
            'provisioning_engine' => ($validated['provisioning_engine'] ?? null) === 'none' ? null : ($validated['provisioning_engine'] ?? null),
            'is_active' => true,
            'last_connected_at' => now(),
        ]);

        return redirect()
            ->route('servers.show', $server)
            ->with('success', 'Server created and SSH connection verified successfully!');
    }

    /**
     * Display the specified server with site information.
     *
     * Sites are loaded from the remote VPS via SSH and then matched against the
     * local database so the UI can show which sites already exist locally and
     * which still need to be imported.
     */
    public function show(Server $server): Response
    {
        $error = null;
        $sites = [];
        $hasProvisioningEngine = is_string($server->provisioning_engine)
            && $server->provisioning_engine !== '';

        if ($hasProvisioningEngine) {
            try {
                $domainServer = DomainServer::fromEloquentModel($server);
                $result = $this->engine->runAction($domainServer, 'list_sites');
                $decoded = json_decode($result['stdout'] ?? '', true);

                if (! is_array($decoded)) {
                    throw new CommandExecutionException('EasyEngine site list did not return valid JSON.');
                }

                $remoteSites = $this->normalizeRemoteSitesPayload($decoded);
                $localSites = Site::where('server_id', $server->id)
                    ->get()
                    ->keyBy(static fn (Site $site): string => strtolower($site->domain));

                $sites = collect($remoteSites)
                    ->map(function ($item) use ($localSites): ?array {
                        $domain = $this->extractRemoteSiteDomain($item);

                        if ($domain === null) {
                            return null;
                        }

                        $localSite = $localSites->get(strtolower($domain));

                        return [
                            'id' => $localSite?->id,
                            'domain' => $domain,
                            'status' => $this->extractRemoteSiteStatus($item),
                            'imported' => $localSite !== null,
                            'local_site_id' => $localSite?->id,
                        ];
                    })
                    ->filter()
                    ->values()
                    ->all();
            } catch (CommandExecutionException $exception) {
                $error = 'Unable to load sites from the server: '.$exception->getMessage();
            } catch (\Throwable $exception) {
                $error = 'Unable to load sites from the server: '.$exception->getMessage();
            }
        }

        // Build provisioning recipes list with last run info
        $recipes = collect($this->recipeRegistry->all())->map(function ($recipe) use ($server): array {
            $lastRun = ProvisioningRun::where('server_id', $server->id)
                ->where('recipe_id', $recipe->id())
                ->latest()
                ->first();

            return [
                'id' => $recipe->id(),
                'name' => $recipe->name(),
                'description' => $recipe->description(),
                'defaultExecutionUsername' => $recipe->defaultExecutionUsername(),
                'allowsExecutionUserSelection' => $recipe->allowsExecutionUserSelection(),
                'lastRun' => $lastRun ? [
                    'id' => $lastRun->id,
                    'executionUsername' => $lastRun->execution_username,
                    'status' => $lastRun->status,
                    'startedAt' => $lastRun->started_at?->toIso8601String(),
                    'completedAt' => $lastRun->completed_at?->toIso8601String(),
                    'failureReason' => $lastRun->failure_reason,
                ] : null,
            ];
        })->values()->all();

        // Return the most recent run regardless of status so the frontend keeps
        // showing step results even after a run completes or fails.
        $activeRun = ProvisioningRun::where('server_id', $server->id)
            ->latest()
            ->first();

        return Inertia::render('servers/show', [
            'server' => [
                'id' => $server->id,
                'name' => $server->name,
                'ip_address' => $server->ip_address,
                'ssh_port' => $server->ssh_port,
                'ssh_execution_username' => $this->resolveExecutionUsername($server),
                'ssh_users' => array_map(
                    static fn (array $sshUser): array => ['username' => $sshUser['username']],
                    $this->resolveStoredSshUsers($server)
                ),
                'provisioning_engine' => $server->provisioning_engine,
                'is_active' => $server->is_active,
                'last_connected_at' => $server->last_connected_at?->diffForHumans(),
                'created_at' => $server->created_at->format('Y-m-d H:i:s'),
            ],
            'sites' => $sites,
            'error' => $error,
            'recipes' => $recipes,
            'activeRun' => $activeRun ? [
                'id' => $activeRun->id,
                'recipeId' => $activeRun->recipe_id,
                'recipeName' => $activeRun->recipe_name,
                'executionUsername' => $activeRun->execution_username,
                'status' => $activeRun->status,
                'steps' => $activeRun->steps ?? [],
                'currentStep' => $activeRun->current_step,
                'startedAt' => $activeRun->started_at?->toIso8601String(),
                'completedAt' => $activeRun->completed_at?->toIso8601String(),
                'totalDuration' => $activeRun->total_duration,
                'failureReason' => $activeRun->failure_reason,
            ] : null,
        ]);
    }

    /**
     * Normalize the EasyEngine list_sites payload into a flat list of items.
     *
     * @param  array<mixed>  $payload
     * @return array<int, mixed>
     */
    private function normalizeRemoteSitesPayload(array $payload): array
    {
        foreach (['sites', 'data', 'items'] as $key) {
            if (isset($payload[$key]) && is_array($payload[$key])) {
                $payload = $payload[$key];
                break;
            }
        }

        return array_is_list($payload) ? $payload : array_values($payload);
    }

    /**
     * Extract a domain name from a list_sites response item.
     */
    private function extractRemoteSiteDomain(mixed $item): ?string
    {
        if (is_string($item)) {
            $domain = trim($item);

            return $domain !== '' ? $domain : null;
        }

        if (! is_array($item)) {
            return null;
        }

        foreach (['domain', 'site', 'name', 'site_name'] as $key) {
            if (isset($item[$key]) && is_string($item[$key]) && trim($item[$key]) !== '') {
                return trim($item[$key]);
            }
        }

        return null;
    }

    /**
     * Extract a readable status from a list_sites response item.
     */
    private function extractRemoteSiteStatus(mixed $item): string
    {
        if (! is_array($item)) {
            return 'unknown';
        }

        foreach (['status', 'site_status', 'state'] as $key) {
            if (isset($item[$key]) && is_string($item[$key]) && trim($item[$key]) !== '') {
                return strtolower(trim($item[$key]));
            }
        }

        if (array_key_exists('site_enabled', $item)) {
            $enabled = $item['site_enabled'];

            return ($enabled === true || $enabled === 'true' || $enabled === 'enabled') ? 'enabled' : 'disabled';
        }

        return 'unknown';
    }

    /**
     * Show the form for editing the specified server.
     */
    public function edit(Server $server): Response
    {
        return Inertia::render('servers/edit', [
            'server' => [
                'id' => $server->id,
                'name' => $server->name,
                'ip_address' => $server->ip_address,
                'ssh_port' => $server->ssh_port,
                'ssh_execution_username' => $this->resolveExecutionUsername($server),
                'ssh_users' => array_map(
                    static fn (array $sshUser): array => ['username' => $sshUser['username']],
                    $this->resolveStoredSshUsers($server)
                ),
                'provisioning_engine' => $server->provisioning_engine,
                'is_active' => $server->is_active,
            ],
        ]);
    }

    /**
     * Update the specified server.
     */
    public function update(ServerUpdateRequest $request, Server $server): RedirectResponse
    {
        $validated = $request->validated();
        $sshUsers = $this->buildEncryptedSshUsers($validated['ssh_users'], $server);
        $executionUsername = $validated['ssh_execution_username'];
        $executionUser = collect($sshUsers)->firstWhere('username', $executionUsername);

        if ($executionUser === null) {
            throw ValidationException::withMessages([
                'ssh_execution_username' => 'The selected execution SSH user was not found in the configured users.',
            ]);
        }

        $data = [
            'name' => $validated['name'],
            'ip_address' => $validated['ip_address'],
            'ssh_port' => $validated['ssh_port'],
            'ssh_users' => $sshUsers,
            'ssh_execution_username' => $executionUsername,
            'ssh_username' => $executionUsername,
            'encrypted_private_key' => $executionUser['encrypted_private_key'],
            'is_active' => $validated['is_active'] ?? $server->is_active,
        ];

        $server->update($data);

        return redirect()
            ->route('servers.show', $server)
            ->with('success', 'Server updated successfully!');
    }

    /**
     * Remove the specified server.
     */
    public function destroy(Server $server): RedirectResponse
    {
        $server->delete();

        return redirect()
            ->route('servers.index')
            ->with('success', 'Server deleted successfully!');
    }

    /**
     * Test server connection.
     */
    public function testConnection(Server $server): RedirectResponse
    {
        try {
            $domainServer = DomainServer::fromEloquentModel($server);

            if (! $this->remoteCommandExecutor->testConnection($domainServer)) {
                return back()->with('error', 'Connection failed.');
            }

            $server->update(['last_connected_at' => now()]);

            return back()->with('success', 'Connection successful!');
        } catch (\Exception $e) {
            return back()->with('error', 'Connection failed: '.$e->getMessage());
        }
    }

    /**
     * Create a site on the given server using EasyEngine.
     */
    public function createSite(Request $request, Server $server): RedirectResponse
    {
        $validated = $request->validate([
            'domain' => ['required', 'string'],
        ]);

        try {
            $domainServer = DomainServer::fromEloquentModel($server);

            // Create site using engine (only domain is required)
            $this->engine->runAction($domainServer, 'create_site', [
                'domain' => $validated['domain'],
            ]);

            // Fetch site info and persist
            $infoResult = $this->engine->runAction($domainServer, 'site_info', [
                'domain' => $validated['domain'],
            ]);

            $stdout = $infoResult['stdout'] ?? '';
            $decoded = json_decode($stdout, true);

            Site::updateOrCreate([
                'server_id' => $server->id,
                'domain' => $validated['domain'],
            ], [
                'info' => is_array($decoded) ? $decoded : null,
            ]);

            return back()->with('success', 'Site created successfully!');
        } catch (CommandExecutionException $e) {
            return back()->with('error', 'Site creation failed: '.$e->getMessage());
        } catch (\Throwable $e) {
            return back()->with('error', 'Failed to create site: '.$e->getMessage());
        }
    }

    /**
     * Retrieve site info from EasyEngine and persist it locally.
     */
    public function siteInfo(Server $server, string $domain): JsonResponse
    {
        try {
            $domainServer = DomainServer::fromEloquentModel($server);

            $result = $this->engine->runAction($domainServer, 'site_info', [
                'domain' => $domain,
            ]);

            $stdout = $result['stdout'] ?? '';
            $decoded = json_decode($stdout, true);

            $site = Site::updateOrCreate([
                'server_id' => $server->id,
                'domain' => $domain,
            ], [
                'info' => is_array($decoded) ? $decoded : null,
            ]);

            return response()->json(['site' => $site->toArray()]);
        } catch (CommandExecutionException $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Import a site that exists on the server but has no local DB record.
     *
     * Fetches site_info from EasyEngine and creates/updates the local Site record,
     * then redirects back to the server show page so the user can manage the site.
     */
    public function importSite(Server $server, string $domain): RedirectResponse
    {
        $domain = urldecode($domain);

        try {
            $domainServer = DomainServer::fromEloquentModel($server);

            $infoResult = $this->engine->runAction($domainServer, 'site_info', [
                'domain' => $domain,
            ]);

            $decoded = json_decode($infoResult['stdout'] ?? '', true);

            Site::updateOrCreate(
                ['server_id' => $server->id, 'domain' => $domain],
                ['info' => is_array($decoded) ? $decoded : null],
            );
        } catch (CommandExecutionException $e) {
            return back()->with('error', "Could not import site '{$domain}': ".$e->getMessage());
        } catch (\Throwable $e) {
            return back()->with('error', "Unexpected error importing site '{$domain}': ".$e->getMessage());
        }

        $site = Site::where('server_id', $server->id)->where('domain', $domain)->firstOrFail();

        return redirect()->route('sites.show', $site)->with('success', "Site '{$domain}' imported successfully.");
    }

    /**
     * Delete a site on the given server and remove local record.
     */
    public function destroySite(Request $request, Server $server, string $domain): RedirectResponse
    {
        try {
            $domainServer = DomainServer::fromEloquentModel($server);

            $this->engine->runAction($domainServer, 'delete_site', [
                'domain' => $domain,
                'remove_files' => $request->boolean('remove_files', true),
            ]);
        } catch (CommandExecutionException $e) {
            Log::channel('engines')->error('Remote site deletion failed (ServerController)', [
                'server_id' => $server->id,
                'domain' => $domain,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'Remote site deletion failed: '.$e->getMessage());
        } catch (\Throwable $e) {
            Log::channel('engines')->error('Remote site deletion failed (ServerController)', [
                'server_id' => $server->id,
                'domain' => $domain,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'Remote site deletion failed: '.$e->getMessage());
        }

        // Remote deletion succeeded — remove local record
        Site::where('server_id', $server->id)
            ->where('domain', $domain)
            ->delete();

        return back()->with('success', 'Site deleted successfully');
    }

    /**
     * @param  array<int, array{username: string, private_key?: string|null}>  $sshUsers
     * @return array<int, array{username: string, encrypted_private_key: string}>
     */
    private function buildEncryptedSshUsers(array $sshUsers, ?Server $existingServer = null): array
    {
        $existingUsersByUsername = $this->resolveStoredSshUsersCollection($existingServer)
            ->keyBy('username');

        return collect($sshUsers)
            ->values()
            ->map(function (array $sshUser, int $index) use ($existingUsersByUsername): array {
                $username = $sshUser['username'];
                $privateKey = $sshUser['private_key'] ?? null;

                if (is_string($privateKey) && $privateKey !== '') {
                    return [
                        'username' => $username,
                        'encrypted_private_key' => Crypt::encrypt($privateKey),
                    ];
                }

                $existingUser = $existingUsersByUsername->get($username);

                if (is_array($existingUser) && ! empty($existingUser['encrypted_private_key'])) {
                    return [
                        'username' => $username,
                        'encrypted_private_key' => $existingUser['encrypted_private_key'],
                    ];
                }

                throw ValidationException::withMessages([
                    "ssh_users.{$index}.private_key" => 'Please provide a private key for new SSH users.',
                ]);
            })
            ->all();
    }

    /**
     * @return array<int, array{username: string, encrypted_private_key: string}>
     */
    private function resolveStoredSshUsers(Server $server): array
    {
        return $this->resolveStoredSshUsersCollection($server)->all();
    }

    /**
     * @return Collection<int, array{username: string, encrypted_private_key: string}>
     */
    private function resolveStoredSshUsersCollection(?Server $server): Collection
    {
        if ($server === null) {
            return collect();
        }

        $sshUsers = is_array($server->ssh_users) ? $server->ssh_users : [];

        $normalizedUsers = collect($sshUsers)
            ->map(static function (mixed $sshUser): ?array {
                if (! is_array($sshUser)) {
                    return null;
                }

                $username = $sshUser['username'] ?? null;
                $encryptedPrivateKey = $sshUser['encrypted_private_key'] ?? null;

                if (! is_string($username) || $username === '') {
                    return null;
                }

                if (! is_string($encryptedPrivateKey) || $encryptedPrivateKey === '') {
                    return null;
                }

                return [
                    'username' => $username,
                    'encrypted_private_key' => $encryptedPrivateKey,
                ];
            })
            ->filter()
            ->values();

        if ($normalizedUsers->isNotEmpty()) {
            return $normalizedUsers;
        }

        if (is_string($server->ssh_username) && $server->ssh_username !== '' && is_string($server->encrypted_private_key) && $server->encrypted_private_key !== '') {
            return collect([[
                'username' => $server->ssh_username,
                'encrypted_private_key' => $server->encrypted_private_key,
            ]]);
        }

        return collect();
    }

    private function resolveExecutionUsername(Server $server): string
    {
        if (is_string($server->ssh_execution_username) && $server->ssh_execution_username !== '') {
            return $server->ssh_execution_username;
        }

        $sshUsers = $this->resolveStoredSshUsers($server);

        return $sshUsers[0]['username'] ?? 'root';
    }
}
