<?php

declare(strict_types=1);

/**
 * Enforce only the two explicitly agreed MOI/PHP Hammer PHP syntax preferences.
 * No general-purpose reformatting; leave PhpStorm MOI in charge of presentation.
 */
require dirname(__DIR__) . '/vendor/autoload.php';

use PhpParser\Node;
use PhpParser\Node\Expr\PreDec;
use PhpParser\Node\Expr\PreInc;
use PhpParser\Node\NullableType;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;

function moiSyntaxProblems(string $source) : array
{
    $parser   = (new ParserFactory())->createForNewestSupportedVersion();
    $ast      = $parser->parse($source) ?? [];
    $finder   = new NodeFinder();
    $problems = [];

    foreach ($finder->find($ast, static fn (Node $node) : bool => $node instanceof NullableType
        || $node instanceof PreInc || $node instanceof PreDec) as $node) {
        if ($node instanceof NullableType) {
            $problems[] = [$node->getStartLine(), 'Use Type|null, not ?Type.'];
        } else {
            $problems[] = [$node->getStartLine(), 'Use postfix increment/decrement; if the prefix result is consumed, refactor safely.'];
        }
    }

    foreach (token_get_all($source) as $token) {
        if (! is_array($token) || $token[0] !== T_DOC_COMMENT) {
            continue;
        }

        // Detect nullable PHPDoc types, including types nested in array shapes/callables.
        if (preg_match_all('~\?(?:[A-Za-z_]|\\\\)[A-Za-z0-9_\\\\]*~', $token[1], $matches, PREG_OFFSET_CAPTURE) === false) {
            throw new RuntimeException('Could not inspect PHPDoc.');
        }
        foreach ($matches[0] as [$type, $offset]) {
            $line       = $token[2] + substr_count(substr($token[1], 0, $offset), "\n");
            $problems[] = [$line, 'Use Type|null instead of ' . $type . ' in PHPDoc.'];
        }
    }

    return $problems;
}

if (($argv[1] ?? '') === '--self-test') {
    $ok  = <<<'PHP'
        <?php
        /** @param string|null $value */
        function check(string|null $value): string|null {
            $result = $value?->value ?? null;
            for ($i = 0; $i < 3; $i++) { $i++; }
            return $result;
        }
        PHP;
    $bad = <<<'PHP'
        <?php
        /** @param ?string $value */
        function check(?string $value): ?string {
            ++$counter;
            return $value;
        }
        PHP;
    if (moiSyntaxProblems($ok) !== [] || count(moiSyntaxProblems($bad)) !== 4) {
        fwrite(STDERR, "MOI convention checker self-test FAILED.\n");
        exit(1);
    }
    echo "MOI convention checker self-test: PASS\n";
    exit(0);
}

$root     = dirname(__DIR__);
$skip     = ['vendor' => true, 'node_modules' => true, '.git' => true, '.idea' => true, 'var' => true];
$files    = new RecursiveIteratorIterator(
    new RecursiveCallbackFilterIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        static function (SplFileInfo $entry) use ($skip) : bool {
            return ! ($entry->isDir() && isset($skip[$entry->getFilename()]));
        },
    ),
);
$checked  = 0;
$failures = 0;
foreach ($files as $entry) {
    if (! $entry->isFile() || $entry->getExtension() !== 'php') {
        continue;
    }
    $path = $entry->getPathname();
    $code = file_get_contents($path);
    if (! is_string($code)) {
        fwrite(STDERR, "Cannot read $path\n");
        exit(2);
    }
    $checked++;
    try {
        $problems = moiSyntaxProblems($code);
    } catch (Throwable $error) {
        fwrite(STDERR, substr($path, strlen($root) + 1) . ': PHP parser failed: ' . $error->getMessage() . "\n");
        exit(2);
    }
    foreach ($problems as [$line, $message]) {
        $failures++;
        fwrite(STDERR, substr($path, strlen($root) + 1) . ":$line: $message\n");
    }
}
if ($failures > 0) {
    fwrite(STDERR, "MOI PHP conventions: FAILED ($failures findings in $checked PHP files).\n");
    exit(1);
}
echo "MOI PHP conventions: PASS ($checked PHP files, Type|null and postfix increments/decrements).\n";
