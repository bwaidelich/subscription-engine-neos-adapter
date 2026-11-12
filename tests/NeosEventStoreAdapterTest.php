<?php

declare(strict_types=1);

namespace Wwwision\SubscriptionEngineNeosAdapter\Tests;

use DateTimeImmutable;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\DefaultSchemaManagerFactory;
use InvalidArgumentException;
use Neos\EventStore\DoctrineAdapter\DoctrineEventStore;
use Neos\EventStore\Model\Event;
use Neos\EventStore\Model\Event\CorrelationId;
use Neos\EventStore\Model\Event\EventData;
use Neos\EventStore\Model\Event\EventId;
use Neos\EventStore\Model\Event\EventType;
use Neos\EventStore\Model\Event\EventTypes;
use Neos\EventStore\Model\Event\StreamName;
use Neos\EventStore\Model\EventStream\EventStreamFilter;
use Neos\EventStore\Model\EventStream\ExpectedVersion;
use Neos\EventStore\Model\EventStream\VirtualStreamName;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use stdClass;
use Wwwision\SubscriptionEngine\EventStore\ReadOptions;
use Wwwision\SubscriptionEngine\Subscription\Position;
use Wwwision\SubscriptionEngineNeosAdapter\NeosEventFilter;
use Wwwision\SubscriptionEngineNeosAdapter\NeosEventStoreAdapter;

#[CoversClass(NeosEventStoreAdapter::class)]
#[CoversClass(NeosEventFilter::class)]
final class NeosEventStoreAdapterTest extends TestCase
{
    private NeosEventStoreAdapter $adapter;

    protected function setUp(): void
    {
        $configuration = new Configuration();
        $configuration->setSchemaManagerFactory(new DefaultSchemaManagerFactory());
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $configuration);
        $eventStore = new DoctrineEventStore($connection, 'events', new class implements ClockInterface {
            public function now(): DateTimeImmutable
            {
                return new DateTimeImmutable();
            }
        });
        $eventStore->setup();
        $events = [
            1 => ['order-1', 'OrderPlaced', 'c1'],
            2 => ['order-2', 'OrderPlaced', 'c2'],
            3 => ['order-1', 'OrderCancelled', 'c1'],
            4 => ['customer-1', 'CustomerRenamed', 'c2'],
        ];
        foreach ($events as $i => [$streamName, $type, $correlationId]) {
            $eventStore->commit(
                StreamName::fromString($streamName),
                new Event(EventId::create(), EventType::fromString($type), EventData::fromString('event ' . $i), correlationId: CorrelationId::fromString($correlationId)),
                ExpectedVersion::ANY(),
            );
        }
        $this->adapter = new NeosEventStoreAdapter($eventStore);
    }

    public function test_read_without_limit_returns_all_events_from_start_position(): void
    {
        self::assertSame([2, 3, 4], $this->readPositions(Position::fromInteger(2), ReadOptions::create()));
    }

    public function test_read_with_limit_returns_at_most_limit_events_from_start_position(): void
    {
        self::assertSame([2, 3], $this->readPositions(Position::fromInteger(2), ReadOptions::create(limit: 2)));
    }

    public function test_read_with_limit_higher_than_remaining_events_returns_remaining_events(): void
    {
        self::assertSame([3, 4], $this->readPositions(Position::fromInteger(3), ReadOptions::create(limit: 10)));
    }

    public function test_read_with_upTo_returns_no_events_after_it(): void
    {
        self::assertSame([2, 3], $this->readPositions(Position::fromInteger(2), ReadOptions::create(upTo: Position::fromInteger(3))));
    }

    public function test_read_with_filter_counts_matching_events_for_the_limit(): void
    {
        self::assertSame([1, 2], $this->readPositions(Position::fromInteger(1), ReadOptions::create(limit: 2, filter: NeosEventFilter::create(VirtualStreamName::forCategory('order-')))));
    }

    public function test_read_with_filter_and_upTo(): void
    {
        self::assertSame([1, 2], $this->readPositions(Position::fromInteger(1), ReadOptions::create(upTo: Position::fromInteger(2), filter: NeosEventFilter::create(VirtualStreamName::forCategory('order-')))));
    }

    /**
     * @return iterable<string, array{NeosEventFilter, array<int>}>
     */
    public static function filters(): iterable
    {
        yield 'all streams' => [NeosEventFilter::create(), [1, 2, 3, 4]];
        yield 'stream' => [NeosEventFilter::create(StreamName::fromString('order-1')), [1, 3]];
        yield 'category' => [NeosEventFilter::create(VirtualStreamName::forCategory('order-')), [1, 2, 3]];
        yield 'correlation id' => [NeosEventFilter::create(VirtualStreamName::forCorrelationId(CorrelationId::fromString('c2'))), [2, 4]];
        yield 'event types' => [NeosEventFilter::create(filter: EventStreamFilter::create(EventTypes::create(EventType::fromString('OrderCancelled'), EventType::fromString('CustomerRenamed')))), [3, 4]];
        yield 'category and event types' => [NeosEventFilter::create(VirtualStreamName::forCategory('order-'), EventStreamFilter::create(EventTypes::create(EventType::fromString('OrderPlaced')))), [1, 2]];
    }

    /**
     * @param array<int> $expectedPositions
     */
    #[DataProvider('filters')]
    public function test_read_with_filter_returns_matching_events_only(NeosEventFilter $filter, array $expectedPositions): void
    {
        self::assertSame($expectedPositions, $this->readPositions(Position::fromInteger(1), ReadOptions::create(filter: $filter)));
    }

    /**
     * @param array<int> $expectedPositions
     */
    #[DataProvider('filters')]
    public function test_matches_decides_like_read(NeosEventFilter $filter, array $expectedPositions): void
    {
        $matchingPositions = [];
        foreach ($this->adapter->read(Position::fromInteger(1), ReadOptions::create()) as $event) {
            if ($this->adapter->matches($filter, $event)) {
                $matchingPositions[] = $this->adapter->eventPosition($event)->value;
            }
        }
        self::assertSame($expectedPositions, $matchingPositions);
    }

    public function test_read_throws_for_filters_of_another_type(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->readPositions(Position::fromInteger(1), ReadOptions::create(filter: new stdClass()));
    }

    /**
     * @return array<int>
     */
    private function readPositions(Position $startPosition, ReadOptions $options): array
    {
        $positions = [];
        foreach ($this->adapter->read($startPosition, $options) as $event) {
            $positions[] = $this->adapter->eventPosition($event)->value;
        }
        return $positions;
    }
}
