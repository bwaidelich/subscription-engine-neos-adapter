<?php

declare(strict_types=1);

namespace Wwwision\SubscriptionEngineNeosAdapter;

use Neos\EventStore\Model\Event\StreamName;
use Neos\EventStore\Model\EventStream\EventStreamFilter;
use Neos\EventStore\Model\EventStream\VirtualStreamName;

/**
 * Filter of a subscriber: The events of one stream, optionally narrowed by an {@see EventStreamFilter}
 */
final readonly class NeosEventFilter
{
    private function __construct(
        public StreamName|VirtualStreamName $streamName,
        public EventStreamFilter|null $filter,
    ) {
    }

    /**
     * @param StreamName|VirtualStreamName|null $streamName NULL for all streams
     */
    public static function create(
        StreamName|VirtualStreamName|null $streamName = null,
        EventStreamFilter|null $filter = null,
    ): self {
        return new self($streamName ?? VirtualStreamName::all(), $filter);
    }
}
