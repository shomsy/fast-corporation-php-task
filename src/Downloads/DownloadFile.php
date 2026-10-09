<?php

declare(strict_types=1);

namespace InterviewTask\Downloads;

use InterviewTask\ActivityTracking\ActivityLog;
use InterviewTask\ActivityTracking\TrackedButton;
use InterviewTask\ActivityTracking\TrackedPage;
use InterviewTask\Downloads\Storage\ReadArtifact;
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
     * @param ActivityLog $activityLog    Activity-event writer shared with the feature.
     * @param string      $artifactPath   Fixed server-side download artifact path, never taken from the request.
     * @param string|null $expectedSha256 Pinned SHA-256 of the deployed binary (required by the production composition
     *                                    root).
     * @param int         $maxBytes       Maximum allowed file bytes, protecting PHP memory.
     *
     * @throws \InvalidArgumentException If the trusted file policy is malformed.
     */
    public function __construct(
        private ActivityLog $activityLog,
        string              $artifactPath,
        string|null         $expectedSha256 = null,
        int                 $maxBytes = 2097152,
    )
    {
        $this->artifact = new ReadArtifact(path: $artifactPath, expectedSha256: $expectedSha256, maxBytes: $maxBytes);
    }

    /**
     * Private storage implementation; DownloadFile itself owns only accepted activity semantics.
     *
     * @var ReadArtifact
     */
    private ReadArtifact $artifact;

    /**
     * Record the authorized display of Page B.
     *
     * @param int    $actorId    Stable numeric identity associated with the audit event.
     * @param string $actorLabel Historical username snapshot written alongside the event.
     *
     * @return void No return value.
     */
    public function view(int $actorId, string $actorLabel) : void
    {
        $this->activityLog->recordPageView(actorId: $actorId, actorLabel: $actorLabel, page: TrackedPage::PageB);
    }

    /**
     * Prepare a fixed server-owned artifact and record the accepted download click after reading it. No activity event
     * is written if the artifact cannot be read.
     *
     * @param int    $actorId    Stable numeric identity associated with the audit event.
     * @param string $actorLabel Historical username snapshot written alongside the event.
     *
     * @return string Complete contents of the fixed readable artifact.
     * @throws ArtifactUnavailable When the configured artifact cannot be read completely.
     * @throws PDOException When the underlying SQLite operation fails.
     */
    public function prepareDownload(int $actorId, string $actorLabel) : string
    {
        $contents = $this->artifact->read();

        $this->activityLog->recordButtonClick(actorId: $actorId, actorLabel: $actorLabel, button: TrackedButton::Download);

        return $contents;
    }
}
