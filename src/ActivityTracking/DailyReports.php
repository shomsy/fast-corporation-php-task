<?php

declare(strict_types=1);

namespace InterviewTask\ActivityTracking;

use DateTimeImmutable;
use InvalidArgumentException;
use PDO;
use RuntimeException;

/** One bounded UTC daily dataset for both chart and table. */
final readonly class DailyReports
{
    public const int MAX_DAYS = 30;

    public function __construct(private PDO $pdo) {}

    /**
     * @return list<array{date: string, page_a_views: int, page_b_views: int, buy_cow_clicks: int, download_clicks:
     *                          int}>
     */
    public function dailyCounts(?string $from = null, ?string $to = null) : array
    {
        $parameters = [];
        foreach (['from' => $from, 'to' => $to] as $name => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
            if ($parsed === false || $parsed->format('Y-m-d') !== $value) {
                throw new InvalidArgumentException('Invalid report date.');
            }
            $parameters[$name] = $value;
        }

        if (isset($parameters['from'], $parameters['to']) && $parameters['from'] > $parameters['to']) {
            throw new InvalidArgumentException('Report date range must be ascending.');
        }

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
        $query     .= implode(' AND ', $conditions);
        $query     .= ' GROUP BY substr(occurred_at, 1, 10) ORDER BY date DESC LIMIT ' . self::MAX_DAYS;
        $statement = $this->pdo->prepare('SELECT * FROM (' . $query . ') ORDER BY date ASC');
        if ($statement === false) {
            throw new RuntimeException('Unable to prepare daily activity report.');
        }
        $statement->execute($parameters);

        /** @var list<array{date: string, page_a_views: int, page_b_views: int, buy_cow_clicks: int, download_clicks: int}> $rows */
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $rows;
    }
}
