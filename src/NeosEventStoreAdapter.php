<?php

declare(strict_types=1);

namespace Wwwision\SubscriptionEngineNeosAdapter;

use InvalidArgumentException;
use Neos\EventStore\EventStoreInterface;
use Neos\EventStore\Model\Event\SequenceNumber;
use Neos\EventStore\Model\Event\StreamName;
use Neos\EventStore\Model\EventEnvelope;
use Neos\EventStore\Model\EventStream\VirtualStreamName;
use Neos\EventStore\Model\EventStream\VirtualStreamType;
use Wwwision\SubscriptionEngine\EventStore\EventStoreAdapter;
use Wwwision\SubscriptionEngine\EventStore\ReadOptions;
use Wwwision\SubscriptionEngine\Subscription\Position;

/**
 * Subscribers can declare a {@see NeosEventFilter} as filter
 *
 * @implements EventStoreAdapter<EventEnvelope, NeosEventFilter>
 */
final readonly class NeosEventStoreAdapter implements EventStoreAdapter
{
    public function __construct(
        private EventStoreInterface $eventStore,
    ) {
    }

    public function read(Position $startPosition, ReadOptions $options): iterable
    {
        $filter = $options->filter !== null ? self::filter($options->filter) : NeosEventFilter::create();
        $eventStream = $this->eventStore->load($filter->streamName, $filter->filter)->withMinimumSequenceNumber(SequenceNumber::fromInteger($startPosition->value));
        if ($options->upTo !== null) {
            $eventStream = $eventStream->withMaximumSequenceNumber(SequenceNumber::fromInteger($options->upTo->value));
        }
        return $options->limit !== null ? $eventStream->limit($options->limit) : $eventStream;
    }

    public function lastPosition(): Position
    {
        /** @var EventEnvelope|null $lastEventEnvelope */
        $lastEventEnvelope = iterator_to_array($this->eventStore->load(VirtualStreamName::all())->backwards()->limit(1))[0] ?? null;
        return Position::fromInteger($lastEventEnvelope->sequenceNumber->value ?? 0);
    }

    public function eventPosition(object $event): Position
    {
        return Position::fromInteger($event->sequenceNumber->value);
    }

    /**
     * Categories are matched by prefix and correlation ids by equality, like the Doctrine adapter of neos/eventstore does without wildcards and with a case-sensitive collation
     */
    public function matches(object $filter, object $event): bool
    {
        $filter = self::filter($filter);
        $streamName = $filter->streamName;
        $matchesStream = match (true) {
            $streamName instanceof StreamName => $event->streamName->value === $streamName->value,
            $streamName->type === VirtualStreamType::ALL => true,
            $streamName->type === VirtualStreamType::CATEGORY => str_starts_with($event->streamName->value, $streamName->value),
            $streamName->type === VirtualStreamType::CORRELATION_ID => $event->event->correlationId?->value === $streamName->value,
        };
        if (!$matchesStream) {
            return false;
        }
        $eventTypes = $filter->filter?->eventTypes;
        return $eventTypes === null || $eventTypes->contains($event->event->type);
    }

    private static function filter(object $filter): NeosEventFilter
    {
        if (!$filter instanceof NeosEventFilter) {
            throw new InvalidArgumentException(sprintf('Expected filter of type %s, got: %s', NeosEventFilter::class, get_debug_type($filter)), 1794470700);
        }
        return $filter;
    }
}
