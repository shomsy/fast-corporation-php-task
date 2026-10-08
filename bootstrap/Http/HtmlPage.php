<?php

declare(strict_types=1);

namespace InterviewTask\Bootstrap\Http;

use InterviewTask\Infrastructure\Templating\Renderer;
use InterviewTask\Runtime\Http\Response;

/** Shared neutral HTTP response mechanism; no application policy. */
final readonly class HtmlPage
{
    public function __construct(private Renderer $renderer) {}

    /**
     * @param array<string, mixed> $data
     */
    public function render(string $path, array $data, int $status = 200) : Response
    {
        return new Response(
            $this->renderer->render(dirname(__DIR__, 2) . '/' . $path, $data),
            $status,
            [
                'Content-Type'            => 'text/html; charset=UTF-8',
                'Cache-Control'           => 'no-store',
                'X-Content-Type-Options'  => 'nosniff',
                'X-Frame-Options'         => 'DENY',
                'Referrer-Policy'         => 'no-referrer',
                'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'",
            ],
        );
    }
}
