<?php

declare(strict_types=1);

namespace InterviewTask\Tests\Infrastructure;

use InterviewTask\Infrastructure\Templating\Renderer;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class RendererTest extends TestCase
{
    public function testProvidesCentralEscapingHelperToTemplate(): void
    {
        $template = tempnam(sys_get_temp_dir(), 'fast-task-template-');

        if ($template === false) {
            throw new RuntimeException('Unable to create temporary template.');
        }

        file_put_contents($template, '<?php echo $escape($data["name"]); ?>');

        try {
            $output = (new Renderer())->render($template, ['name' => '<script>alert("x")</script>']);
        } finally {
            unlink($template);
        }

        self::assertSame('&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt;', $output);
    }
}
