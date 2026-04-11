<?php

declare(strict_types=1);

namespace App\Core\Provisioning\Result;

/**
 * Immutable result of a single recipe step execution.
 *
 * Carries stdout/stderr, exit status, duration and any validation
 * message returned by CommandInterface::validateResult().
 */
final class StepResult
{
    public function __construct(
        public readonly string $stepId,
        public readonly string $stepName,
        public readonly string $description,
        public readonly string $stdout,
        public readonly string $stderr,
        public readonly int $exitStatus,
        public readonly float $duration,
        public readonly bool $successful,
        public readonly ?string $validationError = null,
    ) {}

    public function isSuccessful(): bool
    {
        return $this->successful;
    }

    public function hasFailed(): bool
    {
        return ! $this->successful;
    }

    /**
     * Whether validation produced a SKIP prefixed message.
     */
    public function isSkipped(): bool
    {
        return $this->validationError !== null
            && str_starts_with($this->validationError, 'SKIP:');
    }

    /**
     * Whether validation produced a WARN prefixed message.
     */
    public function hasWarning(): bool
    {
        return $this->validationError !== null
            && str_starts_with($this->validationError, 'WARN:');
    }
}
