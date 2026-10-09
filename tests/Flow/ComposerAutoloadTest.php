<?php

declare(strict_types=1);

namespace InterviewTask\Tests\Flow;

use InterviewTask\Bootstrap\Http\AccountApi;
use InterviewTask\Bootstrap\Http\ActivityApi;
use InterviewTask\Bootstrap\Http\CowOfferApi;
use InterviewTask\Bootstrap\Http\DownloadApi;
use InterviewTask\Bootstrap\Http\SessionApi;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Enforce the API-only PHP delivery surface and autoload contracts.
 *
 * @package InterviewTask\Tests\Flow
 */
final class ComposerAutoloadTest extends TestCase
{
    /**
     * Ensure feature API adapters can be loaded directly through Composer.
     *
     * @return void No return value.
     */
    public function testApiAdaptersResolveThroughComposer() : void
    {
        foreach ([AccountApi::class, ActivityApi::class, CowOfferApi::class, DownloadApi::class, SessionApi::class] as $class) {
            self::assertTrue(class_exists(class: $class));
            $path = (new ReflectionClass(objectOrClass: $class))->getFileName();
            self::assertIsString($path);
            self::assertSame(
                realpath(path: dirname(path: __DIR__, levels: 2) . '/bootstrap/Http/' . basename(path: $path)),
                $path,
            );
        }
    }

    /**
     * Prevent executable PHP/HTML templates from re-entering business or HTTP layers.
     *
     * @return void No return value.
     */
    public function testNoExecutablePhpTemplatesRemainInBusinessAndHttpLayers() : void
    {
        $root = dirname(path: __DIR__, levels: 2);
        foreach (['src', 'bootstrap', 'infrastructure'] as $directory) {
            $walker = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(directory: $root . '/' . $directory));
            foreach ($walker as $entry) {
                if (! $entry instanceof \SplFileInfo || ! $entry->isFile() || $entry->getExtension() !== 'php') {
                    continue;
                }
                $contents = file_get_contents(filename: $entry->getPathname());
                self::assertIsString($contents);
                self::assertStringNotContainsString('?>', $contents, $entry->getPathname());
            }
        }
    }
}
