<?php

declare(strict_types=1);

namespace InterviewTask\Downloads;

use RuntimeException;

/**
 * Expected failure when the configured download artifact cannot be read.
 *
 * @api
 * @package InterviewTask\Downloads
 */
final class ArtifactUnavailable extends RuntimeException {}
