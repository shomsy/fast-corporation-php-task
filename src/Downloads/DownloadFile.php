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
     */
    public function __construct(
        private ActivityLog $activityLog,
        private string      $artifactPath,
    ) {}

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
     * Load a fixed server-owned artifact before recording the download click. No activity event is written if the artifact cannot be read.
     *
     * @param int $actorId Stable numeric identity associated with the audit event.
     * @param string $actorLabel Historical username snapshot written alongside the event.
     * @return string Complete contents of the fixed readable artifact.
     * @throws ArtifactUnavailable When the configured artifact cannot be read completely.
     * @throws PDOException When the underlying SQLite operation fails.
     */
    public function read(int $actorId, string $actorLabel): string
    {
        if (! is_file(filename: $this->artifactPath) || ! is_readable(filename: $this->artifactPath)) {
            throw new ArtifactUnavailable(message: 'Download is temporarily unavailable.');
        }

        $contents = file_get_contents(filename: $this->artifactPath);

        if ($contents === false) {
            throw new ArtifactUnavailable(message: 'Download is temporarily unavailable.');
        }

        $this->activityLog->recordButtonClick(actorId: $actorId, actorLabel: $actorLabel, button: TrackedButton::Download);

        return $contents;
    }
}
