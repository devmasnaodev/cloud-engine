<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Application\DTOs\Sites\CreateSiteInput;
use App\Core\Application\DTOs\Sites\SiteActionInput;
use App\Core\Application\DTOs\Sites\UpdateSiteInput;
use App\Core\Application\UseCases\Sites\CreateSiteUseCase;
use App\Core\Application\UseCases\Sites\SiteActionUseCase;
use App\Core\Application\UseCases\Sites\UpdateSiteUseCase;
use App\Http\Requests\Sites\SiteUpdateRequest;
use App\Http\Requests\SiteStoreRequest;
use App\Models\Server;
use App\Models\Site;
use App\Models\SiteCommandRun;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class SiteController extends Controller
{
    public function __construct(
        private readonly CreateSiteUseCase $createSite,
        private readonly UpdateSiteUseCase $updateSite,
        private readonly SiteActionUseCase $siteAction,
    ) {}

    public function index(): Response
    {
        $sites = Site::with('server')->get()->map(fn (Site $site) => [
            'id' => $site->id,
            'domain' => $site->domain,
            'info' => $site->info,
            'server' => $site->server ? [
                'id' => $site->server->id,
                'name' => $site->server->name,
                'ip_address' => $site->server->ip_address,
            ] : null,
            'created_at' => $site->created_at->diffForHumans(),
        ]);

        $servers = Server::query()->get()->map(fn (Server $server) => [
            'id' => $server->id,
            'name' => $server->name,
            'ip_address' => $server->ip_address,
        ]);

        return Inertia::render('sites/index', ['sites' => $sites, 'servers' => $servers]);
    }

    /**
     * Show the dedicated site creation form.
     * Accepts an optional `server_id` query parameter to pre-select a server.
     */
    public function create(Request $request): Response
    {
        $servers = Server::query()->get()->map(fn (Server $server) => [
            'id' => $server->id,
            'name' => $server->name,
            'ip_address' => $server->ip_address,
            'provisioning_engine' => $server->provisioning_engine,
        ]);

        return Inertia::render('sites/create', [
            'servers' => $servers,
            'defaultServerId' => $request->integer('server_id') ?: null,
        ]);
    }

    /**
     * Create a new site with full options via the provisioning engine.
     */
    public function store(SiteStoreRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $run = $this->createSite->execute(new CreateSiteInput(
            serverId: (int) $data['server_id'],
            domain: $data['domain'],
            options: $request->toEngineOptions(),
        ));

        $site = Site::findOrFail($run->site_id);

        return redirect()->route('sites.show', $site)->with('commandRunId', $run->id);
    }

    public function show(Site $site): Response
    {
        $site->load('server');

        $siteCommandRuns = SiteCommandRun::where('site_id', $site->id)
            ->orderByDesc('created_at')
            ->limit(15)
            ->get()
            ->map(fn (SiteCommandRun $r) => [
                'id' => $r->id,
                'action' => $r->action,
                'domain' => $r->domain,
                'status' => $r->status,
                'stdout' => $r->stdout,
                'stderr' => $r->stderr,
                'partialStdout' => $r->partial_stdout,
                'exitStatus' => $r->exit_status,
                'duration' => $r->duration,
                'startedAt' => $r->started_at?->toISOString(),
                'completedAt' => $r->completed_at?->toISOString(),
            ]);

        return Inertia::render('sites/show', [
            'site' => [
                'id' => $site->id,
                'domain' => $site->domain,
                'info' => $site->info,
                'created_at' => $site->created_at->diffForHumans(),
            ],
            'server' => $site->server ? [
                'id' => $site->server->id,
                'name' => $site->server->name,
                'ip_address' => $site->server->ip_address,
                'provisioning_engine' => $site->server->provisioning_engine,
            ] : null,
            'siteCommandRuns' => $siteCommandRuns,
        ]);
    }

    /**
     * Show the full raw site info from the provisioning engine.
     */
    public function siteInfo(Site $site): Response
    {
        $site->load('server');

        return Inertia::render('sites/info', [
            'site' => [
                'id' => $site->id,
                'domain' => $site->domain,
                'info' => $site->info,
            ],
            'server' => $site->server ? [
                'id' => $site->server->id,
                'name' => $site->server->name,
                'ip_address' => $site->server->ip_address,
                'provisioning_engine' => $site->server->provisioning_engine,
            ] : null,
        ]);
    }

    /**
     * Show the edit form for the site.
     */
    public function edit(Site $site): Response
    {
        $site->load('server');

        return Inertia::render('sites/edit', [
            'site' => [
                'id' => $site->id,
                'domain' => $site->domain,
                'info' => $site->info,
            ],
            'server' => $site->server ? [
                'id' => $site->server->id,
                'name' => $site->server->name,
                'provisioning_engine' => $site->server->provisioning_engine,
            ] : null,
            'phpVersions' => ['5.6', '7.0', '7.2', '7.3', '7.4', '8.0', '8.1', '8.2', '8.3', '8.4', '8.5'],
        ]);
    }

    /**
     * Apply configuration changes to the site via the provisioning engine (async).
     */
    public function update(SiteUpdateRequest $request, Site $site): RedirectResponse
    {
        $server = $site->server;

        if (! $server) {
            return back()->with('error', 'No server associated with this site.');
        }

        $options = $request->toEngineOptions();

        if (empty($options)) {
            return back()->with('error', 'No changes were specified.');
        }

        $run = $this->updateSite->execute(new UpdateSiteInput(
            serverId: $server->id,
            siteId: $site->id,
            domain: $site->domain,
            options: $options,
        ));

        return redirect()->route('sites.show', $site)->with('commandRunId', $run->id);
    }

    /**
     * Enable the site via the provisioning engine (async).
     */
    public function enable(Site $site): RedirectResponse
    {
        $server = $site->server;

        if (! $server) {
            return back()->with('error', 'No server associated with this site.');
        }

        $run = $this->siteAction->execute(new SiteActionInput(
            serverId: $server->id,
            siteId: $site->id,
            domain: $site->domain,
            action: 'enable_site',
        ));

        return back()->with('commandRunId', $run->id);
    }

    /**
     * Disable the site via the provisioning engine (async).
     */
    public function disable(Site $site): RedirectResponse
    {
        $server = $site->server;

        if (! $server) {
            return back()->with('error', 'No server associated with this site.');
        }

        $run = $this->siteAction->execute(new SiteActionInput(
            serverId: $server->id,
            siteId: $site->id,
            domain: $site->domain,
            action: 'disable_site',
        ));

        return back()->with('commandRunId', $run->id);
    }

    /**
     * Clean the site cache via the provisioning engine (async).
     */
    public function clean(Site $site): RedirectResponse
    {
        $server = $site->server;

        if (! $server) {
            return back()->with('error', 'No server associated with this site.');
        }

        $run = $this->siteAction->execute(new SiteActionInput(
            serverId: $server->id,
            siteId: $site->id,
            domain: $site->domain,
            action: 'clean_site',
        ));

        return back()->with('commandRunId', $run->id);
    }

    public function destroy(Request $request, Site $site): RedirectResponse
    {
        $server = $site->server;

        if (! $server) {
            $site->delete();

            return redirect()->route('sites.index')->with('success', 'Site deleted.');
        }

        $run = $this->siteAction->execute(new SiteActionInput(
            serverId: $server->id,
            siteId: $site->id,
            domain: $site->domain,
            action: 'delete_site',
        ));

        return redirect()->route('sites.show', $site)->with('commandRunId', $run->id);
    }

    /**
     * Return current status of a site command run for frontend polling.
     */
    public function commandRunStatus(SiteCommandRun $run): JsonResponse
    {
        return response()->json([
            'id' => $run->id,
            'action' => $run->action,
            'domain' => $run->domain,
            'status' => $run->status,
            'partial_stdout' => $run->partial_stdout,
            'stdout' => $run->stdout,
            'stderr' => $run->stderr,
            'exit_status' => $run->exit_status,
            'duration' => $run->duration,
            'started_at' => $run->started_at?->toISOString(),
            'completed_at' => $run->completed_at?->toISOString(),
        ]);
    }
}
