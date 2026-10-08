<?php

declare(strict_types=1);

namespace InterviewTask\Tests\Runtime;

use InterviewTask\Runtime\Http\Request;
use InterviewTask\Runtime\Http\Response;
use InterviewTask\Runtime\Http\Router;
use PHPUnit\Framework\TestCase;

/**
 * Regression checks for Router test, covering observable behavior and failure paths.
 *
 * @package InterviewTask\Tests\Runtime
 */
final class RouterTest extends TestCase
{
    /**
     * Verify dispatches exact route through the public behavior.
     *
     * @return void No return value.
     */
    public function testDispatchesExactRoute(): void
    {
        $router = new Router();
        $router->add(method: 'GET', path: '/hello', handler: static fn(Request $request): Response => Response::text(
            body: 'hello:' . $request->path(),
        ));

        $response = $router->dispatch(request: new Request(method: 'GET', path: '/hello'));

        self::assertSame(200, $response->status());
        self::assertSame('hello:/hello', $response->body());
    }

    /**
     * Verify returns method not allowed when path exists for another method through the public behavior.
     *
     * @return void No return value.
     */
    public function testReturnsMethodNotAllowedWhenPathExistsForAnotherMethod(): void
    {
        $router = new Router();
        $router->add(method: 'POST', path: '/submit', handler: static fn(): Response => Response::text(body: 'ok'));

        $response = $router->dispatch(request: new Request(method: 'GET', path: '/submit'));

        self::assertSame(405, $response->status());
        self::assertSame('POST', $response->headers()['Allow']);
    }

    /**
     * Verify allow lists all registered methods through the public behavior.
     *
     * @return void No return value.
     */
    public function testAllowListsAllRegisteredMethods(): void
    {
        $router = new Router();
        $router->add(method: 'POST', path: '/submit', handler: static fn(): Response => Response::text(body: 'ok'));
        $router->add(method: 'GET', path: '/submit', handler: static fn(): Response => Response::text(body: 'ok'));

        $response = $router->dispatch(request: new Request(method: 'DELETE', path: '/submit'));

        self::assertSame(405, $response->status());
        self::assertSame('POST, GET', $response->headers()['Allow']);
    }

    /**
     * Verify returns not found for unknown path through the public behavior.
     *
     * @return void No return value.
     */
    public function testReturnsNotFoundForUnknownPath(): void
    {
        $response = (new Router())->dispatch(request: new Request(method: 'GET', path: '/missing'));

        self::assertSame(404, $response->status());
    }
}
