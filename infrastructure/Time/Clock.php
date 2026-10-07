<?php

declare(strict_types=1);

namespace InterviewTask\Infrastructure\Time;

use DateTimeImmutable;

interface Clock
{
    public function now(): DateTimeImmutable;
}
