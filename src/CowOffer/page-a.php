<?php

/**
 * Page A buy-cow interaction view.
 *
 * Render either the CSRF-protected button or the transient thankYou result, never persisted purchase state.
 *
 * @package InterviewTask\CowOffer
 */
/** @var array{csrfToken: string, bought: bool} $data */
/** @var callable(mixed $value): string $escape */
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Page A · Activity Tracker</title>
    <style>body {
            font: 16px/1.5 system-ui, sans-serif;
            max-width: 640px;
            margin: 8vh auto;
            padding: 0 24px;
            color: #17202a
        }

        a {
            color: #1542a5
        }

        button {
            font: inherit;
            padding: 12px 20px;
            border: 0;
            border-radius: 6px;
            background: #1542a5;
            color: white;
            cursor: pointer
        }

        .success {
            font-size: 1.2rem;
            color: #17603e
        }</style>
</head>
<body>
<main>
    <p><a href="/">← Home</a></p>
    <h1>Page A</h1>
    <?php
    if ($data['bought']): ?>
        <p class="success" role="status">thankYou</p>
    <?php else: ?>
        <p>Would you like to buy a cow?</p>
        <form method="post" action="/buy-cow"><input type="hidden" name="csrf_token"
                                                     value="<?= $escape(value: $data['csrfToken']) ?>">
            <button type="submit">Buy a cow</button>
        </form>
    <?php
    endif; ?>
</main>
</body>
</html>
