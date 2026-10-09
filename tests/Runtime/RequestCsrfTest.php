<?php

declare(strict_types=1);

namespace InterviewTask\Tests\Runtime;

use InterviewTask\Runtime\Http\Request;
use PHPUnit\Framework\TestCase;

/**
 * HTTP transport contract: JSON API headers cannot bypass legacy form CSRF checks.
 *
 * @package InterviewTask\Tests\Runtime
 */
final class RequestCsrfTest extends TestCase
{
    /**
     * Only JSON API unsafe requests accept the CSRF header; SSR must use the form field.
     *
     * @return void No return value.
     */
    public function testSelectsCorrectCsrfTransport() : void
    {
        $form = new Request(method: 'POST', path: '/login',
                            post  : ['csrf_token' => 'form-token'],
                            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_X_CSRF_TOKEN' => 'header-token']);
        self::assertSame('form-token', $form->csrfToken());

        $api = new Request(method: 'POST', path: '/api/accounts/login',
                           post  : ['csrf_token' => 'form-token'],
                           server: ['CONTENT_TYPE' => 'application/json; charset=utf-8', 'HTTP_X_CSRF_TOKEN' => 'header-token']);
        self::assertSame('header-token', $api->csrfToken());

        $missing = new Request(method: 'POST', path: '/api/accounts/login',
                               server: ['CONTENT_TYPE' => 'application/json']);
        self::assertNull($missing->csrfToken());

        $wrongType = new Request(method: 'POST', path: '/api/accounts/login',
                                 server: ['CONTENT_TYPE' => 'text/plain', 'HTTP_X_CSRF_TOKEN' => 'header-token']);
        self::assertNull($wrongType->csrfToken());
    }

    /**
     * Native binary download keeps a CSRF-hidden form field without weakening JSON requests.
     *
     * @return void No return value.
     */
    public function testNativeDownloadUsesFormCsrf() : void
    {
        $request = new Request(method: 'POST', path: '/api/downloads/sample',
                               post  : ['csrf_token' => 'hidden-value'],
                               server: [
                                           'CONTENT_TYPE'      => 'application/x-www-form-urlencoded',
                                           'HTTP_X_CSRF_TOKEN' => 'not-the-form-token',
                                       ]);
        self::assertSame('hidden-value', $request->csrfToken());

        $jsonRoute = new Request(method: 'POST', path: '/api/accounts/login',
                                 post  : ['csrf_token' => 'hidden-value'],
                                 server: ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);
        self::assertNull($jsonRoute->csrfToken());
    }
}
