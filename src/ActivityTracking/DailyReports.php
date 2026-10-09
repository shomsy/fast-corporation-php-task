<?php

declare(strict_types=1);

namespace InterviewTask\ActivityTracking;

use DateTimeImmutable;
use InvalidArgumentException;
use PDO;
use PDOException;
use RuntimeException;

/**
 * One bounded UTC daily dataset for both chart and table.
 *
 * @phpstan-type DailyActivityRow array{date: string, page_a_views: int, page_b_views: int, buy_cow_clicks: int, download_clicks: int}
 * @api
 * @package InterviewTask\ActivityTracking
 * @see Statistics
 */
final readonly class DailyReports
{
    /**
     * Maximum number of active UTC dates returned in one report.
     *
     * @var int
     */
    public const int MAX_DAYS = 30;

    /**
     * Bind the daily reporting query to the activity database.
     *
     * @param PDO $pdo SQLite PDO connection with configured driver settings.
     */
    public function __construct(private PDO $pdo) {}

    /**
     * Aggregate the last 30 days with qualifying events, ascending by UTC date. The optional inclusive bounds filter before the 30-row limit is applied. Dates without tracked events are not filled with synthetic zero rows.
     *
     * @param string|null $from Inclusive YYYY-MM-DD UTC lower bound.
     * @param string|null $to Inclusive YYYY-MM-DD UTC upper bound.
     * @return list<DailyActivityRow> The same counts feed the chart and table.
     * @throws InvalidArgumentException When a submitted value or filter violates the documented precondition.
     * @throws PDOException When the underlying SQLite operation fails.
     * @throws RuntimeException When the query cannot be prepared.
     */
    public function dailyCounts(string|null $from = null, string|null $to = null): array
    {
        $parameters = [];
        foreach (['from' => $from, 'to' => $to] as $name => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            if (preg_match(pattern: '/\A[0-9]{4}-[0-9]{2}-[0-9]{2}\z/', subject: $value) !== 1) {
                throw new InvalidArgumentException(message: 'Invalid report date.');
            }

            $parsed = DateTimeImmutable::createFromFormat(format: '!Y-m-d', datetime: $value);
            if ($parsed === false || $parsed->format(format: 'Y-m-d') !== $value) {
                throw new InvalidArgumentException(message: 'Invalid report date.');
            }
            $parameters[$name] = $value;
        }

        if (isset($parameters['from'], $parameters['to']) && $parameters['from'] > $parameters['to']) {
            throw new InvalidArgumentException(message: 'Report date range must be ascending.');
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
        $query     .= implode(separator: ' AND ', array: $conditions);
        $query     .= ' GROUP BY substr(occurred_at, 1, 10) ORDER BY date DESC LIMIT ' . self::MAX_DAYS;
        $statement = $this->pdo->prepare(query: 'SELECT * FROM (' . $query . ') ORDER BY date ASC');
        if ($statement === false) {
            throw new RuntimeException(message: 'Unable to prepare daily activity report.');
        }
        $statement->execute(params: $parameters);

        /** @var list<DailyActivityRow> $rows */
        $rows = $statement->fetchAll(mode: PDO::FETCH_ASSOC);

        return $rows;
    }
}
