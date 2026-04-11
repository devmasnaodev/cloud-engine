<?php

declare(strict_types=1);

namespace App\Core\Console;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class SpinnerService
{
    public function start(InputInterface $input): int
    {
        if (! $input->isInteractive()) {
            return 0;
        }

        $phpCode = "while(true){echo '.'; fflush(STDOUT); usleep(200000);}";
        $cmd = 'php -r '.escapeshellarg($phpCode).' > /dev/tty 2>/dev/tty & echo $!';

        $pid = (int) trim(shell_exec($cmd) ?: '0');

        return $pid;
    }

    public function stop(int $pid, ?OutputInterface $output = null): void
    {
        if ($pid <= 0) {
            return;
        }

        @exec(sprintf('kill %d >/dev/null 2>&1 || true', $pid));

        if ($output) {
            $output->writeln('');
        }
    }
}
