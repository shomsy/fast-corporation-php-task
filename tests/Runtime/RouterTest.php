<?php

declare(strict_types=1);

namespace InterviewTask\Tests\Runtime;

use InterviewTask\Runtime\Http\Request;
use InterviewTask\Runtime\Http\Response;
use InterviewTask\Runtime\Http\Router;
use PHPUnit\Framework\TestCase;

final class RouterTest extends TestCase
{
    public function testDispatchesExactRoute() : void
    {
        $router = new Router();
        $router->add('GET', '/hello', static fn (Request $request) : Response => Response::text(
            'hello:' . $request->path(),
        ));

        $response = $router->dispatch(new Request('GET', '/hello'));

        self::assertSame(200, $response->status());
        self::assertSame('hello:/hello', $response->body());
    }

    public function testReturnsMethodNotAllowedWhenPathExistsForAnotherMethod() : void
    {
        $router = new Router();
        $router->add('POST', '/submit', static fn () : Response => Response::text('ok'));

        $response = $router->dispatch(new Request('GET', '/submit'));

        self::assertSame(405, $response->status());
        self::assertSame('POST', $response->headers()['Allow']);
    }

    public function testAllowListsAllRegisteredMethods() : void
    {
        $router = new Router();
        $router->add('POST', '/submit', static fn () : Response => Response::text('ok'));
        $router->add('GET', '/submit', static fn () : Response => Response::text('ok'));

        $response = $router->dispatch(new Request('DELETE', '/submit'));

        self::assertSame(405, $response->status());
        self::assertSame('POST, GET', $response->headers()['Allow']);
    }

    public function testReturnsNotFoundForUnknownPath() : void
    {
        $response = (new Router())->dispatch(new Request('GET', '/missing'));

        self::assertSame(404, $response->status());
    }
}
