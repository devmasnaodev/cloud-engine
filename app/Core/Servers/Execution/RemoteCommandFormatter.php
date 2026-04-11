<?php

declare(strict_types=1);

namespace App\Core\Servers\Execution;

final class RemoteCommandFormatter
{
    public function format(string $command, RemoteCommandOptions $options): string
    {
        $preparedCommand = $command;

        if ($options->secret !== null) {
            $preparedCommand = str_replace('%secret%', $options->secret, $preparedCommand);
        }

        if ($options->cwd !== null && $options->cwd !== '') {
            $preparedCommand = sprintf('cd %s && %s', escapeshellarg($options->cwd), $preparedCommand);
        }

        if ($options->env !== []) {
            $preparedCommand = sprintf('export %s; %s', $this->stringifyEnv($options->env), $preparedCommand);
        }

        return $preparedCommand;
    }

    public function sanitizeForLogs(string $command): string
    {
        return str_replace('%secret%', '***', $command);
    }

    /**
     * @param  array<string, string>  $env
     */
    private function stringifyEnv(array $env): string
    {
        $pairs = [];

        foreach ($env as $key => $value) {
            $pairs[] = sprintf('%s=%s', $key, escapeshellarg($value));
        }

        return implode(' ', $pairs);
    }
}
