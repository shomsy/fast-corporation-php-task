<?php

declare(strict_types=1);

namespace InterviewTask\Downloads;

use InterviewTask\ActivityTracking\ActivityLog;
use InterviewTask\ActivityTracking\TrackedButton;
use InterviewTask\ActivityTracking\TrackedPage;
use PDOException;

/**
 * Only a server-configured artifact can be downloaded. Never accepts a client-supplied filename or path.
 *
 * @api
 * @package InterviewTask\Downloads
 */
final readonly class DownloadFile
{
    /**
     * Bind the audit log to a fixed server-owned artifact path.
     *
     * @param ActivityLog $activityLog Activity-event writer shared with the feature.
     * @param string $artifactPath Fixed server-side download artifact path, never taken from the request.
     * @param string|null $expectedSha256 Pinned SHA-256 of the deployed binary (required by the production composition root).
     * @param int $maxBytes Maximum allowed file bytes, protecting PHP memory.
     * @throws \InvalidArgumentException If the trusted file policy is malformed.
     */
    public function __construct(
        private ActivityLog $activityLog,
        private string      $artifactPath,
        private string|null     $expectedSha256 = null,
        private int         $maxBytes = 2097152,
    ) {
        if ($maxBytes < 1 || ($expectedSha256 !== null && preg_match(pattern: '/\\A[a-f0-9]{64}\\z/D', subject: $expectedSha256) !== 1)) {
            throw new \InvalidArgumentException(message: 'Invalid trusted download artifact policy.');
        }
    }

    /**
     * Record the authorized display of Page B.
     *
     * @param int $actorId Stable numeric identity associated with the audit event.
     * @param string $actorLabel Historical username snapshot written alongside the event.
     * @return void No return value.
     */
    public function view(int $actorId, string $actorLabel): void
    {
        $this->activityLog->recordPageView(actorId: $actorId, actorLabel: $actorLabel, page: TrackedPage::PageB);
    }

    /**
     * Prepare a fixed server-owned artifact and record the accepted download click after reading it. No activity event is written if the artifact cannot be read.
     *
     * @param int $actorId Stable numeric identity associated with the audit event.
     * @param string $actorLabel Historical username snapshot written alongside the event.
     * @return string Complete contents of the fixed readable artifact.
     * @throws ArtifactUnavailable When the configured artifact cannot be read completely.
     * @throws PDOException When the underlying SQLite operation fails.
     */
    public function prepareDownload(int $actorId, string $actorLabel): string
    {
        if (is_link(filename: $this->artifactPath) || ! is_file(filename: $this->artifactPath)
            || ! is_readable(filename: $this->artifactPath)) {
            throw new ArtifactUnavailable(message: 'Download is temporarily unavailable.');
        }

        clearstatcache(clear_realpath_cache: true, filename: $this->artifactPath);
        $size = filesize(filename: $this->artifactPath);

        if (! is_int(value: $size) || $size < 1 || $size > $this->maxBytes) {
            throw new ArtifactUnavailable(message: 'Download is temporarily unavailable.');
        }

        $stream = fopen(filename: $this->artifactPath, mode: 'rb');

        if ($stream === false) {
            throw new ArtifactUnavailable(message: 'Download is temporarily unavailable.');
        }

        try {
            // A bounded read detects a file replaced/grown after its initial size check.
            $contents = stream_get_contents(stream: $stream, length: $this->maxBytes + 1);
        } finally {
            fclose(stream: $stream);
        }

        if (! is_string(value: $contents) || strlen(string: $contents) !== $size
            || strlen(string: $contents) > $this->maxBytes) {
            throw new ArtifactUnavailable(message: 'Download is temporarily unavailable.');
        }

        // Narrow the optional pinned digest before the constant-time comparison.
        $expectedSha256 = $this->expectedSha256;
        if ($expectedSha256 !== null && ! hash_equals(
            known_string: $expectedSha256,
            user_string: hash(algo: 'sha256', data: $contents),
        )) {
            throw new ArtifactUnavailable(message: 'Download is temporarily unavailable.');
        }

        $this->activityLog->recordButtonClick(actorId: $actorId, actorLabel: $actorLabel, button: TrackedButton::Download);

        return $contents;
    }
}
