<?php

declare(strict_types=1);

namespace InterviewTask\Infrastructure\Templating;

use InvalidArgumentException;
use RuntimeException;
use Stringable;
use Throwable;

final class Renderer
{
    /**
     * Templates receive $data and an $escape callable.
     *
     * @param array<string, mixed> $data
     */
    public function render(string $template, array $data = []): string
    {
        if (! is_file(filename: $template)) {
            throw new RuntimeException(message: 'Template does not exist: ' . $template);
        }

        $escape = static function (mixed $value): string {
            $text = match (true) {
                is_string(value: $value)                => $value,
                is_int(value: $value), is_float(value: $value) => (string) $value,
                is_bool(value: $value)                  => $value ? '1' : '',
                $value === null                  => '',
                $value instanceof Stringable     => $value->__toString(),
                default                          => throw new InvalidArgumentException(message: 'Template value is not safely stringable.'),
            };

            return htmlspecialchars(string: $text, flags: ENT_QUOTES | ENT_SUBSTITUTE, encoding: 'UTF-8');
        };

        ob_start();

        try {
            require $template;
            $output = ob_get_clean();

            if ($output === false) {
                throw new RuntimeException(message: 'Unable to capture rendered template.');
            }

            return $output;
        } catch (Throwable $throwable) {
            if (ob_get_level() > 0) {
                ob_end_clean();
            }

            throw $throwable;
        }
    }
}
