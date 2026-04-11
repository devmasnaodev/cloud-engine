<?php

declare(strict_types=1);

namespace App\Providers;

use App\Core\Application\UseCases\Sites\CreateSiteUseCase;
use App\Core\Application\UseCases\Sites\SiteActionUseCase;
use App\Core\Application\UseCases\Sites\UpdateSiteUseCase;
use App\Core\Engines\EasyEngine\EasyEngineCommandBuilder;
use App\Core\Engines\EasyEngine\EasyEngineEngine;
use App\Core\Engines\Executor\CommandExecutor;
use App\Core\Engines\Executor\CommandNormalizer;
use App\Core\Engines\Registry\EngineRegistry;
use App\Core\Provisioning\Contracts\RecipeRunnerInterface;
use App\Core\Provisioning\Recipes\Engine\InstallEasyEngineRecipe;
use App\Core\Provisioning\Recipes\Setup\InitialServerSetupRecipe;
use App\Core\Provisioning\Recipes\User\CreateNonRootUserRecipe;
use App\Core\Provisioning\Registry\RecipeRegistry;
use App\Core\Provisioning\Runner\RecipeRunner;
use App\Core\Security\Credentials\CredentialStore;
use App\Core\Security\Credentials\SecretEncryptionService;
use App\Core\Servers\Contracts\RemoteCommandExecutorInterface;
use App\Core\Servers\Contracts\ServerConnectionServiceInterface;
use App\Core\Servers\Execution\RemoteCommandFormatter;
use App\Core\Servers\Services\RemoteCommandExecutor;
use App\Core\Servers\Services\ServerConnectionService;
use App\Core\Servers\Services\ServerInfoDetector;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\ServiceProvider;
use Psr\Log\LoggerInterface;

/**
 * Service provider for Cloud Engine core services.
 *
 * Registers all dependency injection bindings for the Core architecture.
 */
final class CoreServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Security
        $this->app->singleton(SecretEncryptionService::class, function ($app) {
            return new SecretEncryptionService($app->make(Encrypter::class));
        });

        $this->app->singleton(CredentialStore::class, function ($app) {
            return new CredentialStore($app->make(SecretEncryptionService::class));
        });

        // Servers
        $this->app->singleton(ServerConnectionService::class, function ($app) {
            return new ServerConnectionService(
                $app->make(SecretEncryptionService::class),
                $app->make(LoggerInterface::class),
                // Provide an EngineValidatorResolver with known validators
                new \App\Core\Engines\Validators\EngineValidatorResolver([
                    'easyengine' => $app->make(\App\Core\Engines\Validators\EasyEngineValidator::class),
                ])
            );
        });

        // Bind validators so the resolver can construct them
        $this->app->singleton(\App\Core\Engines\Validators\EasyEngineValidator::class, function ($app) {
            return new \App\Core\Engines\Validators\EasyEngineValidator;
        });

        $this->app->singleton(ServerConnectionServiceInterface::class, ServerConnectionService::class);

        $this->app->singleton(RemoteCommandExecutor::class, function ($app) {
            return new RemoteCommandExecutor(
                $app->make(ServerConnectionService::class),
                $app->make(RemoteCommandFormatter::class),
                $app->make(LoggerInterface::class)
            );
        });

        $this->app->singleton(RemoteCommandExecutorInterface::class, RemoteCommandExecutor::class);

        // Provisioning - Executor
        $this->app->singleton(CommandNormalizer::class);

        $this->app->singleton(CommandExecutor::class, function ($app) {
            return new CommandExecutor(
                $app->make(CommandNormalizer::class),
                $app->make(LoggerInterface::class)
            );
        });

        // Provisioning - EasyEngine
        $this->app->singleton(EasyEngineCommandBuilder::class);

        $this->app->singleton(EasyEngineEngine::class, function ($app) {
            return new EasyEngineEngine(
                $app->make(RemoteCommandExecutorInterface::class),
                $app->make(EasyEngineCommandBuilder::class)
            );
        });

        // Engine Registry — maps provisioning_engine strings to EngineInterface instances
        $this->app->singleton(EngineRegistry::class, function ($app) {
            $registry = new EngineRegistry;
            $registry->register('easyengine', $app->make(EasyEngineEngine::class));

            return $registry;
        });

        // Application - Use Cases (Sites)
        $this->app->singleton(CreateSiteUseCase::class, function ($app) {
            return new CreateSiteUseCase(
                $app->make(EngineRegistry::class),
                $app->make(CommandNormalizer::class),
            );
        });

        $this->app->singleton(UpdateSiteUseCase::class, function ($app) {
            return new UpdateSiteUseCase(
                $app->make(EngineRegistry::class),
                $app->make(CommandNormalizer::class),
            );
        });

        $this->app->singleton(SiteActionUseCase::class, function ($app) {
            return new SiteActionUseCase(
                $app->make(EngineRegistry::class),
                $app->make(CommandNormalizer::class),
            );
        });

        // Provisioning - Recipe Registry
        $this->app->singleton(RecipeRegistry::class, function ($app) {
            $registry = new RecipeRegistry;
            $registry->register(new InitialServerSetupRecipe);
            $registry->register(new CreateNonRootUserRecipe);
            $registry->register(new InstallEasyEngineRecipe);

            return $registry;
        });

        // Provisioning - Recipe Runner
        $this->app->singleton(RecipeRunner::class, function ($app) {
            return new RecipeRunner(
                $app->make(RemoteCommandExecutorInterface::class),
                $app->make(Dispatcher::class),
            );
        });

        $this->app->singleton(RecipeRunnerInterface::class, RecipeRunner::class);

        // Servers - Info Detector
        $this->app->singleton(ServerInfoDetector::class, function ($app) {
            return new ServerInfoDetector($app->make(RemoteCommandExecutorInterface::class));
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
