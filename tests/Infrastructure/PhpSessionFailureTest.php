<?php

declare(strict_types=1);

namespace InterviewTask\Tests\Infrastructure;

use InterviewTask\Infrastructure\Session\PhpSession;
use Override;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SessionHandler;

/**
 * Native session failures must not leak bearer identifiers into PHP diagnostics.
 *
 * @package InterviewTask\Tests\Infrastructure
 */
#[RunTestsInSeparateProcesses]
final class PhpSessionFailureTest extends TestCase
{
    /**
     * Reject inaccessible storage without forwarding the native warning containing its session filename.
     *
     * @return void No return value.
     */
    public function testStartFailureRedactsNativeWarningAndRestoresErrorHandler() : void
    {
        ini_set(option: 'session.use_cookies', value: '0');
        $session  = new PhpSession(savePath: sys_get_temp_dir() . '/missing-session-' . bin2hex(string: random_bytes(length: 8)));
        $warnings = [];
        $handler  = static function (int $severity, string $message) use (&$warnings) : bool {
            $warnings[] = $message;

            return true;
        };
        set_error_handler(callback: $handler);

        try {
            try {
                $session->start();
                self::fail('Unavailable session storage must fail.');
            } catch (RuntimeException $failure) {
                self::assertSame('Unable to start session.', $failure->getMessage());
                self::assertNull($failure->getPrevious());
            }

            self::assertSame([], $warnings, 'Native warnings must not reach a logging handler.');
            $restored = set_error_handler(callback: $handler);
            self::assertSame($handler, $restored);
            restore_error_handler();
        } finally {
            restore_error_handler();
        }
    }

    /**
     * Both revocation paths translate native warnings while retaining the surrounding handler.
     *
     * @return void No return value.
     */
    public function testRegenerateAndDestroyFailuresRedactNativeWarnings() : void
    {
        ini_set(option: 'session.use_cookies', value: '0');
        $storage = new class extends SessionHandler {
            /**
             * Simulate failed persistent removal without disclosing a session identifier.
             *
             * @param string $id Native session identifier.
             *
             * @return bool False to exercise the native warning path.
             */
            #[Override]
            public function destroy(string $id) : bool
            {
                return false;
            }
        };
        self::assertTrue(session_set_save_handler($storage, true));
        $session = new PhpSession();
        $session->start();
        $warnings = [];
        $handler  = static function (int $severity, string $message) use (&$warnings) : bool {
            $warnings[] = $message;

            return true;
        };
        set_error_handler(callback: $handler);

        try {
            foreach (['regenerate' => 'Unable to regenerate session id.', 'destroy' => 'Unable to destroy session.'] as $operation => $message) {
                $session->start();
                try {
                    $session->{$operation}();
                    self::fail('Failed native session removal must fail.');
                } catch (RuntimeException $failure) {
                    self::assertSame($message, $failure->getMessage());
                    self::assertNull($failure->getPrevious());
                }
                self::assertSame([], $warnings);
                $restored = set_error_handler(callback: $handler);
                self::assertSame($handler, $restored);
                restore_error_handler();
            }
        } finally {
            restore_error_handler();
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }
        }
    }

    /**
     * Preserve a storage callback's exception object and restore error handling after it escapes.
     *
     * @return void No return value.
     */
    public function testThrownStorageFailureRetainsOriginalException() : void
    {
        ini_set(option: 'session.use_cookies', value: '0');
        $original = new RuntimeException(message: 'Storage callback failed.');
        $storage  = new class($original) extends SessionHandler {
            /**
             * @param RuntimeException $failure Original failure used to prove exception identity.
             */
            public function __construct(private RuntimeException $failure) {}

            /**
             * @param string $id Native session identifier.
             *
             * @return bool Never returns because the injected storage failure escapes.
             * @throws RuntimeException The original injected failure.
             */
            #[Override]
            public function destroy(string $id) : bool
            {
                throw $this->failure;
            }
        };
        self::assertTrue(session_set_save_handler($storage, true));
        $session = new PhpSession();
        $session->start();
        $handler = static fn (int $severity, string $message) : bool => true;
        set_error_handler(callback: $handler);

        try {
            try {
                $session->destroy();
                self::fail('The storage failure must escape.');
            } catch (RuntimeException $failure) {
                self::assertSame($original, $failure);
                self::assertSame([], $_SESSION);
            }
            $restored = set_error_handler(callback: $handler);
            self::assertSame($handler, $restored);
            restore_error_handler();
        } finally {
            restore_error_handler();
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }
        }
    }

    /**
     * Persist immediately, retain usable state, and revoke the same durable session after an explicit close.
     *
     * @return void No return value.
     */
    public function testPersistWritesNativeStateAndClosedSessionCanBeDestroyed() : void
    {
        ini_set(option: 'session.use_cookies', value: '0');
        $directory = sys_get_temp_dir() . '/fast-persist-' . bin2hex(string: random_bytes(length: 8));
        self::assertTrue(mkdir(directory: $directory, permissions: 0700));
        $session = new PhpSession(savePath: $directory);

        try {
            $session->put(key: 'account_id', value: 42);
            $id = session_id();
            $session->persist();

            self::assertSame(PHP_SESSION_ACTIVE, session_status());
            self::assertSame($id, session_id());
            self::assertSame(42, $session->get(key: 'account_id'));
            $bytes = file_get_contents(filename: $directory . '/sess_' . $id);
            self::assertIsString($bytes);
            self::assertNotSame('', $bytes, 'Session bytes must exist before a success is returned.');

            self::assertTrue(session_write_close());
            $session->destroy();
            self::assertSame([], $_SESSION);
            self::assertSame('', session_id());
            self::assertFileDoesNotExist($directory . '/sess_' . $id);
        } finally {
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_destroy();
            }
            foreach (glob(pattern: $directory . '/sess_*') ?: [] as $file) {
                unlink(filename: $file);
            }
            rmdir(directory: $directory);
        }
    }

    /**
     * Native write warnings must fail persistence even when PHP reports a successful close.
     *
     * @return void No return value.
     */
    public function testPersistRejectsNativeWriteFailureWithoutForwardingWarning() : void
    {
        ini_set(option: 'session.use_cookies', value: '0');
        $storage = new class extends SessionHandler {
            /**
             * @param string $id   Native session identifier.
             * @param string $data Serialized server-session state.
             *
             * @return bool False to produce the native failed-write warning.
             */
            #[Override]
            public function write(string $id, string $data) : bool
            {
                return false;
            }
        };
        self::assertTrue(session_set_save_handler($storage, true));
        $session = new PhpSession();
        $session->put(key: 'account_id', value: 42);
        $warnings = [];
        $handler  = static function (int $severity, string $message) use (&$warnings) : bool {
            $warnings[] = $message;

            return true;
        };
        set_error_handler(callback: $handler);

        try {
            try {
                $session->persist();
                self::fail('Failed session persistence must fail before accepting login.');
            } catch (RuntimeException $failure) {
                self::assertSame('Unable to persist session.', $failure->getMessage());
                self::assertNull($failure->getPrevious());
            }

            self::assertSame([], $warnings);
            $restored = set_error_handler(callback: $handler);
            self::assertSame($handler, $restored);
            restore_error_handler();
            $session->destroy();
            self::assertSame([], $_SESSION);
        } finally {
            restore_error_handler();
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_destroy();
            }
        }
    }

    /**
     * Even unavailable storage during reopening must clear the retained in-memory identity.
     *
     * @return void No return value.
     */
    public function testDestroyClearsMemoryWhenReopeningClosedStorageFails() : void
    {
        ini_set(option: 'session.use_cookies', value: '0');
        $storage = new class extends SessionHandler {
            /**
             * @var bool Whether the next storage read must fail.
             */
            public bool $rejectRead = false;

            /**
             * @param string $id Native session identifier.
             *
             * @return string|false Stored session data when the storage is available.
             * @throws RuntimeException When the injected storage outage begins.
             */
            #[Override]
            public function read(string $id) : string|false
            {
                if ($this->rejectRead) {
                    throw new RuntimeException(message: 'Session storage is unavailable.');
                }

                return parent::read(id: $id);
            }
        };
        self::assertTrue(session_set_save_handler($storage, true));
        $session = new PhpSession();
        $session->put(key: 'account_id', value: 42);
        self::assertTrue(session_write_close());
        $storage->rejectRead = true;

        try {
            try {
                $session->destroy();
                self::fail('Failed persistent revocation must remain visible.');
            } catch (RuntimeException $failure) {
                self::assertSame('Session storage is unavailable.', $failure->getMessage());
                self::assertSame([], $_SESSION);
            }
        } finally {
            $storage->rejectRead = false;
            $session->destroy();
        }
    }
}
