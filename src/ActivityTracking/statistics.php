<?php

declare(strict_types=1);

// $data and $escape are injected by Renderer::render() when this trusted template is required.
/** @noinspection PhpUndefinedVariableInspection */

/**
 * Administrator activity-history table and pagination view.
 *
 * Render validated activity rows, filter values, errors, and navigation without building independent statistics.
 *
 * @package InterviewTask\ActivityTracking
 */
/** @var array{events: list<array{id:int,actor_id:int,actor_label:string,action:string,target: string|null,occurred_at:string}>,date:string,user:string,action:string,error:string,page:int,hasNext:bool,cursorMode:bool,nextCursor:string|null,previousCursor:string|null} $data */
/** @var callable(mixed $value): string $escape */
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Statistics · Activity Tracker</title>
    <style>body {
            font: 16px/1.5 system-ui, sans-serif;
            max-width: 1000px;
            margin: 4vh auto;
            padding: 0 24px;
            color: #17202a
        }

        a {
            color: #1542a5
        }

        table {
            width: 100%;
            border-collapse: collapse
        }

        td, th {
            padding: 9px;
            border-bottom: 1px solid #d8dee4;
            text-align: left
        }

        form {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            align-items: end
        }

        label {
            display: block
        }

        input, select, button {
            font: inherit;
            padding: 8px
        }

        button {
            cursor: pointer
        }

        .error {
            color: #9c1737
        }

        .table-wrap {
            overflow-x: auto
        }</style>
</head>
<body>
<main><p><a href="/">← Home</a> · <a href="/reports">Reports</a></p>
    <h1>Activity statistics</h1>
    <form method="get" action="/statistics">
        <div><label for="date">Date (UTC)</label><input id="date" name="date" placeholder="YYYY-MM-DD"
                                                        value="<?= $escape(value: $data['date']) ?>"></div>
        <div><label for="user">User</label><input id="user" name="user" value="<?= $escape(value: $data['user']) ?>"></div>
        <div><label for="action">Action</label><select id="action" name="action">
                <option value="">All actions</option>
                <?php
                foreach (['registration', 'login', 'logout', 'view-page', 'button-click'] as $action): ?>
                    <option value="<?= $escape(value: $action) ?>" <?= $action === $data['action'] ? 'selected' : '' ?>><?= $escape(value: $action) ?></option>
                <?php
                endforeach; ?></select></div>
        <button type="submit">Filter</button>
        <a href="/statistics">Clear</a>
    </form>
    <?php
    if ($data['error'] !== ''): ?><p role="alert" class="error"><?= $escape(value: $data['error']) ?></p><?php
    endif; ?>
    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>UTC time</th>
                <th>User</th>
                <th>Action</th>
                <th>Target</th>
            </tr>
            </thead>
            <tbody>
            <?php
            foreach ($data['events'] as $event): ?>
                <tr>
                    <td><?= $escape(value: $event['occurred_at']) ?></td>
                    <td><?= $escape(value: $event['actor_label']) ?></td>
                    <td><?= $escape(value: $event['action']) ?></td>
                    <td><?= $escape(value: $event['target']) ?></td>
                </tr>
            <?php
            endforeach; ?>
            <?php
            if ($data['events'] === []): ?>
                <tr>
                    <td colspan="4">No matching activity events.</td>
                </tr><?php
            endif; ?>
            </tbody>
        </table>
    </div>
    <?php
    $filters = array_filter(array: ['date' => $data['date'], 'user' => $data['user'], 'action' => $data['action']], callback: static fn(string $value): bool => $value !== '');
?>
    <nav aria-label="Activity pages">
        <?php if ($data['cursorMode']): ?>
            <?php if ($data['previousCursor'] !== null): ?>
                <a href="/statistics?<?= $escape(value: http_build_query(data: $filters + ['cursor' => $data['previousCursor']])) ?>">← Previous</a>
            <?php endif; ?>
            <span>Activity history</span>
            <?php if ($data['nextCursor'] !== null): ?>
                <a href="/statistics?<?= $escape(value: http_build_query(data: $filters + ['cursor' => $data['nextCursor']])) ?>">Next →</a>
            <?php endif; ?>
        <?php else: ?>
            <?php if ($data['page'] > 1): ?>
                <a href="/statistics?<?= $escape(value: http_build_query(data: $filters + ['page' => $data['page'] - 1])) ?>">← Previous</a>
            <?php endif; ?>
            <span>Page <?= $escape(value: $data['page']) ?></span>
            <?php if ($data['hasNext']): ?>
                <a href="/statistics?<?= $escape(value: http_build_query(data: $filters + ['page' => $data['page'] + 1])) ?>">Next →</a>
            <?php endif; ?>
        <?php endif; ?>
    </nav>
</main>
</body>
</html>
