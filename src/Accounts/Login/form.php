<?php

/**
 * Server-rendered sign-in form.
 *
 * The caller supplies a session CSRF token, optional registration notice, and non-revealing login error.
 *
 * @package InterviewTask\Accounts\Login
 */

/** @var array{csrfToken: string, error: string, registered: bool} $data */
/** @var callable(mixed $value): string $escape */
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in · Activity Tracker</title>
    <style>body {
            font: 16px/1.5 system-ui, sans-serif;
            max-width: 480px;
            margin: 9vh auto;
            padding: 0 24px;
            color: #17202a
        }

        input {
            box-sizing: border-box;
            display: block;
            width: 100%;
            font: inherit;
            padding: 10px;
            margin: 6px 0 18px;
            border: 1px solid #9da6b0;
            border-radius: 6px
        }

        button {
            font: inherit;
            padding: 10px 18px;
            background: #1542a5;
            color: white;
            border: 0;
            border-radius: 6px;
            cursor: pointer
        }

        .error {
            color: #9c1737
        }

        .success {
            color: #17603e
        }

        a {
            color: #1542a5
        }</style>
</head>
<body>
<main>
    <h1>Sign in</h1>
    <?php
    if ($data['registered']): ?><p class="success">Account created. You can now sign in.</p><?php
    endif; ?>
    <?php
    if ($data['error'] !== ''): ?><p class="error" role="alert"><?= $escape(value: $data['error']) ?></p><?php
    endif; ?>
    <form action="/login" method="post">
        <input type="hidden" name="csrf_token" value="<?= $escape(value: $data['csrfToken']) ?>">
        <label for="username">Username</label>
        <input id="username" name="username" autocomplete="username" required>
        <label for="password">Password</label>
        <input id="password" type="password" name="password" autocomplete="current-password" required>
        <button type="submit">Sign in</button>
    </form>
    <p>New here? <a href="/register">Create an account</a>.</p>
</main>
</body>
</html>
