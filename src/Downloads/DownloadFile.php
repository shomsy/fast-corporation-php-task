<?php

declare(strict_types=1);

namespace InterviewTask\Downloads;

use InterviewTask\ActivityTracking\ActivityLog;
use InterviewTask\ActivityTracking\TrackedButton;
use InterviewTask\ActivityTracking\TrackedPage;

/**
 * Only a server-configured artifact can be downloaded.
 * Never accepts a client-supplied filename or path.
 */
final readonly class DownloadFile
{
    public function __construct(
        private ActivityLog $activityLog,
        private string $artifactPath,
    ) {}

    public function view(int $actorId, string $actorLabel): void
    {
        $this->activityLog->recordPageView($actorId, $actorLabel, TrackedPage::PageB);
    }

    /**
     * @return string Complete contents of the fixed readable artifact.
     */
    public function read(int $actorId, string $actorLabel): string
    {
        if (!is_file($this->artifactPath) || !is_readable($this->artifactPath)) {
            throw new ArtifactUnavailable('Download is temporarily unavailable.');
        }

        $contents = file_get_contents($this->artifactPath);

        if ($contents === false) {
            throw new ArtifactUnavailable('Download is temporarily unavailable.');
        }

        $this->activityLog->recordButtonClick($actorId, $actorLabel, TrackedButton::Download);

        return $contents;
    }
}
