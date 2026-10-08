<?php

/** @var array{username: string, role: string, csrfToken: string} $data */
/** @var callable(mixed $value): string $escape */
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Activity Tracker</title>
    <style>body {
            font: 16px/1.5 system-ui, sans-serif;
            max-width: 640px;
            margin: 9vh auto;
            padding: 0 24px;
            color: #17202a
        }

        nav a {
            display: inline-block;
            margin-right: 18px;
            color: #1542a5
        }

        button {
            font: inherit;
            padding: 9px 16px;
            border: 1px solid #9da6b0;
            border-radius: 6px;
            cursor: pointer
        }</style>
</head>
<body>
<main>
    <h1>Activity Tracker</h1>
    <p>Signed in as <strong><?= $escape(value: $data['username']) ?></strong> (<?= $escape(value: $data['role']) ?>).</p>
    <nav aria-label="Pages">
        <a href="/page-a">Page A</a>
        <a href="/page-b">Page B</a>
        <?php
        if ($data['role'] === 'admin'): ?>
            <a href="/statistics">Statistics</a>
            <a href="/reports">Reports</a>
        <?php
        endif; ?>
    </nav>
    <p>Use Page A to try Buy a cow and Page B to download the sample file. These actions are recorded in activity
        history.</p>
    <form method="post" action="/logout">
        <input type="hidden" name="csrf_token" value="<?= $escape(value: $data['csrfToken']) ?>">
        <button type="submit">Sign out</button>
    </form>
</main>
</body>
</html>
