<?php

declare(strict_types=1);

namespace InterviewTask\Bootstrap\Http;

use InterviewTask\Accounts\ManageUsers\ManageUsers;
use InterviewTask\Runtime\Http\Request;
use InterviewTask\Runtime\Http\Response;
use InvalidArgumentException;
use JsonException;
use Throwable;

/**
 * Administrator-only account directory and explicit role upgrade transport.
 *
 * @package InterviewTask\Bootstrap\Http
 */
final readonly class ManageUsersApi
{
    /**
     * Bind the Account owner's authorized management behavior.
     *
     * @param ManageUsers $users Verified account management behavior.
     */
    public function __construct(private ManageUsers $users) {}

    /**
     * Read a bounded account page, without any credentials.
     *
     * @param Request $request Untrusted cursor.
     *
     * @return Response Account page or safe validation error.
     * @throws JsonException When response encoding fails.
     * @throws Throwable When storage or authorization fails.
     */
    public function index(Request $request) : Response
    {
        $raw = $request->query(key: 'after');
        if ($raw !== null && (! is_string(value: $raw)
            || preg_match(pattern: '/^(0|[1-9][0-9]{0,17})$/D', subject: $raw) !== 1
            || filter_var(value: $raw, filter: FILTER_VALIDATE_INT, options: ['options' => ['min_range' => 0]]) === false)) {
            return self::error(code: 'invalid_cursor', message: 'We could not load users. Start again from the first page.', status: 422);
        }

        $after = $raw === null ? 0 : (int) $raw;
        return Response::json(payload: $this->users->listAfter(afterId: $after));
    }

    /**
     * Grant administrator rights to a specified existing ordinary user.
     *
     * @param Request $request Untrusted JSON with userId.
     *
     * @return Response Updated account, or 404/409/422 error.
     * @throws JsonException When response encoding fails.
     * @throws Throwable When storage, authorization or diagnostics fails.
     */
    public function promote(Request $request) : Response
    {
        try {
            $body = $request->jsonObject();
            $userId = $body['userId'] ?? null;

            if (! is_int(value: $userId) || $userId <= 0) {
                throw new InvalidArgumentException(message: 'Invalid user ID.');
            }

            $outcome = $this->users->promote(userId: $userId);
            if ($outcome['status'] === 'already_admin') {
                return self::error(code: 'already_admin', message: 'This user already has administrator access.', status: 409);
            }
            if ($outcome['status'] === 'not_found' || $outcome['user'] === null) {
                return self::error(code: 'user_not_found', message: 'This account no longer exists. Refresh the list.', status: 404);
            }

            return Response::json(payload: ['user' => $outcome['user']]);
        } catch (InvalidArgumentException) {
            return self::error(code: 'invalid_user', message: 'Choose a valid user to update.', status: 422);
        }
    }

    /**
     * @param string $code Stable error code.
     * @param string $message Safe explanation.
     * @param int $status HTTP status.
     *
     * @return Response JSON error response.
     * @throws JsonException When serialization fails.
     */
    private static function error(string $code, string $message, int $status) : Response
    {
        return Response::json(payload: ['error' => ['code' => $code, 'message' => $message]], status: $status);
    }
}
