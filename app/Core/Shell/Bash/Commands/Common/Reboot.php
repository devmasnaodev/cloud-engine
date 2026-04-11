<?php

declare(strict_types=1);

namespace App\Core\Shell\Bash\Commands\Common;

use App\Core\Commands\AbstractCommand;

final class Reboot extends AbstractCommand
{
    public function id(): string
    {
        return 'reboot';
    }

    public function name(): string
    {
        return 'Reboot Server';
    }

    public function description(): string
    {
        return 'Reboot the server to apply changes';
    }

    public function command(): string
    {
        return 'reboot now';
    }
}
