<?php

declare(strict_types=1);

namespace Tests\Feature\Core\Provisioning;

use App\Core\Engines\EasyEngine\EasyEngineCommandBuilder;
use App\Core\Engines\EasyEngine\EasyEngineEngine;
use App\Core\Engines\Exceptions\CommandExecutionException;
use App\Core\Engines\Executor\CommandExecutor;
use App\Core\Engines\Executor\CommandNormalizer;

uses()->group('provisioning', 'easyengine');

it('can execute a simple command via executor', function () {
    $engine = app(EasyEngineEngine::class);
    $executor = app(CommandExecutor::class);

    expect($executor)->toBeInstanceOf(CommandExecutor::class)
        ->and($engine)->toBeInstanceOf(EasyEngineEngine::class);
})->skip('Requires actual SSH server');

it('can normalize domain names', function () {
    $normalizer = app(CommandNormalizer::class);

    expect($normalizer->normalizeDomain('example.com'))->toBe('example.com')
        ->and($normalizer->normalizeDomain('https://example.com'))->toBe('example.com')
        ->and($normalizer->normalizeDomain('http://www.example.com/'))->toBe('example.com')
        ->and($normalizer->normalizeDomain('WWW.EXAMPLE.COM'))->toBe('example.com');
});

it('validates invalid domain names', function () {
    $normalizer = app(CommandNormalizer::class);

    $normalizer->normalizeDomain('invalid domain with spaces');
})->throws(CommandExecutionException::class);

it('can normalize PHP versions', function () {
    $normalizer = app(CommandNormalizer::class);

    expect($normalizer->normalizePhpVersion('8.3'))->toBe('8.3')
        ->and($normalizer->normalizePhpVersion('8.2'))->toBe('8.2')
        ->and($normalizer->normalizePhpVersion('8.1'))->toBe('8.1');
});

it('rejects invalid PHP versions', function () {
    $normalizer = app(CommandNormalizer::class);

    $normalizer->normalizePhpVersion('9.0');
})->throws(CommandExecutionException::class);

it('can build EasyEngine commands', function () {
    $builder = app(EasyEngineCommandBuilder::class);

    $listCommand = $builder->buildListSites();
    expect($listCommand)->toBe("bash -l -c 'sudo ee site list --format=json'");

    $createCommand = $builder->buildCreateSite('example.com', [
        'type' => 'wordpress',
        'php_version' => '8.3',
        'ssl' => 'le',
    ]);

    expect($createCommand)->toContain('script -q -c')
        ->and($createCommand)->toContain('ee site create')
        ->and($createCommand)->toContain("'example.com'")
        ->and($createCommand)->toContain('8.3')
        ->and($createCommand)->toContain('le')
        ->and($createCommand)->toContain('--yes');
});
