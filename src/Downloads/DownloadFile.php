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
        private string      $artifactPath,
    ) {}

    public function view(int $actorId, string $actorLabel): void
    {
        $this->activityLog->recordPageView(actorId: $actorId, actorLabel: $actorLabel, page: TrackedPage::PageB);
    }

    /**
     * @return string Complete contents of the fixed readable artifact.
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
