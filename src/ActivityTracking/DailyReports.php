<?php

declare(strict_types=1);

namespace InterviewTask\ActivityTracking;

use InvalidArgumentException;
use InterviewTask\ActivityTracking\Persistence\ReadDailyActivity;
use PDOException;
use RuntimeException;

/**
 * One bounded UTC daily dataset for both chart and table.
 *
 * @phpstan-type DailyActivityRow array{date: string, page_a_views: int, page_b_views: int, buy_cow_clicks: int,
 *               download_clicks: int}
 * @api
 * @package InterviewTask\ActivityTracking
 * @see     Statistics
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
     * @param ReadDailyActivity $reader Owner-private bounded daily SQLite projection.
     */
    public function __construct(private ReadDailyActivity $reader) {}

    /**
     * Aggregate the last 30 days with qualifying events, ascending by UTC date. The optional inclusive bounds filter
     * before the 30-row limit is applied. Dates without tracked events are not filled with synthetic zero rows.
     *
     * @param string|null $from Inclusive YYYY-MM-DD UTC lower bound.
     * @param string|null $to   Inclusive YYYY-MM-DD UTC upper bound.
     *
     * @return list<DailyActivityRow> The same counts feed the chart and table.
     * @throws InvalidArgumentException When a submitted value or filter violates the documented precondition.
     * @throws PDOException When the underlying SQLite operation fails.
     * @throws RuntimeException When the query cannot be prepared.
     */
    public function dailyCounts(string|null $from = null, string|null $to = null) : array
    {
        $parameters = [];
        foreach (['from' => $from, 'to' => $to] as $name => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $date = UtcDate::tryFrom(value: $value);
            if ($date === null) {
                throw new InvalidArgumentException(message: 'Invalid report date.');
            }
            $parameters[$name] = $date->value();
        }

        if (isset($parameters['from'], $parameters['to']) && $parameters['from'] > $parameters['to']) {
            throw new InvalidArgumentException(message: 'Report date range must be ascending.');
        }

        return $this->reader->read(parameters: $parameters);
    }
}
