<?php

declare(strict_types=1);

namespace InterviewTask\Tests\Architecture;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Enforces declared owner exports for production imports; bootstrap/routes.php
 * is deliberately the composition root and does not take part in this scan.
 */
final class PublicSurfaceTest extends TestCase
{
    /** @var list<string> */
    private const array OWNERS = ['Accounts', 'ActivityTracking', 'CowOffer', 'Downloads'];

    public function testEveryCrossOwnerAndHttpAdapterImportUsesDeclaredModuleSurface(): void
    {
        $root = dirname(__DIR__, 2);
        $exports = [];
        foreach (self::OWNERS as $owner) {
            $manifest = require $root . '/src/' . $owner . '/module.php';
            self::assertIsArray($manifest);
            self::assertIsArray($manifest['public']);
            $exports[$owner] = $manifest['public'];
        }

        $problems = [];
        foreach ([$root . '/src', $root . '/bootstrap/Http'] as $directory) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));
            foreach ($iterator as $entry) {
                if (!$entry instanceof SplFileInfo || !$entry->isFile()
                    || $entry->getExtension() !== 'php' || $entry->getFilename() === 'module.php') {
                    continue;
                }
                $path = $entry->getPathname();
                $contents = file_get_contents($path);
                self::assertIsString($contents);

                $currentOwner = null;
                foreach (self::OWNERS as $owner) {
                    if (str_starts_with($path, $root . '/src/' . $owner . '/')) {
                        $currentOwner = $owner;
                        break;
                    }
                }
                foreach (self::importedOwnerTypes($contents) as [$owner, $type]) {
                    if ($currentOwner !== $owner && !in_array($type, $exports[$owner], true)) {
                        $problems[] = substr($path, strlen($root) + 1) . ' imports private ' . $type;
                    }
                }
            }
        }

        self::assertSame([], $problems);
    }

    public function testForbiddenImportMutationIsDetected(): void
    {
        $source = "<?php\nuse InterviewTask\\Accounts\\PasswordRejected;\n";
        self::assertSame([['Accounts', 'InterviewTask\\Accounts\\PasswordRejected']], self::importedOwnerTypes($source));
        $manifest = require dirname(__DIR__, 2) . '/src/Accounts/module.php';
        self::assertIsArray($manifest);
        self::assertArrayHasKey('public', $manifest);
        self::assertIsArray($manifest['public']);
        self::assertNotContains('InterviewTask\\Accounts\\PasswordRejected', $manifest['public']);
    }

    /**
     * @return list<array{string, string}>
     */
    private static function importedOwnerTypes(string $source): array
    {
        $result = [];
        preg_match_all(
            '/^use\\s+(InterviewTask\\\\(Accounts|ActivityTracking|CowOffer|Downloads)\\\\[A-Za-z0-9_\\\\]+)(?:\\s+as\\s+[A-Za-z_]+)?\\s*;/m',
            $source,
            $matches,
            PREG_SET_ORDER,
        );
        foreach ($matches as $match) {
            $result[] = [$match[2], $match[1]];
        }

        return $result;
    }
}
