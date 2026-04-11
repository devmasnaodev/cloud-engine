<?php

declare(strict_types=1);

use App\Core\Servers\Execution\RemoteCommandFormatter;
use App\Core\Servers\Execution\RemoteCommandOptions;

it('formats command with cwd env and secret placeholder', function () {
    $formatter = new RemoteCommandFormatter;
    $options = new RemoteCommandOptions(
        cwd: '/var/www/site',
        env: ['APP_ENV' => 'production', 'FOO' => 'bar baz'],
        secret: 'super-token',
    );

    $formatted = $formatter->format('echo %secret% && pwd', $options);

    expect($formatted)
        ->toContain("cd '/var/www/site' &&")
        ->toContain("APP_ENV='production'")
        ->toContain("FOO='bar baz'")
        ->toContain('echo super-token && pwd');
});

it('sanitizes secret placeholder for logs', function () {
    $formatter = new RemoteCommandFormatter;

    expect($formatter->sanitizeForLogs('curl -H "Authorization: Bearer %secret%"'))
        ->toBe('curl -H "Authorization: Bearer ***"');
});
