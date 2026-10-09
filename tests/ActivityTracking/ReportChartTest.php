<?php

declare(strict_types=1);

namespace InterviewTask\Tests\ActivityTracking;

use InterviewTask\Infrastructure\Templating\Renderer;
use PHPUnit\Framework\TestCase;

/**
 * Regression checks for faithful calendar spacing and accessible chart semantics.
 *
 * @package InterviewTask\Tests\ActivityTracking
 */
final class ReportChartTest extends TestCase
{
    /**
     * The second of three sparse dates must appear near day two, not at the halfway x-coordinate.
     *
     * @return void No return value.
     */
    public function testSparseUtcCalendarDatesAreSpacedProportionally(): void
    {
        $html = $this->render([
            $this->day(date: '2026-10-01'),
            $this->day(date: '2026-10-02'),
            $this->day(date: '2026-10-10'),
        ]);

        self::assertStringContainsString('cx="167.22"', $html);
        preg_match_all(pattern: '/<polyline\\b/', subject: $html, matches: $lines);
        self::assertCount(8, $lines[0], 'All four series must break across a missing-day gap.');
        self::assertStringContainsString('Missing UTC dates', $html);
        self::assertStringContainsString('aria-labelledby="activity-chart-title activity-chart-description"', $html);
    }

    /**
     * Count-one datasets need only unique zero and one Y ticks, not five repeated labels.
     *
     * @return void No return value.
     */
    public function testLowCountsHaveUniqueYAxisTicksAndKeyboardReadableValues(): void
    {
        $html = $this->render([$this->day(date: '2026-10-08')]);

        preg_match_all(pattern: '/<text x="65"[^>]*>([0-9]+)<\/text>/', subject: $html, matches: $matches);
        self::assertSame(['0', '1'], $matches[1]);
        self::assertStringContainsString('tabindex="0"', $html);
        self::assertStringContainsString('Page A views: 1 on 2026-10-08', $html);
    }

    /**
     * Build one report row with a single Page A view.
     *
     * @param string $date Trusted fixture UTC date.
     * @return array{date: string, page_a_views: int, page_b_views: int, buy_cow_clicks: int, download_clicks: int}
     */
    private function day(string $date): array
    {
        return [
            'date' => $date, 'page_a_views' => 1, 'page_b_views' => 0,
            'buy_cow_clicks' => 0, 'download_clicks' => 0,
        ];
    }

    /**
     * Render the actual ActivityTracking HTML adapter with isolated report data.
     *
     * @param list<array{date: string, page_a_views: int, page_b_views: int, buy_cow_clicks: int, download_clicks: int}> $daily
     * @return string Escaped report HTML.
     */
    private function render(array $daily): string
    {
        return (new Renderer())->render(
            template: dirname(path: __DIR__, levels: 2) . '/src/ActivityTracking/reports.php',
            data: ['daily' => $daily, 'from' => '', 'to' => '', 'error' => ''],
        );
    }
}
