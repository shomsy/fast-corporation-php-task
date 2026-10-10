<?php

declare(strict_types=1);

namespace InterviewTask\Infrastructure\Session;

use RuntimeException;
use SensitiveParameter;

/**
 * Adapt native PHP sessions with strict IDs and hardened browser-cookie settings.
 *
 * @package InterviewTask\Infrastructure\Session
 */
final readonly class PhpSession
{
    /**
     * Configure a named PHP session and whether its cookie requires HTTPS.
     *
     * @param string      $name         Configured session name or lookup name.
     * @param bool        $secureCookie Whether PHP cookies require HTTPS.
     * @param string|null $savePath     Preprovisioned private directory for native session files.
     */
    public function __construct(
        private string      $name = 'fast_task',
        private bool        $secureCookie = false,
        private string|null $savePath = null,
    ) {}

    /**
     * Read a server-session entry after safely initializing the session.
     *
     * @param string $key Name of a session or HTTP input value.
     *
     * @return mixed Result described by the native PHP return type.
     * @throws RuntimeException When session initialization fails.
     */
    public function get(string $key) : mixed
    {
        $this->start();

        return $_SESSION[$key] ?? null;
    }

    /**
     * Start a strict-ID PHP session with HttpOnly and SameSite cookie parameters.
     *
     * @return void No return value.
     * @throws RuntimeException When a required external resource or stored value is invalid.
     */
    public function start() : void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        if (ini_set(option: 'session.use_strict_mode', value: '1') === false) {
            throw new RuntimeException(message: 'Unable to enforce strict session mode.');
        }
        // Native file GC must never precede the server's 12-hour absolute session expiry.
        if (ini_set(option: 'session.gc_maxlifetime', value: '43200') === false) {
            throw new RuntimeException(message: 'Unable to enforce session retention.');
        }

        if ($this->savePath !== null && session_save_path(path: $this->savePath) === false) {
            throw new RuntimeException(message: 'Unable to configure private session storage.');
        }

        session_name(name: $this->name);

        if (ini_get(option: 'session.use_cookies')) {
            session_set_cookie_params([
                                          'httponly' => true,
                                          'secure'   => $this->secureCookie,
                                          'samesite' => 'Lax',
                                          'path'     => '/',
                                      ]);
        }

        $this->performNativeOperation(operation: static fn () : bool => session_start(), failureMessage: 'Unable to start session.');
    }

    /**
     * Store a trusted server-side session value.
     *
     * @param string $key   Name of a session or HTTP input value.
     * @param mixed  $value Value to store, format, or validate, as appropriate for this operation.
     *
     * @return void No return value.
     * @throws RuntimeException When session initialization fails.
     */
    public function put(string $key, mixed $value) : void
    {
        $this->start();
        $_SESSION[$key] = $value;
    }

    /**
     * Delete a named session entry.
     *
     * @param string $key Name of a session or HTTP input value.
     *
     * @return void No return value.
     * @throws RuntimeException When session initialization fails.
     */
    public function remove(string $key) : void
    {
        $this->start();
        unset($_SESSION[$key]);
    }

    /**
     * Persist current state before accepting an outcome, then reopen its native session.
     * This does not provide atomicity with another resource such as the activity database.
     *
     * @return void No return value; the successfully persisted session remains active.
     * @throws RuntimeException When native persistence or reopening fails.
     */
    public function persist() : void
    {
        $this->start();
        $this->performNativeOperation(operation: static fn () : bool => session_write_close(), failureMessage: 'Unable to persist session.');
        $this->start();
    }

    /**
     * Rotate the session identifier and invalidate the old session.
     *
     * @return void No return value.
     * @throws RuntimeException When a required external resource or stored value is invalid.
     */
    public function regenerate() : void
    {
        $this->start();

        $this->performNativeOperation(
            operation     : static fn () : bool => session_regenerate_id(delete_old_session: true),
            failureMessage: 'Unable to regenerate session id.',
        );
    }

    /**
     * Clear server state, expire the cookie and terminate the PHP session.
     *
     * @return void No return value.
     * @throws RuntimeException When a required external resource or stored value is invalid.
     */
    public function destroy() : void
    {
        if (session_status() !== PHP_SESSION_ACTIVE && session_id() === '') {
            $_SESSION = [];

            return;
        }

        try {
            // A failed or explicit close retains the identifier; reopen it to revoke durable state.
            $this->start();
        } finally {
            // Neither a failed reopen nor persistent cleanup may retain an in-memory identity.
            $_SESSION = [];

            if (ini_get(option: 'session.use_cookies') && ! headers_sent()) {
                $parameters = session_get_cookie_params();
                setcookie($this->name, '', [
                    'expires'  => time() - 3600,
                    'path'     => $parameters['path'],
                    'domain'   => $parameters['domain'],
                    'secure'   => $parameters['secure'],
                    'httponly' => $parameters['httponly'],
                    'samesite' => $parameters['samesite'],
                ]);
            }
        }

        $this->performNativeOperation(operation: static fn () : bool => session_destroy(), failureMessage: 'Unable to destroy session.');

        session_id(id: '');
    }

    /**
     * Translate native session warnings before PHP can log bearer identifiers or storage paths.
     *
     * @param callable(): bool $operation      One native session operation.
     * @param string           $failureMessage Fixed diagnostic safe for the outer error boundary.
     *
     * @return void No return value.
     * @throws RuntimeException When the native operation warns or returns false.
     */
    private function performNativeOperation(callable $operation, string $failureMessage) : void
    {
        set_error_handler(
            callback    : static function (int $severity, #[SensitiveParameter] string $message) use ($failureMessage) : never {
                throw new RuntimeException(message: $failureMessage);
            },
            error_levels: E_WARNING,
        );

        try {
            if (! $operation()) {
                throw new RuntimeException(message: $failureMessage);
            }
        } finally {
            restore_error_handler();
        }
    }
}
