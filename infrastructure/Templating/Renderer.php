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
    public function render(string $template, array $data = []) : string
    {
        if (! is_file($template)) {
            throw new RuntimeException('Template does not exist: ' . $template);
        }

        $escape = static function (mixed $value) : string {
            $text = match (true) {
                is_string($value)                => $value,
                is_int($value), is_float($value) => (string) $value,
                is_bool($value)                  => $value ? '1' : '',
                $value === null                  => '',
                $value instanceof Stringable     => $value->__toString(),
                default                          => throw new InvalidArgumentException('Template value is not safely stringable.'),
            };

            return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        };

        ob_start();

        try {
            require $template;
            $output = ob_get_clean();

            if ($output === false) {
                throw new RuntimeException('Unable to capture rendered template.');
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
