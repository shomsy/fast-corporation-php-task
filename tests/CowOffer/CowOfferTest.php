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
        (new Migrator(pdo: $pdo, migrationDirectories: [
            'ActivityTracking' => dirname(path: __DIR__, levels: 2) . '/src/ActivityTracking/migrations',
        ]))->migrate();

        $offer = new CowOffer(activityLog: new ActivityLog(pdo: $pdo, clock: new SystemClock()));
        $offer->view(actorId: 7, actorLabel: 'alice');
        self::assertSame('thankYou', $offer->buy(actorId: 7, actorLabel: 'alice'));

        $statement = $pdo->query(query: 'SELECT actor_id, actor_label, action, target FROM activity_events ORDER BY id');
        self::assertNotFalse($statement);
        self::assertSame([
            ['actor_id' => 7, 'actor_label' => 'alice', 'action' => 'view-page', 'target' => 'page-a'],
            ['actor_id' => 7, 'actor_label' => 'alice', 'action' => 'button-click', 'target' => 'buy-cow'],
        ], $statement->fetchAll(mode: PDO::FETCH_ASSOC));
    }
}
