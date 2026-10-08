<?php

declare(strict_types=1);

namespace InterviewTask\Tests\Architecture;

use PhpParser\Node\Scalar\String_;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Checks owner SQL literals (PHP strings and owner-local .sql migrations).
 * Computed identifiers and dynamically generated SQL remain REVIEW-ENFORCED.
 */
final class DataOwnershipTest extends TestCase
{
    /** @var array<string, string> */
    private const array TABLE_OWNERS
        = [
            'accounts'        => 'Accounts',
            'activity_events' => 'ActivityTracking',
        ];

    public function testBusinessModulesDoNotDirectlyReferenceAnotherOwnersTables(): void
    {
        $root       = dirname(path: __DIR__, levels: 2) . '/src';
        $violations = [];
        foreach (['Accounts', 'ActivityTracking', 'CowOffer', 'Downloads'] as $owner) {
            $iterator = new RecursiveIteratorIterator(iterator: new RecursiveDirectoryIterator(directory: $root . '/' . $owner));
            foreach ($iterator as $file) {
                if (! $file instanceof SplFileInfo || ! $file->isFile()
                    || ! in_array(needle: $file->getExtension(), haystack: ['php', 'sql'], strict: true)) {
                    continue;
                }
                $contents = file_get_contents(filename: $file->getPathname());
                self::assertIsString($contents);
                $sqlStrings = $file->getExtension() === 'sql'
                    ? [$contents]
                    : self::literalStrings(source: $contents);
                foreach ($sqlStrings as $sql) {
                    foreach (self::foreignTables(owner: $owner, sql: $sql) as $table) {
                        $violations[] = $file->getPathname() . ' accesses foreign table ' . $table;
                    }
                }
            }
        }
        self::assertSame([], $violations);
    }

    /**
     * @return list<string>
     */
    private static function literalStrings(string $source): array
    {
        $parsed = (new ParserFactory())->createForNewestSupportedVersion()->parse(code: $source);
        if ($parsed === null) {
            return [];
        }

        $finder = new NodeFinder();
        $values = [];
        foreach ($finder->findInstanceOf(nodes: $parsed, class: String_::class) as $node) {
            $values[] = $node->value;
        }

        return $values;
    }

    /**
     * @return list<string>
     */
    private static function foreignTables(string $owner, string $sql): array
    {
        preg_match_all(
            pattern: '/\b(?:FROM|JOIN|INTO|UPDATE|REFERENCES|TABLE(?:\s+IF\s+NOT\s+EXISTS)?)\s+["\x60]?([a-z][a-z0-9_]*)/i',
            subject: $sql,
            matches: $matches,
        );
        $violations = [];
        foreach ($matches[1] as $table) {
            $table = strtolower(string: $table);
            if (isset(self::TABLE_OWNERS[$table]) && self::TABLE_OWNERS[$table] !== $owner) {
                $violations[$table] = $table;
            }
        }

        return array_values(array: $violations);
    }

    public function testForeignTableReferenceMutationsFailOwnerPolicy(): void
    {
        self::assertSame(['activity_events'], self::foreignTables(
            owner: 'Accounts',
            sql: "INSERT INTO activity_events (actor_id) VALUES (1)",
        ));
        self::assertSame(['accounts'], self::foreignTables(
            owner: 'ActivityTracking',
            sql: 'SELECT role FROM accounts WHERE id = :id',
        ));
        self::assertSame(['accounts'], self::foreignTables(
            owner: 'CowOffer',
            sql: 'UPDATE accounts SET role = :role WHERE id = :id',
        ));
        self::assertSame([], self::foreignTables(
            owner: 'ActivityTracking',
            sql: 'SELECT id FROM activity_events WHERE actor_id = :id',
        ));
    }
}
