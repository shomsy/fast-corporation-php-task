<?php

use InterviewTask\ActivityTracking\DailyReports;

/**
 * Administrator daily activity report chart and table view.
 *
 * Use exactly one DailyReports dataset for the SVG series and tabular metric totals.
 *
 * @package InterviewTask\ActivityTracking
 */
/** @var array{daily: list<array{date:string,page_a_views:int,page_b_views:int,buy_cow_clicks:int,download_clicks:int}>,from:string,to:string,error:string} $data */
/** @var callable(mixed $value): string $escape */
$daily  = $data['daily'];
$series = [
    'page_a_views'    => ['label' => 'Page A views', 'color' => '#1d4ed8', 'dash' => ''],
    'page_b_views'    => ['label' => 'Page B views', 'color' => '#16a34a', 'dash' => '8 4'],
    'buy_cow_clicks'  => ['label' => 'Buy a cow clicks', 'color' => '#dc2626', 'dash' => '3 4'],
    'download_clicks' => ['label' => 'Download clicks', 'color' => '#9333ea', 'dash' => '9 3 2 3'],
];
$max    = 1;
foreach ($daily as $day) {
    foreach ($series as $key => $_) {
        $max = max($max, (int) $day[$key]);
    }
}
$count = count(value: $daily);
$dayEpoch = static fn(string $date): int => (new DateTimeImmutable(datetime: $date, timezone: new DateTimeZone(timezone: 'UTC')))->getTimestamp();
$firstEpoch = $count === 0 ? 0 : $dayEpoch(date: $daily[0]['date']);
$lastEpoch  = $count === 0 ? 0 : $dayEpoch(date: $daily[$count - 1]['date']);
$spanSeconds = max(86400, $lastEpoch - $firstEpoch);
$xFor = static fn(string $date): float => round(num: 75 + ($dayEpoch(date: $date) - $firstEpoch) * 830 / $spanSeconds, precision: 2);
$yFor = static fn(int $value): float => 265 - $value * 215 / $max;
$ticks = range(start: 0, end: min(4, $max));
$ticks = array_map(callback: static fn(int $i): int => (int) round(num: $max * $i / max(1, count(value: $ticks) - 1)), array: $ticks);

// Missing dates are genuine zero-event gaps. Never connect nonadjacent active days.
$segmentsBySeries = [];
foreach ($series as $key => $_) {
    $segments = [];
    $current = [];
    $previousEpoch = null;
    foreach ($daily as $day) {
        $epoch = $dayEpoch(date: $day['date']);
        if ($previousEpoch !== null && $epoch - $previousEpoch > 86400) {
            if ($current !== []) {
                $segments[] = $current;
            }
            $current = [];
        }
        $current[] = $xFor(date: $day['date']) . ',' . round(num: $yFor(value: (int) $day[$key]), precision: 2);
        $previousEpoch = $epoch;
    }
    if ($current !== []) {
        $segments[] = $current;
    }
    $segmentsBySeries[$key] = $segments;
}

$axisLabels = [];
$previousLabelX = -200.0;
foreach ($daily as $index => $day) {
    $x = $xFor(date: $day['date']);
    if ($index === 0 || $index === $count - 1 || ($x - $previousLabelX >= 115 && $x < 795)) {
        $axisLabels[] = $day;
        $previousLabelX = $x;
    }
}
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
        to <?= DailyReports::MAX_DAYS ?> recent active dates).</p>
    <form method="get" action="/reports">
        <label>From (UTC) <input name="from" placeholder="YYYY-MM-DD" value="<?= $escape(value: $data['from']) ?>"></label>
        <label>To (UTC) <input name="to" placeholder="YYYY-MM-DD" value="<?= $escape(value: $data['to']) ?>"></label>
        <button type="submit">Filter</button>
        <a href="/reports">Clear</a>
    </form>
    <?php
    if ($data['error'] !== ''): ?><p role="alert"><?= $escape(value: $data['error']) ?></p><?php
    endif; ?>
    <?php
    if ($daily === []): ?>
        <p>No reportable activity yet.</p>
    <?php else: ?>
        <p id="report-calendar-note">Missing UTC dates have zero recorded activity and appear as gaps in the chart; the table lists only active dates.</p>
        <div class="legend">
            <?php
            foreach ($series as $s): ?><span style="color:<?= $escape(value: $s['color']) ?>">
                ● <?= $escape(value: $s['label']) ?><?= $s['dash'] === '' ? ' (solid)' : ' (patterned)' ?></span><?php
            endforeach; ?>
        </div>
        <svg viewBox="0 0 960 330" role="img" aria-labelledby="activity-chart-title activity-chart-description">
            <title id="activity-chart-title">Daily Page A, Page B, Buy Cow and Download event counts</title>
            <desc id="activity-chart-description">The UTC date axis uses actual calendar spacing. Missing UTC dates represent zero tracked events and break the lines. Each point has a keyboard-readable count and date. Exact active-day totals follow in the table.</desc>
            <line x1="75" y1="50" x2="75" y2="265" stroke="#64748b"/>
            <line x1="75" y1="265" x2="905" y2="265" stroke="#64748b"/>
            <?php
            foreach ($ticks as $val): $y = $yFor(value: $val); ?>
                <line x1="75" y1="<?= $escape(value: $y) ?>" x2="905" y2="<?= $escape(value: $y) ?>" stroke="#e2e8f0"/>
                <text x="65" y="<?= $escape(value: $y + 4) ?>" text-anchor="end" font-size="12"
                      fill="#475569"><?= $escape(value: $val) ?></text>
            <?php
            endforeach; ?>
            <?php
            foreach ($series as $key => $s): ?>
                <?php foreach ($segmentsBySeries[$key] as $segment): ?>
                    <polyline fill="none" stroke="<?= $escape(value: $s['color']) ?>" stroke-width="3"
                              stroke-dasharray="<?= $escape(value: $s['dash']) ?>"
                              points="<?= $escape(value: implode(separator: ' ', array: $segment)) ?>"/>
                <?php endforeach; ?>
                <?php foreach ($daily as $day): ?>
                    <circle cx="<?= $escape(value: $xFor(date: $day['date'])) ?>" cy="<?= $escape(value: $yFor(value: (int) $day[$key])) ?>" r="4"
                            fill="<?= $escape(value: $s['color']) ?>" tabindex="0"
                            aria-label="<?= $escape(value: $s['label'] . ': ' . $day[$key] . ' on ' . $day['date']) ?>">
                        <title><?= $escape(value: $s['label'] . ': ' . $day[$key] . ' on ' . $day['date']) ?></title>
                    </circle>
                <?php endforeach; ?>
            <?php
            endforeach; ?>
            <?php
            foreach ($axisLabels as $day): ?>
                <text x="<?= $escape(value: $xFor(date: $day['date'])) ?>" y="295" font-size="12" text-anchor="middle"
                      fill="#475569"><?= $escape(value: $day['date']) ?></text><?php
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
                <td><?= $escape(value: $day['date']) ?></td>
                <td><?= $escape(value: $day['page_a_views']) ?></td>
                <td><?= $escape(value: $day['page_b_views']) ?></td>
                <td><?= $escape(value: $day['buy_cow_clicks']) ?></td>
                <td><?= $escape(value: $day['download_clicks']) ?></td></tr><?php
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
