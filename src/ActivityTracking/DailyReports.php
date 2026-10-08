<?php

declare(strict_types=1);

namespace InterviewTask\ActivityTracking;

use PDO;
use RuntimeException;

/**
 * One daily dataset for both the graph and the tabular report.
 * Dates are UTC calendar days, matching persisted activity timestamps.
 */
final readonly class DailyReports
{
    public function __construct(private PDO $pdo) {}

    /**
     * @return list<array{date: string, page_a_views: int, page_b_views: int, buy_cow_clicks: int, download_clicks: int}>
     */
    public function dailyCounts(): array
    {
        $statement = $this->pdo->prepare(<<<'SQL'
            SELECT
                substr(occurred_at, 1, 10) AS date,
                SUM(CASE WHEN action = 'view-page' AND target = 'page-a' THEN 1 ELSE 0 END) AS page_a_views,
                SUM(CASE WHEN action = 'view-page' AND target = 'page-b' THEN 1 ELSE 0 END) AS page_b_views,
                SUM(CASE WHEN action = 'button-click' AND target = 'buy-cow' THEN 1 ELSE 0 END) AS buy_cow_clicks,
                SUM(CASE WHEN action = 'button-click' AND target = 'download' THEN 1 ELSE 0 END) AS download_clicks
            FROM activity_events
            WHERE (action = 'view-page' AND target IN ('page-a', 'page-b'))
               OR (action = 'button-click' AND target IN ('buy-cow', 'download'))
            GROUP BY substr(occurred_at, 1, 10)
            ORDER BY date ASC
            SQL);

        if ($statement === false) {
            throw new RuntimeException('Unable to prepare daily activity report.');
        }

        $statement->execute();

        /** @var list<array{date: string, page_a_views: int, page_b_views: int, buy_cow_clicks: int, download_clicks: int}> $rows */
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $rows;
    }
}
