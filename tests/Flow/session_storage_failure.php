<?php

declare(strict_types=1);

/**
 * Prove native file-session write failure cannot accept a login or expose session warnings.
 *
 * Run only inside a disposable container with a zero-byte file limit and ignored SIGXFSZ:
 * docker run --rm -i --ulimit fsize=0:0 -v "$PWD:$PWD" -w "$PWD" php:8.5-cli
 *     sh -c "trap '' XFSZ; exec php tests/Flow/session_storage_failure.php"
 * The limit affects this container only; it does not fill a filesystem or fault host storage.
 *
 * @package InterviewTask\Tests\Flow
 */

use InterviewTask\Accounts\AccountCredentials;
use InterviewTask\Accounts\AccountLifecycle;
use InterviewTask\Accounts\Accounts;
use InterviewTask\Accounts\BcryptPasswords;
use InterviewTask\Accounts\Login\Login;
use InterviewTask\Accounts\Persistence\SqliteAccounts;
use InterviewTask\Accounts\Register\Register;
use InterviewTask\Accounts\SessionIdentity;
use InterviewTask\ActivityTracking\ActivityLog;
use InterviewTask\ActivityTracking\Persistence\RecordActivity;
use InterviewTask\Bootstrap\Http\AccountApi;
use InterviewTask\Infrastructure\Database\Migrator;
use InterviewTask\Infrastructure\Database\SqliteConnection;
use InterviewTask\Infrastructure\Database\Transaction;
use InterviewTask\Infrastructure\Diagnostics\CleanupFailures;
use InterviewTask\Infrastructure\Session\Csrf;
use InterviewTask\Infrastructure\Session\PhpSession;
use InterviewTask\Infrastructure\Time\SystemClock;
use InterviewTask\Runtime\Http\Request;
use InterviewTask\Runtime\Http\Response;

$limits = function_exists(function: 'posix_getrlimit') ? posix_getrlimit() : false;
if (! is_array(value: $limits) || ($limits['soft filesize'] ?? null) !== 0 || ($limits['hard filesize'] ?? null) !== 0) {
    throw new RuntimeException(message: 'Run this probe only in the documented file-limited disposable container.');
}

$root = dirname(path: __DIR__, levels: 2);
require $root . '/vendor/autoload.php';
ini_set(option: 'display_errors', value: '0');
ini_set(option: 'session.use_cookies', value: '0');
ini_set(option: 'session.save_handler', value: 'files');
$directory = sys_get_temp_dir() . '/fast-native-session-' . bin2hex(string: random_bytes(length: 8));
if (! mkdir(directory: $directory, permissions: 0700)) {
    throw new RuntimeException(message: 'Unable to provision isolated probe storage.');
}

try {
    $pdo = SqliteConnection::memory();
    (new Migrator(pdo: $pdo, migrationDirectories: [
        'Accounts'         => $root . '/src/Accounts/migrations',
        'ActivityTracking' => $root . '/src/ActivityTracking/migrations',
    ]))->migrate();
    $accounts  = new Accounts(storage: new SqliteAccounts(pdo: $pdo), credentials: new AccountCredentials(), bcrypt: new BcryptPasswords());
    $clock     = new SystemClock();
    $session   = new PhpSession(savePath: $directory);
    $cleanup   = new CleanupFailures(report: static function (Throwable $error) : void {});
    $identity  = new SessionIdentity(session: $session, accounts: $accounts, clock: $clock, cleanupFailures: $cleanup);
    $lifecycle = new AccountLifecycle(
        register       : new Register(accounts: $accounts, clock: $clock),
        login          : new Login(accounts: $accounts, identity: $identity),
        identity       : $identity,
        activity       : new ActivityLog(writer: new RecordActivity(pdo: $pdo), clock: $clock),
        transaction    : new Transaction(pdo: $pdo, cleanupFailures: $cleanup),
        cleanupFailures: $cleanup,
    );
    $lifecycle->register(username: 'native-storage-probe', password: 'strong-password');
    $api          = new AccountApi(lifecycle: $lifecycle, csrf: new Csrf(session: $session));
    $warningCount = 0;
    set_error_handler(callback: static function (int $severity, #[SensitiveParameter] string $message) use (&$warningCount) : bool {
        $warningCount++;

        return true;
    });

    try {
        try {
            $response             = $api->login(request: new Request(
                                                             method : 'POST', path: '/api/accounts/login', server: ['CONTENT_TYPE' => 'application/json'],
                                                             rawBody: json_encode(value: ['username' => 'native-storage-probe', 'password' => 'strong-password'], flags: JSON_THROW_ON_ERROR),
                                                         ));
            $failedBeforeResponse = false;
            $safeFailure          = false;
        } catch (RuntimeException $failure) {
            $failedBeforeResponse = true;
            $safeFailure          = $failure->getMessage() === 'Unable to persist session.' && $failure->getPrevious() === null;
            $response             = Response::text(body: 'Internal Server Error', status: 500);
        }
        ob_start();
        $response->send();
        $body = ob_get_clean();
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
    } finally {
        restore_error_handler();
    }

    $loginEvents = $pdo->query(query: "SELECT COUNT(*) FROM activity_events WHERE action = 'login'");
    if ($loginEvents === false) {
        throw new RuntimeException(message: 'Unable to inspect login acceptance evidence.');
    }
    $result = [
        'status'                  => $response->status(),
        'failed_before_response'  => $failedBeforeResponse,
        'safe_failure'            => $safeFailure,
        'generic_response'        => $body === 'Internal Server Error',
        'native_warning_count'    => $warningCount,
        'memory_cleared'          => $_SESSION === [],
        'remaining_session_files' => count(glob(pattern: $directory . '/sess_*') ?: []),
        'login_events'            => (int) $loginEvents->fetchColumn(),
    ];
    if ($result !== [
            'status'               => 500, 'failed_before_response' => true, 'safe_failure' => true, 'generic_response' => true,
            'native_warning_count' => 0, 'memory_cleared' => true, 'remaining_session_files' => 0, 'login_events' => 0,
        ]) {
        throw new RuntimeException(message: 'Native session failure acceptance failed.');
    }
    fwrite(stream: STDOUT, data: json_encode(value: $result, flags: JSON_THROW_ON_ERROR) . PHP_EOL);
} finally {
    foreach (glob(pattern: $directory . '/sess_*') ?: [] as $file) {
        unlink(filename: $file);
    }
    rmdir(directory: $directory);
}
