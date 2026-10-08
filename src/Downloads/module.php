<?php

declare(strict_types=1);

/**
 * Authoritative machine-readable public surface of the fixed-artifact download behavior.
 *
 * No client-selected filesystem paths or executable names enter the owner contract.
 *
 * @package InterviewTask\Downloads
 */

use InterviewTask\Downloads\ArtifactUnavailable;
use InterviewTask\Downloads\DownloadFile;

return ['public' => [ArtifactUnavailable::class, DownloadFile::class]];
