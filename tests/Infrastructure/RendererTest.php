<?php

declare(strict_types=1);

namespace InterviewTask\Tests\Infrastructure;

use InterviewTask\Infrastructure\Templating\Renderer;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Regression checks for Renderer test, covering observable behavior and failure paths.
 *
 * @package InterviewTask\Tests\Infrastructure
 */
final class RendererTest extends TestCase
{
    /**
     * Verify provides central escaping helper to template through the public behavior.
     *
     * @return void No return value.
     */
    public function testProvidesCentralEscapingHelperToTemplate(): void
    {
        $template = tempnam(directory: sys_get_temp_dir(), prefix: 'fast-task-template-');

        if ($template === false) {
            throw new RuntimeException(message: 'Unable to create temporary template.');
        }

        file_put_contents(filename: $template, data: '<?php echo $escape($data["name"]); ?>');

        try {
            $output = (new Renderer())->render(template: $template, data: ['name' => '<script>alert("x")</script>']);
        } finally {
            unlink(filename: $template);
        }

        self::assertSame('&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt;', $output);
    }

    /**
     * A throwing template with its own buffer must not leak the renderer buffer or replace the exception.
     *
     * @return void No return value.
     */
    public function testNestedThrowingTemplateRestoresCallerBuffer(): void
    {
        $template = tempnam(directory: sys_get_temp_dir(), prefix: 'fast-task-template-');
        self::assertNotFalse($template);
        file_put_contents(filename: $template, data: '<?php ob_start(); echo "nested"; throw new \RuntimeException("original template failure");');

        $initialLevel = ob_get_level();
        ob_start();
        $callerLevel = ob_get_level();
        echo 'caller output';

        try {
            try {
                (new Renderer())->render(template: $template);
                self::fail('Expected template failure.');
            } catch (RuntimeException $exception) {
                self::assertSame('original template failure', $exception->getMessage());
            }

            self::assertSame($callerLevel, ob_get_level(), 'Renderer leaked an output buffer.');
            self::assertSame('caller output', ob_get_contents(), 'Renderer disturbed the caller buffer.');
        } finally {
            while (ob_get_level() > $initialLevel) {
                ob_end_clean();
            }
            unlink(filename: $template);
        }
    }

    /**
     * A successful require that leaves an inner buffer open is an invalid rendering, not partial HTML.
     *
     * @return void No return value.
     */
    public function testUnbalancedSuccessfulTemplateIsRejectedWithoutLeakingBuffers(): void
    {
        $template = tempnam(directory: sys_get_temp_dir(), prefix: 'fast-task-template-');
        self::assertNotFalse($template);
        file_put_contents(filename: $template, data: '<?php echo "before"; ob_start(); echo "inner";');

        $initialLevel = ob_get_level();
        ob_start();
        $callerLevel = ob_get_level();

        try {
            try {
                (new Renderer())->render(template: $template);
                self::fail('Expected unbalanced buffer failure.');
            } catch (RuntimeException $exception) {
                self::assertSame('Template left output buffers open.', $exception->getMessage());
            }
            self::assertSame($callerLevel, ob_get_level(), 'Renderer leaked its buffer.');
        } finally {
            while (ob_get_level() > $initialLevel) {
                ob_end_clean();
            }
            unlink(filename: $template);
        }
    }

    /**
     * A template that correctly closes its nested buffer still returns its complete rendered output.
     *
     * @return void No return value.
     */
    public function testBalancedNestedTemplateCapturesCompleteOutput(): void
    {
        $template = tempnam(directory: sys_get_temp_dir(), prefix: 'fast-task-template-');
        self::assertNotFalse($template);
        file_put_contents(filename: $template, data: '<?php echo "before"; ob_start(); echo "inner"; ob_end_flush(); echo "after";');

        $initialLevel = ob_get_level();

        try {
            self::assertSame('beforeinnerafter', (new Renderer())->render(template: $template));
            self::assertSame($initialLevel, ob_get_level());
        } finally {
            while (ob_get_level() > $initialLevel) {
                ob_end_clean();
            }
            unlink(filename: $template);
        }
    }

}
