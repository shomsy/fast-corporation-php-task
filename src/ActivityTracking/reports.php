<?php
/** @var array{daily: list<array{date:string,page_a_views:int,page_b_views:int,buy_cow_clicks:int,download_clicks:int}>,from:string,to:string,error:string} $data */
/** @var callable(mixed): string $escape */
$daily  = $data['daily'];
$series = [
    'page_a_views'    => ['label' => 'Page A views', 'color' => '#1d4ed8'],
    'page_b_views'    => ['label' => 'Page B views', 'color' => '#16a34a'],
    'buy_cow_clicks'  => ['label' => 'Buy a cow clicks', 'color' => '#dc2626'],
    'download_clicks' => ['label' => 'Download clicks', 'color' => '#9333ea'],
];
$max    = 1;
foreach ($daily as $day) {
    foreach ($series as $key => $_) {
        $max = max($max, (int) $day[$key]);
    }
}
$count = count($daily);
$xFor  = static fn (int $i) : float => 75 + ($count < 2 ? 0 : $i * 830 / ($count - 1));
$yFor  = static fn (int $value) : float => 265 - $value * 215 / $max;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reports · Activity Tracker</title>
    <style>body {
            font: 16px/1.5 system-ui, sans-serif;
            max-width: 1080px;
            margin: 4vh auto;
            padding: 0 24px;
            color: #17202a
        }

        a {
            color: #1542a5
        }

        svg {
            width: 100%;
            height: auto;
            border: 1px solid #d8dee4;
            border-radius: 8px
        }

        table {
            width: 100%;
            border-collapse: collapse
        }

        td, th {
            padding: 10px;
            border-bottom: 1px solid #d8dee4;
            text-align: right
        }

        th:first-child, td:first-child {
            text-align: left
        }

        .legend {
            display: flex;
            gap: 16px;
            flex-wrap: wrap
        }

        .table-wrap {
            overflow-x: auto
        }</style>
</head>
<body>
<main><p><a href="/">← Home</a> · <a href="/statistics">Statistics</a></p>
    <h1>Daily activity reports</h1>
    <p>All dates are UTC. The chart and table use the same daily dataset (up
        to <?= \InterviewTask\ActivityTracking\DailyReports::MAX_DAYS ?> recent active dates).</p>
    <form method="get" action="/reports">
        <label>From (UTC) <input name="from" placeholder="YYYY-MM-DD" value="<?= $escape($data['from']) ?>"></label>
        <label>To (UTC) <input name="to" placeholder="YYYY-MM-DD" value="<?= $escape($data['to']) ?>"></label>
        <button type="submit">Filter</button>
        <a href="/reports">Clear</a>
    </form>
    <?php
    if ($data['error'] !== ''): ?><p role="alert"><?= $escape($data['error']) ?></p><?php
    endif; ?>
    <?php
    if ($daily === []): ?>
        <p>No reportable activity yet.</p>
    <?php
    else: ?>
        <div class="legend">
            <?php
            foreach ($series as $s): ?><span style="color:<?= $escape($s['color']) ?>">
                ● <?= $escape($s['label']) ?></span><?php
            endforeach; ?>
        </div>
        <svg viewBox="0 0 960 330" role="img" aria-label="Daily Page A, Page B, Buy Cow and Download event counts">
            <line x1="75" y1="50" x2="75" y2="265" stroke="#64748b"/>
            <line x1="75" y1="265" x2="905" y2="265" stroke="#64748b"/>
            <?php
            foreach ([0, 1, 2, 3, 4] as $i): $val = (int) round($max * $i / 4);
                $y = $yFor($val); ?>
                <line x1="75" y1="<?= $escape($y) ?>" x2="905" y2="<?= $escape($y) ?>" stroke="#e2e8f0"/>
                <text x="65" y="<?= $escape($y + 4) ?>" text-anchor="end" font-size="12"
                      fill="#475569"><?= $escape($val) ?></text>
            <?php
            endforeach; ?>
            <?php
            foreach ($series as $key => $s): ?>
                <polyline fill="none" stroke="<?= $escape($s['color']) ?>" stroke-width="3" points="<?php
                foreach ($daily as $i => $day): ?><?= $escape(round($xFor($i), 2) . ',' . round($yFor((int) $day[$key]), 2)) ?> <?php
                endforeach; ?>"/>
                <?php
                foreach ($daily as $i => $day): ?>
                    <circle cx="<?= $escape($xFor($i)) ?>" cy="<?= $escape($yFor((int) $day[$key])) ?>" r="4"
                            fill="<?= $escape($s['color']) ?>">
                    <title><?= $escape($s['label'] . ': ' . $day[$key] . ' on ' . $day['date']) ?></title></circle><?php
                endforeach; ?>
            <?php
            endforeach; ?>
            <?php
            foreach ($daily as $i => $day): ?>
                <text x="<?= $escape($xFor($i)) ?>" y="295" font-size="12" text-anchor="middle"
                      fill="#475569"><?= $escape($day['date']) ?></text><?php
            endforeach; ?>
        </svg>
    <?php
    endif; ?>
    <h2>Daily totals</h2>
    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>UTC date</th>
                <th>Page A views</th>
                <th>Page B views</th>
                <th>Buy a cow clicks</th>
                <th>Download clicks</th>
            </tr>
            </thead>
            <tbody>
            <?php
            foreach ($daily as $day): ?>
                <tr>
                <td><?= $escape($day['date']) ?></td>
                <td><?= $escape($day['page_a_views']) ?></td>
                <td><?= $escape($day['page_b_views']) ?></td>
                <td><?= $escape($day['buy_cow_clicks']) ?></td>
                <td><?= $escape($day['download_clicks']) ?></td></tr><?php
            endforeach; ?>
            <?php
            if ($daily === []): ?>
                <tr>
                    <td colspan="5">No reportable activity yet.</td>
                </tr><?php
            endif; ?>
            </tbody>
        </table>
    </div>
</main>
</body>
</html>
