<?php

declare(strict_types=1);

namespace InterviewTask\Tests\Architecture;

use InterviewTask\Accounts\Accounts;
use InterviewTask\ActivityTracking\ActivityLog;
use InterviewTask\ActivityTracking\DailyReports;
use InterviewTask\ActivityTracking\Statistics;
use InterviewTask\Downloads\DownloadFile;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Regression gates for hiding the actual SQL and filesystem mechanism behind business behavior.
 *
 * @package InterviewTask\Tests\Architecture
 */
final class OwnerTechnologyBoundaryTest extends TestCase
{
    /**
     * Ensure concrete SQLite and filesystem access is kept out of the owner behavior entry points.
     *
     * @return void No return value.
     */
    public function testBusinessEntryPointsHaveNoDirectSqlOrFilesystemCalls() : void
    {
        $privateMechanisms = [
            Accounts::class     => ['->prepare(', '->fetch(', '->execute(', 'password_hash(', 'password_verify('],
            ActivityLog::class  => ['->prepare(', '->fetch(', '->execute('],
            DailyReports::class => ['->prepare(', '->fetch(', '->execute('],
            Statistics::class   => ['->prepare(', '->fetch(', '->fetchAll(', '->bindValue(', '->execute('],
            DownloadFile::class => ['fopen(', 'is_file(', 'is_link(', 'stream_get_contents(', 'hash_equals('],
        ];

        foreach ($privateMechanisms as $ownerClass => $forbiddenCalls) {
            $path = (new ReflectionClass(objectOrClass: $ownerClass))->getFileName();
            self::assertIsString($path);
            $source = file_get_contents(filename: $path);
            self::assertIsString($source);
            foreach ($forbiddenCalls as $call) {
                self::assertStringNotContainsString($call, $source, $ownerClass . ' must delegate the concrete mechanism to owner-private code.');
            }
        }
    }

    /**
     * Keep all concrete owner-specific realizations private in their module manifests.
     *
     * @return void No return value.
     */
    public function testTechnicalRealizationsAreNotPromotedIntoBusinessPublicApi() : void
    {
        $root = dirname(path: __DIR__, levels: 2);
        foreach ([
                     'Accounts'         => [
                         'InterviewTask\Accounts\Persistence\SqliteAccounts',
                         'InterviewTask\Accounts\Credentials\BcryptPasswords',
                         'InterviewTask\Accounts\Credentials\AccountCredentials',
                     ],
                     'ActivityTracking' => [
                         'InterviewTask\ActivityTracking\Persistence\RecordActivity',
                         'InterviewTask\ActivityTracking\Persistence\ReadDailyActivity',
                         'InterviewTask\ActivityTracking\Persistence\ReadActivityHistory',
                         'InterviewTask\ActivityTracking\Query\ActivityCursor',
                         'InterviewTask\ActivityTracking\Query\ActivityFilters',
                         'InterviewTask\ActivityTracking\Query\UtcDate',
                     ],
                     'Downloads'        => ['InterviewTask\Downloads\Storage\ReadArtifact'],
                 ] as $owner => $realizations) {
            $manifest = require $root . '/src/' . $owner . '/module.php';
            self::assertIsArray($manifest);
            self::assertIsArray($manifest['public']);
            foreach ($realizations as $implementation) {
                self::assertNotContains($implementation, $manifest['public']);
            }
        }
    }
}
