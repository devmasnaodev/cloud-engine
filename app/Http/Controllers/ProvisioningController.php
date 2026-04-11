<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Provisioning\Contracts\ProvisioningRecipeInterface;
use App\Core\Provisioning\Registry\RecipeRegistry;
use App\Core\Servers\Models\Server as DomainServer;
use App\Jobs\RunProvisioningRecipeJob;
use App\Models\ProvisioningRun;
use App\Models\Server;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class ProvisioningController extends Controller
{
    public function __construct(
        private readonly RecipeRegistry $registry,
    ) {}

    /**
     * Provisioning run history page for a server.
     * Returns the last 50 runs with all step details so the user can audit
     * every command that has been executed on the server.
     */
    public function index(Server $server): Response
    {
        $runs = ProvisioningRun::where('server_id', $server->id)
            ->latest()
            ->limit(50)
            ->get()
            ->map(static fn (ProvisioningRun $run): array => [
                'id' => $run->id,
                'recipeId' => $run->recipe_id,
                'recipeName' => $run->recipe_name,
                'executionUsername' => $run->execution_username,
                'status' => $run->status,
                'steps' => $run->steps ?? [],
                'failureReason' => $run->failure_reason,
                'totalDuration' => $run->total_duration,
                'startedAt' => $run->started_at?->toIso8601String(),
                'completedAt' => $run->completed_at?->toIso8601String(),
                'createdAt' => $run->created_at->toIso8601String(),
            ])
            ->values()
            ->all();

        return Inertia::render('servers/provisioning-history', [
            'server' => [
                'id' => $server->id,
                'name' => $server->name,
            ],
            'runs' => $runs,
        ]);
    }

    /**
     * Dispatch a provisioning recipe run for the given server.
     * Returns a redirect back so it works from any page (Inertia or web).
     */
    public function run(Request $request, Server $server): RedirectResponse
    {
        $validated = $request->validate([
            'recipe_id' => ['required', 'string'],
            'execution_username' => ['nullable', 'string'],
        ]);

        $recipe = $this->registry->find($validated['recipe_id']);

        if ($recipe === null) {
            return back()->with('error', "Unknown recipe: {$validated['recipe_id']}");
        }

        // Block concurrent runs
        $activeRun = ProvisioningRun::where('server_id', $server->id)
            ->whereIn('status', ['pending', 'running'])
            ->first();

        if ($activeRun !== null) {
            return back()->with('error', "A provisioning run is already in progress: {$activeRun->recipe_name}.");
        }

        $effectiveExecutionUsername = $this->resolveExecutionUsername(
            $recipe,
            $server->toDomainModel(),
            $validated['execution_username'] ?? null,
        );

        $run = ProvisioningRun::create([
            'server_id' => $server->id,
            'recipe_id' => $recipe->id(),
            'recipe_name' => $recipe->name(),
            'execution_username' => $effectiveExecutionUsername,
            'status' => 'pending',
        ]);

        RunProvisioningRecipeJob::dispatch($server->id, $recipe->id(), $run->id);

        return back()->with('success', "Recipe \"{$recipe->name()}\" queued successfully.");
    }

    /**
     * Return the current state of a provisioning run as JSON.
     * Used by the frontend to poll for progress every 2 seconds.
     * Keys use camelCase to match the TypeScript ActiveRun interface.
     *
     * Includes `currentStep` so the frontend can show which command is
     * currently executing along with any partial stdout captured so far.
     */
    public function status(Server $server, ProvisioningRun $run): JsonResponse
    {
        abort_if($run->server_id !== $server->id, 404);

        return response()->json([
            'id' => $run->id,
            'recipeId' => $run->recipe_id,
            'recipeName' => $run->recipe_name,
            'executionUsername' => $run->execution_username,
            'status' => $run->status,
            'steps' => $run->steps ?? [],
            'currentStep' => $run->current_step,
            'failureReason' => $run->failure_reason,
            'totalDuration' => $run->total_duration,
            'startedAt' => $run->started_at?->toIso8601String(),
            'completedAt' => $run->completed_at?->toIso8601String(),
        ]);
    }

    private function resolveExecutionUsername(
        ProvisioningRecipeInterface $recipe,
        DomainServer $server,
        ?string $requestedExecutionUsername,
    ): string {
        $requestedExecutionUsername = $requestedExecutionUsername !== null
            ? trim($requestedExecutionUsername)
            : null;

        if ($requestedExecutionUsername === '') {
            $requestedExecutionUsername = null;
        }

        if ($recipe->allowsExecutionUserSelection()) {
            $effectiveExecutionUsername = $requestedExecutionUsername ?? $recipe->defaultExecutionUsername();
        } else {
            if ($requestedExecutionUsername !== null) {
                throw ValidationException::withMessages([
                    'execution_username' => sprintf(
                        'Recipe "%s" must always run as %s.',
                        $recipe->name(),
                        $recipe->defaultExecutionUsername(),
                    ),
                ]);
            }

            $effectiveExecutionUsername = $recipe->defaultExecutionUsername();
        }

        if (! $server->hasSshUser($effectiveExecutionUsername)) {
            throw ValidationException::withMessages([
                'execution_username' => sprintf(
                    'SSH user "%s" is not configured for this server.',
                    $effectiveExecutionUsername,
                ),
            ]);
        }

        return $effectiveExecutionUsername;
    }
}
