<?php

declare(strict_types=1);

namespace InterviewTask\Infrastructure\Session;

use RuntimeException;

final readonly class PhpSession
{
    public function __construct(
        private string $name = 'fast_task',
        private bool   $secureCookie = false,
    ) {}

    public function get(string $key): mixed
    {
        $this->start();

        return $_SESSION[$key] ?? null;
    }

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

    public function put(string $key, mixed $value): void
    {
        $this->start();
        $_SESSION[$key] = $value;
    }

    public function remove(string $key): void
    {
        $this->start();
        unset($_SESSION[$key]);
    }

    public function regenerate(): void
    {
        $this->start();

        if (! session_regenerate_id(delete_old_session: true)) {
            throw new RuntimeException(message: 'Unable to regenerate session id.');
        }
    }

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
