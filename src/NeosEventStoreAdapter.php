<?php

declare(strict_types=1);

namespace Wwwision\SubscriptionEngineNeosAdapter;

use Neos\EventStore\EventStoreInterface;
use Neos\EventStore\Model\Event\SequenceNumber;
use Neos\EventStore\Model\EventEnvelope;
use Neos\EventStore\Model\EventStream\VirtualStreamName;
use Wwwision\SubscriptionEngine\EventStore\EventStoreAdapter;
use Wwwision\SubscriptionEngine\Subscription\Position;

/**
 * @implements EventStoreAdapter<EventEnvelope>
 */
final readonly class NeosEventStoreAdapter implements EventStoreAdapter
{
    public function __construct(
        private EventStoreInterface $eventStore,
    ) {
    }

    public function read(Position $startPosition): iterable
    {
        return $this->eventStore->load(VirtualStreamName::all())->withMinimumSequenceNumber(SequenceNumber::fromInteger($startPosition->value));
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
}
