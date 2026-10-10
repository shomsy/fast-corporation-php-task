<?php

declare(strict_types=1);

namespace InterviewTask\Infrastructure\Diagnostics;

use Closure;
use Throwable;

/**
 * Report secondary cleanup errors without replacing an already observed failure.
 *
 * @package InterviewTask\Infrastructure\Diagnostics
 */
final readonly class CleanupFailures
{
    /**
     * Select the diagnostic destination at composition, independently of resource cleanup.
     *
     * @param Closure(Throwable): void $report Trusted diagnostic sink; must not expose secrets.
     */
    public function __construct(private Closure $report) {}

    /**
     * Attempt one diagnostic; a broken sink must not mask the primary application failure.
     *
     * @param Throwable $error Secondary cleanup failure, never its message or arguments.
     *
     * @return void No return value.
     */
    public function record(Throwable $error) : void
    {
        try {
            ($this->report)($error);
        } catch (Throwable) {
            // Best effort only: the caller still propagates the primary failure.
        }
    }
}
