<?php

/**
 * Page B fixed-sample download form.
 *
 * Submit a CSRF-protected request without exposing the server artifact path.
 *
 * @package InterviewTask\Downloads
 */
/** @var array{csrfToken: string} $data */
/** @var callable(mixed $value): string $escape */
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Page B · Activity Tracker</title>
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
        }</style>
</head>
<body>
<main><p><a href="/">← Home</a></p>
    <h1>Page B</h1>
    <p>Download a fixed, harmless sample executable.</p>
    <form method="post" action="/download"><input type="hidden" name="csrf_token"
                                                  value="<?= $escape(value: $data['csrfToken']) ?>">
        <button type="submit">Download</button>
    </form>
</main>
</body>
</html>
