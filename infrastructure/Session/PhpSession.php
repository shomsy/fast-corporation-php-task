<?php

declare(strict_types=1);

namespace InterviewTask\Infrastructure\Session;

use RuntimeException;

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
     * @param string $name Configured session name or lookup name.
     * @param bool $secureCookie Whether PHP cookies require HTTPS.
     */
    public function __construct(
        private string $name = 'fast_task',
        private bool   $secureCookie = false,
    ) {}

    /**
     * Read a server-session entry after safely initializing the session.
     *
     * @param string $key Name of a session or HTTP input value.
     * @return mixed Result described by the native PHP return type.
     */
    public function get(string $key): mixed
    {
        $this->start();

        return $_SESSION[$key] ?? null;
    }

    /**
     * Start a strict-ID PHP session with HttpOnly and SameSite cookie parameters.
     *
     * @return void No return value.
     * @throws \RuntimeException When a required external resource or stored value is invalid.
     */
    public function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        if (ini_set(option: 'session.use_strict_mode', value: '1') === false) {
            throw new RuntimeException(message: 'Unable to enforce strict session mode.');
        }
        session_name(name: $this->name);

        if ((bool) ini_get(option: 'session.use_cookies')) {
            session_set_cookie_params(lifetime_or_options: [
                'httponly' => true,
                'secure'   => $this->secureCookie,
                'samesite' => 'Lax',
                'path'     => '/',
            ]);
        }

        if (! session_start()) {
            throw new RuntimeException(message: 'Unable to start session.');
        }
    }

    /**
     * Store a trusted server-side session value.
     *
     * @param string $key Name of a session or HTTP input value.
     * @param mixed $value Value to store, format, or validate, as appropriate for this operation.
     * @return void No return value.
     */
    public function put(string $key, mixed $value): void
    {
        $this->start();
        $_SESSION[$key] = $value;
    }

    /**
     * Delete a named session entry.
     *
     * @param string $key Name of a session or HTTP input value.
     * @return void No return value.
     */
    public function remove(string $key): void
    {
        $this->start();
        unset($_SESSION[$key]);
    }

    /**
     * Rotate the session identifier and invalidate the old session.
     *
     * @return void No return value.
     * @throws \RuntimeException When a required external resource or stored value is invalid.
     */
    public function regenerate(): void
    {
        $this->start();

        if (! session_regenerate_id(delete_old_session: true)) {
            throw new RuntimeException(message: 'Unable to regenerate session id.');
        }
    }

    /**
     * Clear server state, expire the cookie and terminate the PHP session.
     *
     * @return void No return value.
     * @throws \RuntimeException When a required external resource or stored value is invalid.
     */
    public function destroy(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            $_SESSION = [];

            return;
        }

        $_SESSION = [];

        if ((bool) ini_get(option: 'session.use_cookies') && ! headers_sent()) {
            $parameters = session_get_cookie_params();
            setcookie(name: $this->name, value: '', expires_or_options: [
                'expires'  => time() - 3600,
                'path'     => $parameters['path'],
                'domain'   => $parameters['domain'],
                'secure'   => $parameters['secure'],
                'httponly' => $parameters['httponly'],
                'samesite' => $parameters['samesite'],
            ]);
        }

        if (! session_destroy()) {
            throw new RuntimeException(message: 'Unable to destroy session.');
        }

        session_id(id: '');
    }
}
