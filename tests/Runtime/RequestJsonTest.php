<?php

declare(strict_types=1);

namespace InterviewTask\Tests\Runtime;

use InterviewTask\Runtime\Http\Request;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Fail-closed JSON object parsing at the same-origin API boundary.
 *
 * @package InterviewTask\Tests\Runtime
 */
final class RequestJsonTest extends TestCase
{
    /**
     * Reject malformed, oversized and ambiguous payloads; preserve JSON scalar typing.
     *
     * @return void No return value.
     */
    public function testRejectsInvalidJsonWithoutCoercion() : void
    {
        foreach (['{', '[]', 'null', '"string"', str_repeat(string: 'x', times: 16385)] as $body) {
            $request = new Request(method: 'POST', path: '/api/accounts/login',
                                   server: ['CONTENT_TYPE' => 'application/json'], rawBody: $body);
            try {
                $request->jsonObject();
                self::fail('Malformed JSON request unexpectedly accepted.');
            } catch (InvalidArgumentException $error) {
                self::assertInstanceOf(InvalidArgumentException::class, $error);
            }
        }

        $wrongType = new Request(method: 'POST', path: '/api/accounts/login',
                                 server: ['CONTENT_TYPE' => 'text/plain'], rawBody: '{}');
        $this->expectException(InvalidArgumentException::class);
        $wrongType->jsonObject();
    }

    /**
     * Accept only bounded JSON objects, keeping nested attacker data as untrusted values.
     *
     * @return void No return value.
     */
    public function testPreservesInputTypes() : void
    {
        $request = new Request(method : 'POST', path: '/api/accounts/register',
                               server : ['CONTENT_TYPE' => 'application/json; charset=utf-8'],
                               rawBody: '{"username":"alice","password":["not-a-string"]}');

        self::assertSame('alice', $request->jsonObject()['username']);
        self::assertIsArray($request->jsonObject()['password']);
    }
}
