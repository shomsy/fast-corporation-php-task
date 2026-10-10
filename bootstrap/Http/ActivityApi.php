<?php

declare(strict_types=1);

namespace InterviewTask\Bootstrap\Http;

use InterviewTask\Accounts\Access\AccessDenied;
use InterviewTask\Accounts\Access\RequireAdmin;
use InterviewTask\ActivityTracking\DailyReports;
use InterviewTask\ActivityTracking\Statistics;
use InterviewTask\Infrastructure\Session\PhpSession;
use InterviewTask\Runtime\Http\Request;
use InterviewTask\Runtime\Http\Response;
use InvalidArgumentException;
use JsonException;
use PDOException;
use Random\RandomException;
use RuntimeException;
use Throwable;

/**
 * Admin-only JSON transport for the authoritative ActivityTracking read models.
 *
 * @package InterviewTask\Bootstrap\Http
 */
final readonly class ActivityApi
{
    /**
     * Receive the existing query owners and server-side session cursor authority.
     *
     * @param Statistics   $statistics Authoritative bounded activity query.
     * @param DailyReports $reports    Authoritative UTC daily aggregation.
     * @param RequireAdmin $admin      Server-owned admin authorization gate.
     * @param PhpSession   $session    Server-only session cursor secret.
     */
    public function __construct(
        private Statistics   $statistics,
        private DailyReports $reports,
        private RequireAdmin $admin,
        private PhpSession   $session,
    ) {}

    /**
     * Return a signed cursor page preserving ActivityTracking filter semantics.
     *
     * @param Request $request Untrusted filter/cursor inputs.
     *
     * @return Response 200 cursor page, 403 denied, or 422 invalid filters.
     * @throws PDOException When authoritative account or activity reads fail.
     * @throws RuntimeException When session access, cursor-secret persistence or activity preparation fails.
     * @throws RandomException When the new cursor secret cannot be generated.
     * @throws JsonException When a response cannot be serialized.
     * @throws Throwable When another configured authentication dependency fails.
     */
    public function statistics(Request $request) : Response
    {
        try {
            $this->admin->account();
        } catch (AccessDenied) {
            return self::error(code: 'forbidden', message: 'Forbidden.', status: 403);
        }

        try {
            $date   = $this->optionalQuery(request: $request, name: 'date');
            $user   = $this->optionalQuery(request: $request, name: 'user');
            $action = $this->optionalQuery(request: $request, name: 'action');
            $cursor = $this->optionalQuery(request: $request, name: 'cursor');
            if ($request->query(key: 'page') !== null) {
                throw new InvalidArgumentException(message: 'Use a cursor for pagination.');
            }

            $secret = $this->session->get(key: 'activity_cursor_secret');
            if (! is_string(value: $secret) || $secret === '') {
                $secret = bin2hex(string: random_bytes(length: 32));
                $this->session->put(key: 'activity_cursor_secret', value: $secret);
                $this->session->persist();
            }

            $result = $this->statistics->cursorPage(
                cursor: $cursor, key: $secret, date: $date, user: $user, action: $action,
            );

            return Response::json(payload: $result);
        } catch (InvalidArgumentException) {
            return self::error(code: 'invalid_filters', message: 'Invalid statistics filters or cursor.', status: 422);
        }
    }

    /**
     * Return one UTC active-day dataset shared by chart and table.
     *
     * @param Request $request Untrusted inclusive UTC date bounds.
     *
     * @return Response 200 canonical daily counts, 403 denied, or 422 invalid dates.
     * @throws PDOException When authoritative account or activity reads fail.
     * @throws RuntimeException When session access or report preparation fails.
     * @throws JsonException When a response cannot be serialized.
     * @throws Throwable When another configured authentication dependency fails.
     */
    public function reports(Request $request) : Response
    {
        try {
            $this->admin->account();
        } catch (AccessDenied) {
            return self::error(code: 'forbidden', message: 'Forbidden.', status: 403);
        }

        try {
            $rows = $this->reports->dailyCounts(
                from: $this->optionalQuery(request: $request, name: 'from'),
                to  : $this->optionalQuery(request: $request, name: 'to'),
            );
        } catch (InvalidArgumentException) {
            return self::error(code: 'invalid_dates', message: 'Invalid UTC date range.', status: 422);
        }

        $days = [];
        foreach ($rows as $row) {
            $days[] = [
                'date'           => $row['date'],
                'pageAViews'     => (int) $row['page_a_views'],
                'pageBViews'     => (int) $row['page_b_views'],
                'buyCowClicks'   => (int) $row['buy_cow_clicks'],
                'downloadClicks' => (int) $row['download_clicks'],
            ];
        }

        return Response::json(payload: ['days' => $days]);
    }

    /**
     * Reject non-string/oversized query values without changing valid filter syntax.
     *
     * @param Request $request Raw HTTP input boundary.
     * @param string  $name    Query parameter name.
     *
     * @return string|null A present nonempty string, otherwise null.
     * @throws InvalidArgumentException For malformed input values.
     */
    private function optionalQuery(Request $request, string $name) : string|null
    {
        $value = $request->query(key: $name);
        if ($value === null || $value === '') {
            return null;
        }
        if (! is_string(value: $value) || strlen(string: $value) > 4096) {
            throw new InvalidArgumentException(message: 'Invalid query input.');
        }

        return $value;
    }

    /**
     * Serialize a stable non-sensitive error outcome for the browser.
     *
     * @param string $code    Stable machine-readable category.
     * @param string $message Safe user-facing message.
     * @param int    $status  HTTP status.
     *
     * @return Response JSON error contract.
     * @throws JsonException When the error response cannot be serialized.
     */
    private static function error(string $code, string $message, int $status) : Response
    {
        return Response::json(payload: ['error' => ['code' => $code, 'message' => $message]], status: $status);
    }
}
