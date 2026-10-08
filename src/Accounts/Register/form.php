<?php

/** @var array{csrfToken: string, username: string, error: string} $data */
/** @var callable(mixed $value): string $escape */
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Create account · Activity Tracker</title>
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

        a {
            color: #1542a5
        }</style>
</head>
<body>
<main>
    <h1>Create account</h1>
    <?php
    if ($data['error'] !== ''): ?><p class="error" role="alert"><?= $escape(value: $data['error']) ?></p><?php
    endif; ?>
    <form action="/register" method="post">
        <input type="hidden" name="csrf_token" value="<?= $escape(value: $data['csrfToken']) ?>">
        <label for="username">Username</label>
        <input id="username" name="username" autocomplete="username" value="<?= $escape(value: $data['username']) ?>" required>
        <label for="password">Password</label>
        <input id="password" type="password" name="password" autocomplete="new-password" required>
        <button type="submit">Register</button>
    </form>
    <p>Already registered? <a href="/login">Sign in</a>.</p>
</main>
</body>
</html>
