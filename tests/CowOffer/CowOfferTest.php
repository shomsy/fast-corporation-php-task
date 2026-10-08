<?php

declare(strict_types=1);

namespace InterviewTask\Tests\CowOffer;

use InterviewTask\ActivityTracking\ActivityLog;
use InterviewTask\CowOffer\CowOffer;
use InterviewTask\Infrastructure\Database\Migrator;
use InterviewTask\Infrastructure\Database\SqliteConnection;
use InterviewTask\Infrastructure\Time\SystemClock;
use PDO;
use PHPUnit\Framework\TestCase;

final class CowOfferTest extends TestCase
{
    public function testViewAndBuyProduceExactlyTheRequiredActivity(): void
    {
        $pdo = SqliteConnection::memory();
        (new Migrator($pdo, [
            'ActivityTracking' => dirname(__DIR__, 2) . '/src/ActivityTracking/migrations',
        ]))->migrate();

        $offer = new CowOffer(new ActivityLog($pdo, new SystemClock()));
        $offer->view(7, 'alice');
        self::assertSame('thankYou', $offer->buy(7, 'alice'));

        $statement = $pdo->query('SELECT actor_id, actor_label, action, target FROM activity_events ORDER BY id');
        self::assertNotFalse($statement);
        self::assertSame([
            ['actor_id' => 7, 'actor_label' => 'alice', 'action' => 'view-page', 'target' => 'page-a'],
            ['actor_id' => 7, 'actor_label' => 'alice', 'action' => 'button-click', 'target' => 'buy-cow'],
        ], $statement->fetchAll(PDO::FETCH_ASSOC));
    }
}
