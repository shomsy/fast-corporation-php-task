<?php

declare(strict_types=1);

/**
 * Enforce complete structural PHPDoc coverage on the interview project.
 *
 * Every class, interface, enum, method, property and class constant must have
 * a DocBlock. Method tags must cover all named parameters and the return type,
 * except for constructors. This checks coverage; PHPStan validates type semantics.
 *
 * @package InterviewTask\Tooling
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use PhpParser\Node;
use PhpParser\Node\Stmt;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;

/**
 * Scan one AST and report incomplete structural documentation.
 *
 * Exception and deprecation annotations must correspond to real behavior;
 * narrative contracts and error semantics require human review.
 *
 * @param list<Stmt> $ast  Parsed PHP statements.
 * @param string     $path Path used in diagnostic messages.
 *
 * @return list<string> Missing contract annotations.
 */
function inspectDocContracts(array $ast, string $path) : array
{
    $visitor = new class ($path) extends NodeVisitorAbstract {
        /** @var list<string> */
        public array $issues = [];

        /**
         * @param string $path Relative PHP source path.
         */
        public function __construct(private readonly string $path) {}

        /**
         * Check type declarations and callable member contracts.
         *
         * @param Node $node Node visited by PhpParser.
         *
         * @return Node|null No AST changes; the visitor may return null.
         */
        #[Override]
        public function enterNode(Node $node) : Node|null
        {
            if (! self::needsContract($node)
                || ($node instanceof Stmt\ClassLike && $node->name === null)) {
                return null;
            }

            $line = $node->getStartLine();
            $doc  = $node->getDocComment();
            if ($doc === null) {
                $this->issues[] = "{$this->path}:{$line} missing DocBlock";

                return null;
            }

            $text = $doc->getText();
            if (! preg_match('/\/\*\*\s*\*\s*\S/', $text)) {
                $this->issues[] = "{$this->path}:{$line} missing meaningful summary";
            }

            if ($node instanceof Stmt\ClassMethod || $node instanceof Stmt\Function_) {
                $this->checkCallable(node: $node, text: $text, line: $line);
            }

            if (($node instanceof Stmt\Property || $node instanceof Stmt\ClassConst)
                && ! preg_match('/@var\s+\S+/', $text)) {
                $this->issues[] = "{$this->path}:{$line} missing @var";
            }

            return null;
        }

        /**
         * Define declarations whose public structure requires PHPDoc.
         *
         * @param Node $node Candidate syntax tree node.
         *
         * @return bool Whether it is a contract-bearing declaration.
         */
        private static function needsContract(Node $node) : bool
        {
            return $node instanceof Stmt\ClassLike || $node instanceof Stmt\ClassMethod
                || $node instanceof Stmt\Function_ || $node instanceof Stmt\Property
                || $node instanceof Stmt\ClassConst || $node instanceof Stmt\EnumCase;
        }

        /**
         * Verify each parameter and a non-constructor's declared result.
         *
         * @param Stmt\ClassMethod|Stmt\Function_ $node Callable declaration.
         * @param string                          $text Complete PHPDoc text (possibly multiline).
         * @param int                             $line Source declaration line.
         *
         * @return void No return value.
         */
        private function checkCallable(Stmt\ClassMethod|Stmt\Function_ $node, string $text, int $line) : void
        {
            foreach ($node->params as $param) {
                if (! $param->var instanceof Node\Expr\Variable || ! is_string($param->var->name)) {
                    continue;
                }
                $name = preg_quote($param->var->name, '/');
                // A wrapped type may span multiple lines; stop before the next PHPDoc tag.
                if (! preg_match('/@param\b(?:(?!\R[ \t]*\*[ \t]*@)[\s\S])*?\$' . $name . '\b/', $text)) {
                    $this->issues[] = "{$this->path}:{$line} missing @param \${$param->var->name}";
                }
            }

            if ($node->name->toString() !== '__construct' && ! preg_match('/@return\s+\S+/', $text)) {
                $this->issues[] = "{$this->path}:{$line} missing @return";
            }
        }

    };

    $traverser = new NodeTraverser();
    $traverser->addVisitor($visitor);
    $traverser->traverse($ast);

    return $visitor->issues;
}

$parser    = (new ParserFactory())->createForNewestSupportedVersion();
$errors    = [];
$fileCount = 0;
$roots     = ['src', 'runtime', 'infrastructure', 'bootstrap', 'tests'];

foreach ($roots as $root) {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
                                               dirname(__DIR__) . '/' . $root,
                                               FilesystemIterator::SKIP_DOTS,
                                           ));

    foreach ($files as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $path   = $file->getPathname();
        $source = file_get_contents($path);
        if ($source === false) {
            $errors[] = $path . ' is unreadable';
            continue;
        }

        $statements = $parser->parse($source);
        if ($statements === null) {
            continue;
        }

        $fileCount++;
        foreach (inspectDocContracts(
                     ast : $statements,
                     path: str_replace(dirname(__DIR__) . '/', '', $path),
                 ) as $error) {
            $errors[] = $error;
        }
    }
}

if ($errors !== []) {
    foreach ($errors as $error) {
        fwrite(STDERR, $error . PHP_EOL);
    }

    exit(1);
}

echo "PHPDoc structural coverage: PASS ({$fileCount} PHP files inspected)" . PHP_EOL;
