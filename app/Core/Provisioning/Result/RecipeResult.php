<?php

declare(strict_types=1);

namespace App\Core\Provisioning\Result;

/**
 * Immutable aggregate result of a complete recipe execution.
 *
 * Collects all StepResults and exposes high-level success/failure state
 * along with the total duration and an optional failure reason.
 */
final class RecipeResult
{
    /**
     * @param  StepResult[]  $stepResults
     */
    public function __construct(
        public readonly bool $successful,
        public readonly array $stepResults,
        public readonly float $totalDuration,
        public readonly ?string $failureReason = null,
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
     * Number of steps that completed with exit status 0 and passed validation.
     */
    public function completedSteps(): int
    {
        return count(array_filter(
            $this->stepResults,
            static fn (StepResult $r) => $r->isSuccessful(),
        ));
    }

    /**
     * Total steps in the recipe (including skipped/failed).
     */
    public function totalSteps(): int
    {
        return count($this->stepResults);
    }
}
