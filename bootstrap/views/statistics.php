<?php
/** @var array{events: list<array{id:int,actor_id:int,actor_label:string,action:string,target:?string,occurred_at:string}>,date:string,user:string,action:string,error:string} $data */
/** @var callable(mixed): string $escape */
?>
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Statistics · Activity Tracker</title>
<style>body{font:16px/1.5 system-ui,sans-serif;max-width:1000px;margin:4vh auto;padding:0 24px;color:#17202a}a{color:#1542a5}table{width:100%;border-collapse:collapse}td,th{padding:9px;border-bottom:1px solid #d8dee4;text-align:left}form{display:flex;gap:12px;flex-wrap:wrap;align-items:end}label{display:block}input,select,button{font:inherit;padding:8px}button{cursor:pointer}.error{color:#9c1737}.table-wrap{overflow-x:auto}</style>
</head>
<body><main><p><a href="/">← Home</a> · <a href="/reports">Reports</a></p>
<h1>Activity statistics</h1>
<form method="get" action="/statistics">
<div><label for="date">Date (UTC)</label><input id="date" name="date" placeholder="YYYY-MM-DD" value="<?= $escape($data['date']) ?>"></div>
<div><label for="user">User</label><input id="user" name="user" value="<?= $escape($data['user']) ?>"></div>
<div><label for="action">Action</label><select id="action" name="action">
<option value="">All actions</option>
<?php foreach (['registration','login','logout','view-page','button-click'] as $action): ?>
<option value="<?= $escape($action) ?>" <?= $action === $data['action'] ? 'selected' : '' ?>><?= $escape($action) ?></option>
<?php endforeach; ?></select></div>
<button type="submit">Filter</button> <a href="/statistics">Clear</a>
</form>
<?php if ($data['error'] !== ''): ?><p role="alert" class="error"><?= $escape($data['error']) ?></p><?php endif; ?>
<div class="table-wrap"><table><thead><tr><th>UTC time</th><th>User</th><th>Action</th><th>Target</th></tr></thead><tbody>
<?php foreach ($data['events'] as $event): ?>
<tr><td><?= $escape($event['occurred_at']) ?></td><td><?= $escape($event['actor_label']) ?></td><td><?= $escape($event['action']) ?></td><td><?= $escape($event['target']) ?></td></tr>
<?php endforeach; ?>
<?php if ($data['events'] === []): ?><tr><td colspan="4">No matching activity events.</td></tr><?php endif; ?>
</tbody></table></div></main></body></html>
