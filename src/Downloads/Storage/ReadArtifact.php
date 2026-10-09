<?php

declare(strict_types=1);

namespace InterviewTask\Downloads\Storage;

use InterviewTask\Downloads\ArtifactUnavailable;
use InvalidArgumentException;

/**
 * Private fixed-artifact filesystem realization: bounded reading and integrity verification.
 *
 * @package InterviewTask\Downloads\Storage
 */
final readonly class ReadArtifact
{
    /**
     * @param string      $path           Trusted server-selected path; never a client-supplied filename.
     * @param string|null $expectedSha256 Trusted pinned SHA-256, mandatory for production composition.
     * @param int         $maxBytes       Maximum permitted bytes in PHP memory.
     *
     * @throws InvalidArgumentException When the trusted artifact policy is invalid.
     */
    public function __construct(
        private string      $path,
        private string|null $expectedSha256 = null,
        private int         $maxBytes = 2097152,
    )
    {
        if ($maxBytes < 1 || ($expectedSha256 !== null && preg_match(pattern: '/\A[a-f0-9]{64}\z/', subject: $expectedSha256) !== 1)) {
            throw new InvalidArgumentException(message: 'Invalid trusted download artifact policy.');
        }
    }

    /**
     * Read the fixed artifact only when the server-selected file meets size and integrity constraints.
     *
     * @return string Complete verified binary bytes.
     * @throws ArtifactUnavailable If artifact is missing, substituted, oversized or corrupted.
     */
    public function read() : string
    {
        if (is_link(filename: $this->path) || ! is_file(filename: $this->path)
            || ! is_readable(filename: $this->path)) {
            throw new ArtifactUnavailable(message: 'Download is temporarily unavailable.');
        }

        $stream = fopen(filename: $this->path, mode: 'rb');

        if ($stream === false) {
            throw new ArtifactUnavailable(message: 'Download is temporarily unavailable.');
        }

        try {
            // Check the actual opened file, not a path-level filesize() value that can become stale.
            $stat = fstat(stream: $stream);
            $size = is_array(value: $stat) ? ($stat['size'] ?? null) : null;

            if (! is_int(value: $size) || $size < 1 || $size > $this->maxBytes) {
                throw new ArtifactUnavailable(message: 'Download is temporarily unavailable.');
            }

            // The +1 read detects a file growing after its descriptor was inspected.
            $contents = stream_get_contents(stream: $stream, length: $this->maxBytes + 1);

            if (! is_string(value: $contents) || strlen(string: $contents) !== $size
                || strlen(string: $contents) > $this->maxBytes) {
                throw new ArtifactUnavailable(message: 'Download is temporarily unavailable.');
            }
        } finally {
            fclose(stream: $stream);
        }

        $expectedSha256 = $this->expectedSha256;
        if ($expectedSha256 !== null && ! hash_equals(
                known_string: $expectedSha256,
                user_string : hash(algo: 'sha256', data: $contents),
            )) {
            throw new ArtifactUnavailable(message: 'Download is temporarily unavailable.');
        }

        return $contents;
    }
}
