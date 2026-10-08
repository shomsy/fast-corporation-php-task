<?php

declare(strict_types=1);

namespace InterviewTask\Tests\Flow;

use InterviewTask\Bootstrap\Http\AccountPages;
use InterviewTask\Bootstrap\Http\ActivityPages;
use InterviewTask\Bootstrap\Http\CowOfferPages;
use InterviewTask\Bootstrap\Http\DownloadPages;
use InterviewTask\Bootstrap\Http\HtmlPage;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class ComposerAutoloadTest extends TestCase
{
    public function testEveryBootstrapHttpAdapterResolvesFromComposerAutoloadOnly(): void
    {
        foreach ([AccountPages::class, ActivityPages::class, CowOfferPages::class, DownloadPages::class, HtmlPage::class] as $class) {
            self::assertTrue(class_exists(class: $class), $class);
            $file = (new ReflectionClass(objectOrClass: $class))->getFileName();
            self::assertIsString($file);
            self::assertSame(
                realpath(path: dirname(path: __DIR__, levels: 2) . '/bootstrap/Http/' . basename(path: $file)),
                $file,
            );
        }
    }
}
