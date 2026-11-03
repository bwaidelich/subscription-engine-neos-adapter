<?php

declare(strict_types=1);

namespace Wwwision\SubscriptionEngineNeosAdapter\Tests;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\DefaultSchemaManagerFactory;
use Neos\EventStore\DoctrineAdapter\DoctrineEventStore;
use Neos\EventStore\Model\Event;
use Neos\EventStore\Model\Event\EventData;
use Neos\EventStore\Model\Event\EventId;
use Neos\EventStore\Model\Event\EventType;
use Neos\EventStore\Model\Event\StreamName;
use Neos\EventStore\Model\EventStream\ExpectedVersion;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Wwwision\SubscriptionEngine\Subscription\Position;
use Wwwision\SubscriptionEngineNeosAdapter\NeosEventStoreAdapter;

#[CoversClass(NeosEventStoreAdapter::class)]
final class NeosEventStoreAdapterTest extends TestCase
{
    private NeosEventStoreAdapter $adapter;

    protected function setUp(): void
    {
        $configuration = new Configuration();
        $configuration->setSchemaManagerFactory(new DefaultSchemaManagerFactory());
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $configuration);
        $eventStore = new DoctrineEventStore($connection, eventTableName: 'events');
        $eventStore->setup();
        for ($i = 1; $i <= 4; $i++) {
            $eventStore->commit(
                StreamName::fromString('some-stream'),
                new Event(EventId::create(), EventType::fromString('SomeEvent'), EventData::fromString('event ' . $i)),
                ExpectedVersion::ANY(),
            );
        }
        $this->adapter = new NeosEventStoreAdapter($eventStore);
    }

    public function test_read_without_limit_returns_all_events_from_start_position(): void
    {
        self::assertSame([2, 3, 4], $this->readPositions(Position::fromInteger(2)));
    }

    public function test_read_with_limit_returns_at_most_limit_events_from_start_position(): void
    {
        self::assertSame([2, 3], $this->readPositions(Position::fromInteger(2), 2));
    }

    public function test_read_with_limit_higher_than_remaining_events_returns_remaining_events(): void
    {
        self::assertSame([3, 4], $this->readPositions(Position::fromInteger(3), 10));
    }

    /**
     * @return array<int>
     */
    private function readPositions(Position $startPosition, int|null $limit = null): array
    {
        $positions = [];
        foreach ($this->adapter->read($startPosition, $limit) as $event) {
            $positions[] = $this->adapter->eventPosition($event)->value;
        }
        return $positions;
    }
}
