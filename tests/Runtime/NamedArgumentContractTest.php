<?php

declare(strict_types=1);

namespace InterviewTask\Tests\Runtime;

use InterviewTask\Accounts\AccountLifecycle;
use InterviewTask\ActivityTracking\Statistics;
use InterviewTask\Downloads\DownloadFile;
use InterviewTask\Runtime\Http\Router;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Guard named-argument compatibility on public app owner and HTTP entry boundaries.
 *
 * @package InterviewTask\Tests\Runtime
 */
final class NamedArgumentContractTest extends TestCase
{
    /**
     * A refactor must not silently rename the public arguments used by independent owners.
     *
     * @return void The test asserts the approved API parameter vocabulary.
     */
    public function testPublicNamedArgumentsRemainCompatible() : void
    {
        foreach ([
                     [AccountLifecycle::class, 'register', ['username', 'password']],
                     [AccountLifecycle::class, 'login', ['username', 'password']],
                     [Statistics::class, 'page', ['date', 'user', 'action', 'page']],
                     [Statistics::class, 'cursorPage', ['cursor', 'key', 'date', 'user', 'action']],
                     [DownloadFile::class, 'prepareDownload', ['actorId', 'actorLabel']],
                     [Router::class, 'add', ['method', 'path', 'handler', 'access']],
                 ] as [$owner, $method, $expected]) {
            $actual = array_map(
                callback: static fn (\ReflectionParameter $parameter) : string => $parameter->getName(),
                array   : (new ReflectionMethod(objectOrMethod: $owner, method: $method))->getParameters(),
            );

            self::assertSame($expected, $actual, $owner . '::' . $method . ' named-argument contract changed.');
        }
    }
}
