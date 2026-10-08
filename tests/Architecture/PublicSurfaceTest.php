<?php

declare(strict_types=1);

namespace InterviewTask\Tests\Architecture;

use InterviewTask\Accounts\AccountLifecycle;
use InterviewTask\Accounts\CurrentAccount;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Stmt\GroupUse;
use PhpParser\Node\Stmt\Use_;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionNamedType;
use SplFileInfo;

/**
 * AST-based owner export enforcement for statically named PHP class references.
 * Explicit composition root bootstrap/routes.php and test code are excluded.
 * Runtime class-string/reflection construction is an independently reviewed risk.
 */
final class PublicSurfaceTest extends TestCase
{
    /** @var list<string> */
    private const array OWNERS = ['Accounts', 'ActivityTracking', 'CowOffer', 'Downloads'];

    public function testEveryCrossOwnerAndHttpAdapterClassReferenceUsesDeclaredSurface(): void
    {
        $root     = dirname(path: __DIR__, levels: 2);
        $exports  = self::exports(root: $root);
        $problems = [];

        foreach ([$root . '/src', $root . '/bootstrap/Http'] as $directory) {
            $iterator = new RecursiveIteratorIterator(iterator: new RecursiveDirectoryIterator(directory: $directory));
            foreach ($iterator as $entry) {
                if (! $entry instanceof SplFileInfo || ! $entry->isFile()
                    || $entry->getExtension() !== 'php' || $entry->getFilename() === 'module.php') {
                    continue;
                }

                $path     = $entry->getPathname();
                $contents = file_get_contents(filename: $path);
                self::assertIsString($contents);
                $currentOwner = null;
                foreach (self::OWNERS as $owner) {
                    if (str_starts_with(haystack: $path, needle: $root . '/src/' . $owner . '/')) {
                        $currentOwner = $owner;
                        break;
                    }
                }

                foreach (self::referencedOwnerTypes(source: $contents) as [$owner, $type]) {
                    if ($currentOwner !== $owner && ! in_array(needle: $type, haystack: $exports[$owner], strict: true)) {
                        $problems[] = substr(string: $path, offset: strlen(string: $root) + 1) . ' references private ' . $type;
                    }
                }
            }
        }

        self::assertSame([], $problems);
    }

    /**
     * @return array<string, list<string>>
     */
    private static function exports(string $root): array
    {
        $result = [];
        foreach (self::OWNERS as $owner) {
            $manifest = require $root . '/src/' . $owner . '/module.php';
            self::assertIsArray($manifest);
            self::assertIsArray($manifest['public']);
            /** @var list<string> $public */
            $public         = $manifest['public'];
            $result[$owner] = $public;
        }

        return $result;
    }

    /**
     * @return list<array{string, string}>
     */
    private static function referencedOwnerTypes(string $source): array
    {
        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $parsed = $parser->parse(code: $source);
        if ($parsed === null) {
            return [];
        }

        $traverser = new NodeTraverser(new NameResolver());
        $nodes     = $traverser->traverse(nodes: $parsed);
        $finder    = new NodeFinder();
        $names     = [];

        // NameResolver expands aliases in new, type hints, static calls,
        // class constants, traits, attributes, instanceof, extends and more.
        foreach ($finder->findInstanceOf(nodes: $nodes, class: FullyQualified::class) as $name) {
            $names[] = $name->toString();
        }

        // Import declarations are not necessarily rewritten by NameResolver.
        foreach ($finder->findInstanceOf(nodes: $nodes, class: Use_::class) as $use) {
            foreach ($use->uses as $item) {
                if ($use->type === Use_::TYPE_NORMAL && $item->type !== Use_::TYPE_FUNCTION && $item->type !== Use_::TYPE_CONSTANT) {
                    $names[] = $item->name->toString();
                }
            }
        }
        foreach ($finder->findInstanceOf(nodes: $nodes, class: GroupUse::class) as $group) {
            if ($group->type !== Use_::TYPE_NORMAL) {
                continue;
            }
            foreach ($group->uses as $item) {
                if ($item->type !== Use_::TYPE_FUNCTION && $item->type !== Use_::TYPE_CONSTANT) {
                    $names[] = $group->prefix->toString() . '\\' . $item->name->toString();
                }
            }
        }

        $found = [];
        foreach ($names as $className) {
            $parts = explode(separator: '\\', string: $className);
            $owner = $parts[1] ?? null;
            if (($parts[0] ?? null) !== 'InterviewTask'
                || $owner === null || ! in_array(needle: $owner, haystack: self::OWNERS, strict: true)
                || count(value: $parts) < 3) {
                continue;
            }
            $found[$className] = [$owner, $className];
        }
        ksort(array: $found);

        return array_values(array: $found);
    }

    public function testOnlyTheTrackedAccountLifecycleIsPubliclyExposedForAuthenticationChanges(): void
    {
        $exports = self::exports(root: dirname(path: __DIR__, levels: 2));
        self::assertContains(AccountLifecycle::class, $exports['Accounts']);
        self::assertContains(CurrentAccount::class, $exports['Accounts']);

        foreach ([
            'InterviewTask\Accounts\Register\Register',
            'InterviewTask\Accounts\Login\Login',
            'InterviewTask\Accounts\Logout\Logout',
            'InterviewTask\Accounts\SessionIdentity',
            'InterviewTask\Accounts\Accounts',
        ] as $privateType) {
            self::assertNotContains($privateType, $exports['Accounts'], $privateType . ' is a private implementation detail');
        }

        $view = new ReflectionClass(objectOrClass: CurrentAccount::class);
        self::assertTrue($view->hasMethod(name: 'current'));
        self::assertFalse($view->hasMethod(name: 'signIn'));
        self::assertFalse($view->hasMethod(name: 'signOut'));
    }

    public function testExportedLifecycleDoesNotLeakInternalAccountInPublicMethodReturns(): void
    {
        $exports   = self::exports(root: dirname(path: __DIR__, levels: 2));
        $lifecycle = new ReflectionClass(objectOrClass: AccountLifecycle::class);
        foreach ($lifecycle->getMethods() as $method) {
            if (! $method->isPublic() || $method->isConstructor()) {
                continue;
            }
            $return = $method->getReturnType();
            self::assertInstanceOf(ReflectionNamedType::class, $return);
            $name = $return->getName();
            if (str_starts_with(haystack: $name, needle: 'InterviewTask\\Accounts\\')) {
                self::assertContains($name, $exports['Accounts'], $method->getName() . ' leaks private type');
            }
        }
        $registerReturn = $lifecycle->getMethod(name: 'register')->getReturnType();
        self::assertInstanceOf(ReflectionNamedType::class, $registerReturn);
        self::assertSame('void', $registerReturn->getName());
    }

    public function testForbiddenMutationIsDetectedForAllStaticReferenceShapes(): void
    {
        $cases   = [
            "<?php\nuse InterviewTask\\Accounts\\PasswordRejected;\n",
            "<?php\nnamespace Consumer;\nnew \\InterviewTask\\Accounts\\PasswordRejected();\n",
            "<?php\nnamespace Consumer;\nuse InterviewTask\\Accounts\\{PasswordRejected as Reject, CurrentAccount};\nnew Reject();\n",
            "<?php\nnamespace Consumer;\nclass C { public function fail(\\InterviewTask\\Accounts\\PasswordRejected \$e): void {} }\n",
            "<?php\nnamespace Consumer;\n\\InterviewTask\\Accounts\\PasswordRejected::forbidden();\n",
            "<?php\nnamespace Consumer;\n\\InterviewTask\\Accounts\\PasswordRejected::class;\n",
            "<?php\nnamespace Consumer;\nuse InterviewTask\\Accounts\\PasswordRejected as P;\nclass C { public function get(): P { throw new \\Exception(); } }\n",
        ];
        $exports = self::exports(root: dirname(path: __DIR__, levels: 2));
        foreach ($cases as $source) {
            $refs = self::referencedOwnerTypes(source: $source);
            self::assertContains(['Accounts', 'InterviewTask\Accounts\PasswordRejected'], $refs, $source);
            self::assertNotContains('InterviewTask\Accounts\PasswordRejected', $exports['Accounts']);
        }
    }
}
