<?php

declare(strict_types=1);

namespace InterviewTask\Tests\Runtime;

use InterviewTask\Runtime\Http\Request;
use InterviewTask\Runtime\Http\RouteAccess;
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
        ), access: RouteAccess::Public);

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
    /**
     * New routes without an explicit public designation must deny unauthenticated access.
     *
     * @return void No return value.
     */
    public function testProtectedRouteFailsClosedWithoutGuard(): void
    {
        $called = false;
        $router = new Router();
        $router->add(method: 'GET', path: '/secret', handler: static function () use (&$called): Response {
            $called = true;

            return Response::text(body: 'secret');
        });

        self::assertSame(403, $router->dispatch(request: new Request(method: 'GET', path: '/secret'))->status());
        self::assertFalse($called);
    }

    /**
     * Even explicitly public POST handlers cannot bypass the central unsafe-method guard.
     *
     * @return void No return value.
     */
    public function testPublicPostWithoutGuardIsRejected(): void
    {
        $router = new Router();
        $router->add(method: 'POST', path: '/signup', handler: static fn(): Response => Response::text(body: 'ok'), access: RouteAccess::Public);

        self::assertSame(403, $router->dispatch(request: new Request(method: 'POST', path: '/signup'))->status());
    }

    /**
     * One central route guard controls protected routes and all mutating methods.
     *
     * @return void No return value.
     */
    public function testConfiguredRouteGuardControlsAccessBeforeHandlerRuns(): void
    {
        $router = new Router();
        $router->guardWith(static function (Request $request, RouteAccess $access): ?Response {
            if ($access === RouteAccess::Admin && $request->server(key: 'role') !== 'admin') {
                return Response::text(body: 'Forbidden', status: 403);
            }

            if ($request->method() === 'POST' && $request->post(key: 'csrf_token') !== 'valid') {
                return Response::text(body: 'Forbidden', status: 403);
            }

            return null;
        });

        $router->add(method: 'GET', path: '/admin', handler: static fn(): Response => Response::text(body: 'admin'), access: RouteAccess::Admin);
        $router->add(method: 'POST', path: '/signup', handler: static fn(): Response => Response::text(body: 'ok'), access: RouteAccess::Public);

        self::assertSame(403, $router->dispatch(request: new Request(method: 'GET', path: '/admin'))->status());
        self::assertSame(200, $router->dispatch(request: new Request(method: 'GET', path: '/admin', server: ['role' => 'admin']))->status());
        self::assertSame(403, $router->dispatch(request: new Request(method: 'POST', path: '/signup'))->status());
        self::assertSame(200, $router->dispatch(request: new Request(method: 'POST', path: '/signup', post: ['csrf_token' => 'valid']))->status());
    }

}
