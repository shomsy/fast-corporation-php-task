<?php

declare(strict_types=1);

namespace InterviewTask\ActivityTracking\Persistence;

use InterviewTask\ActivityTracking\DailyReports;
use PDO;
use PDOException;
use RuntimeException;

/**
 * Private SQLite realization of the owner's bounded daily chart/table projection.
 *
 * @package InterviewTask\ActivityTracking\Persistence
 */
final readonly class ReadDailyActivity
{
    /**
     * @param PDO $pdo Same configured SQLite connection as the event writer.
     */
    public function __construct(private PDO $pdo) {}

    /**
     * Read and aggregate already-validated UTC dates without owning their domain validation.
     *
     * @param array<string, string> $parameters Validated optional from/to dates.
     *
     * @return list<array{
     *     date: string,
     *     page_a_views: int,
     *     page_b_views: int,
     *     buy_cow_clicks: int,
     *     download_clicks: int
     * }> One bounded UTC date series.
     * @throws RuntimeException When SQLite cannot prepare the projection.
     * @throws PDOException On read execution failure.
     */
    public function read(array $parameters) : array
    {
        $conditions = ["((action = 'view-page' AND target IN ('page-a', 'page-b')) OR (action = 'button-click' AND target IN ('buy-cow', 'download')))"];
        if (isset($parameters['from'])) {
            $conditions[] = 'substr(occurred_at, 1, 10) >= :from';
        }
        if (isset($parameters['to'])) {
            $conditions[] = 'substr(occurred_at, 1, 10) <= :to';
        }
        $query     = <<<'SQL'
            SELECT substr(occurred_at, 1, 10) AS date,
                SUM(CASE WHEN action = 'view-page' AND target = 'page-a' THEN 1 ELSE 0 END) AS page_a_views,
                SUM(CASE WHEN action = 'view-page' AND target = 'page-b' THEN 1 ELSE 0 END) AS page_b_views,
                SUM(CASE WHEN action = 'button-click' AND target = 'buy-cow' THEN 1 ELSE 0 END) AS buy_cow_clicks,
                SUM(CASE WHEN action = 'button-click' AND target = 'download' THEN 1 ELSE 0 END) AS download_clicks
            FROM activity_events WHERE
            SQL;
        $query     .= implode(separator: ' AND ', array: $conditions);
        $query     .= ' GROUP BY substr(occurred_at, 1, 10) ORDER BY date DESC LIMIT ' . DailyReports::MAX_DAYS;
        $statement = $this->pdo->prepare(query: 'SELECT * FROM (' . $query . ') ORDER BY date ASC');
        if ($statement === false) {
            throw new RuntimeException(message: 'Unable to prepare daily activity report.');
        }
        $statement->execute(params: $parameters);

        /** @var list<array{date: string, page_a_views: int, page_b_views: int, buy_cow_clicks: int, download_clicks: int}> $rows */
        $rows = $statement->fetchAll(mode: PDO::FETCH_ASSOC);

        return $rows;
    }
}
