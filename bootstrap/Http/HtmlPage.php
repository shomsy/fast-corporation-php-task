<?php

declare(strict_types=1);

namespace InterviewTask\Bootstrap\Http;

use InterviewTask\Infrastructure\Templating\Renderer;
use InterviewTask\Runtime\Http\Response;

/**
 * Shared neutral HTTP response mechanism; no application policy.
 *
 * @package InterviewTask\Bootstrap\Http
 */
final readonly class HtmlPage
{
    /**
     * Use the trusted local PHP template renderer.
     *
     * @param Renderer $renderer Trusted PHP template renderer.
     */
    public function __construct(private Renderer $renderer) {}

    /**
     * Render a private view and attach browser security and cache-control headers.
     *
     * @param string               $path   Absolute route, request path, or trusted local filesystem path.
     * @param array<string, mixed> $data
     * @param int                  $status HTTP response status code.
     *
     * @return Response HTTP response with rendered HTML and security headers.
     */
    public function render(string $path, array $data, int $status = 200) : Response
    {
        return new Response(
            body   : $this->renderer->render(template: dirname(path: __DIR__, levels: 2) . '/' . $path, data: $data),
            status : $status,
            headers: [
                         'Content-Type'           => 'text/html; charset=UTF-8',
                         'Cache-Control'          => 'no-store',
                         'X-Content-Type-Options' => 'nosniff',
                         'X-Frame-Options'        => 'DENY',
                         'Referrer-Policy'        => 'no-referrer',
                     ],
        );
    }
}
