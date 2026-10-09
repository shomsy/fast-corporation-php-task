<?php

declare(strict_types=1);

namespace InterviewTask\Tests\Runtime;

use InterviewTask\Runtime\Http\Request;
use InterviewTask\Runtime\Http\Response;
use InterviewTask\Runtime\Http\RouteAccess;
use InterviewTask\Runtime\Http\Router;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Cover safe URI boundaries and automatic HTTP method negotiation.
 *
 * @package InterviewTask\Tests\Runtime
 */
final class HttpProtocolTest extends TestCase
{
    /**
     * Invalid URI bytes and unsupported URI forms must never be mistaken for '/'.
     *
     * @return void No return value.
     */
    public function testMalformedRequestTargetsFailAtTransportBoundary() : void
    {
        $original = $_SERVER['REQUEST_URI'] ?? null;

        try {
            foreach (['/%XY', '/hello%00', '/hello%0A', '//untrusted.example/admin', 'https://other.example/', '/hello#fragment', '/hello\\path'] as $uri) {
                $_SERVER['REQUEST_URI'] = $uri;

                try {
                    Request::fromGlobals();
                    self::fail('Invalid request target accepted: ' . $uri);
                } catch (InvalidArgumentException $error) {
                    self::assertSame('Invalid request target.', $error->getMessage());
                }
            }
        } finally {
            if ($original === null) {
                unset($_SERVER['REQUEST_URI']);
            } else {
                $_SERVER['REQUEST_URI'] = $original;
            }
        }
    }

    /**
     * HEAD must authorize the GET route but never execute the view/audit handler.
     *
     * @return void No return value.
     */
    public function testHeadIsBodylessAndDoesNotInvokeGetBusinessAction() : void
    {
        $router  = new Router();
        $invoked = 0;
        $router->add(
            method : 'GET',
            path   : '/page-a',
            handler: static function () use (&$invoked) : Response {
                $invoked++;

                return Response::html(body: 'tracked view');
            },
            access : RouteAccess::SignedIn,
        );
        $router->guardWith(static fn (Request $request, RouteAccess $access) : Response|null => $request->server(key: 'identity') === 'trusted' ? null : Response::text(body: 'Forbidden', status: 403));

        $denied = $router->dispatch(request: new Request(method: 'HEAD', path: '/page-a'));
        self::assertSame(403, $denied->status());
        self::assertSame('', $denied->body());

        $allowed = $router->dispatch(request: new Request(method: 'HEAD', path: '/page-a', server: ['identity' => 'trusted']));
        self::assertSame(200, $allowed->status());
        self::assertSame('', $allowed->body());
        self::assertSame(0, $invoked);
    }

    /**
     * OPTIONS reports stable supported verbs without touching GET or POST handlers.
     *
     * @return void No return value.
     */
    public function testOptionsAnd405ListSupportedMethodsIncludingImplicitHead() : void
    {
        $router = new Router();
        $called = 0;
        $router->add(method: 'GET', path: '/view', handler: static function () use (&$called) : Response {
            $called++;

            return Response::text(body: 'ok');
        },           access: RouteAccess::Public);
        $router->add(method: 'POST', path: '/view', handler: static fn () : Response => Response::text(body: 'created'));

        $options = $router->dispatch(request: new Request(method: 'OPTIONS', path: '/view'));
        self::assertSame(204, $options->status());
        self::assertSame('', $options->body());
        self::assertSame('GET, HEAD, POST, OPTIONS', $options->headers()['Allow']);

        $method = $router->dispatch(request: new Request(method: 'PATCH', path: '/view'));
        self::assertSame(405, $method->status());
        self::assertSame($options->headers()['Allow'], $method->headers()['Allow']);
        self::assertSame(0, $called);

        self::assertSame(404, $router->dispatch(request: new Request(method: 'OPTIONS', path: '/not-a-route'))->status());
    }

    /**
     * Even errors must be bodyless on HEAD at the final HTTP output boundary.
     *
     * @return void No return value.
     */
    public function testHeadNeverEmitsAnHttpBody() : void
    {
        $old                       = $_SERVER['REQUEST_METHOD'] ?? null;
        $_SERVER['REQUEST_METHOD'] = 'HEAD';

        try {
            ob_start();
            Response::text(body: 'internal sensitive error', status: 500)->send();
            $body = ob_get_clean();
            self::assertSame('', $body);
        } finally {
            if ($old === null) {
                unset($_SERVER['REQUEST_METHOD']);
            } else {
                $_SERVER['REQUEST_METHOD'] = $old;
            }
        }
    }

}
