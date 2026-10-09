<?php

declare(strict_types=1);

namespace InterviewTask\Tests\Downloads;

use InterviewTask\Downloads\ArtifactUnavailable;
use InterviewTask\Downloads\Storage\ReadArtifact;
use PHPUnit\Framework\TestCase;

/**
 * Exercise the descriptor-based fixed-artifact reader without an HTTP or database dependency.
 *
 * @package InterviewTask\Tests\Downloads
 */
final class ReadArtifactTest extends TestCase
{
    /**
     * The opened descriptor must not permit an empty or oversized binary.
     *
     * @return void No return value.
     */
    public function testEmptyFixedArtifactFailsClosed() : void
    {
        $path = tempnam(directory: sys_get_temp_dir(), prefix: 'fast-verified-');
        self::assertNotFalse($path);

        try {
            $reader = new ReadArtifact(path: $path);
            $this->expectException(ArtifactUnavailable::class);
            $reader->read();
        } finally {
            unlink(filename: $path);
        }
    }

    /**
     * A pinned artifact must be read in full before its SHA-256 is accepted.
     *
     * @return void No return value.
     */
    public function testPinnedArtifactIsVerifiedAgainstOpenedBytes() : void
    {
        $path = tempnam(directory: sys_get_temp_dir(), prefix: 'fast-verified-');
        self::assertNotFalse($path);

        try {
            $bytes = 'safe artifact bytes';
            self::assertNotFalse(file_put_contents(filename: $path, data: $bytes));
            $reader = new ReadArtifact(path: $path, expectedSha256: hash(algo: 'sha256', data: $bytes), maxBytes: strlen(string: $bytes));
            self::assertSame($bytes, $reader->read());
        } finally {
            unlink(filename: $path);
        }
    }
}
