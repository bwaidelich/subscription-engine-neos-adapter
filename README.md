# Neos event store adapter for the Subscription Engine

[![Latest version](https://img.shields.io/packagist/v/wwwision/subscription-engine-neos-adapter)](https://packagist.org/packages/wwwision/subscription-engine-neos-adapter)
[![License](https://img.shields.io/packagist/l/wwwision/subscription-engine-neos-adapter)](./LICENSE)

**Keep your projections up to date with the events of a [neos/eventstore](https://github.com/neos/eventstore).**

This package connects the event store to the [Subscription Engine](https://github.com/bwaidelich/subscription-engine). Your subscribers receive each event as an `EventEnvelope`.

## Installation

Install the adapter together with an event store implementation and a [subscription store](https://github.com/bwaidelich/subscription-engine#which-packages-do-i-need), for example:

```bash
composer require wwwision/subscription-engine-neos-adapter neos/eventstore-doctrineadapter wwwision/subscription-engine-doctrine
```

## Usage

```php
<?php

declare(strict_types=1);

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Neos\EventStore\DoctrineAdapter\DoctrineEventStore;
use Neos\EventStore\Model\Event;
use Neos\EventStore\Model\Event\EventData;
use Neos\EventStore\Model\Event\EventId;
use Neos\EventStore\Model\Event\EventType;
use Neos\EventStore\Model\Event\StreamName;
use Neos\EventStore\Model\EventEnvelope;
use Neos\EventStore\Model\EventStream\ExpectedVersion;
use Wwwision\SubscriptionEngine\Subscriber\Subscriber;
use Wwwision\SubscriptionEngine\Subscriber\Subscribers;
use Wwwision\SubscriptionEngine\Subscription\RunMode;
use Wwwision\SubscriptionEngine\SubscriptionEngine;
use Wwwision\SubscriptionEngineDoctrine\DoctrineSubscriptionStore;
use Wwwision\SubscriptionEngineNeosAdapter\NeosEventStoreAdapter;

require __DIR__ . '/vendor/autoload.php';

$connection = DriverManager::getConnection((new DsnParser())->parse('pdo-sqlite:///' . __DIR__ . '/demo.sqlite'));
$eventStore = new DoctrineEventStore($connection, eventTableName: 'events');

$engine = new SubscriptionEngine(
    eventStoreAdapter: new NeosEventStoreAdapter($eventStore),
    subscriptionStore: new DoctrineSubscriptionStore($connection, tableName: 'subscriptions'),
    subscribers: Subscribers::fromArray([
        Subscriber::create(
            id: 'some-projection',
            handler: fn (EventEnvelope $envelope) => print("Projecting {$envelope->event->type->value} at position {$envelope->sequenceNumber->value}\n"),
            reset: fn () => print("Resetting projection for replay\n"),
        ),
        Subscriber::create(
            id: 'some-process',
            handler: fn (EventEnvelope $envelope) => print("Invoking process for {$envelope->event->type->value}\n"),
            runMode: RunMode::FROM_NOW,
            setup: fn () => print("Setting up process\n"),
        ),
    ]),
);

// Once per deployment
$eventStore->setup();
$engine->setup();
$engine->boot();

// Store an event and pass it to the subscribers
$eventStore->commit(
    StreamName::fromString('some-stream'),
    new Event(EventId::create(), EventType::fromString('SomethingHappened'), EventData::fromString('{"foo": "bar"}')),
    ExpectedVersion::ANY(),
);
$engine->catchUpActive();
```

```console
Setting up process
Invoking process for SomethingHappened
Projecting SomethingHappened at position 1
```

## Next steps

The [guides of the Subscription Engine](https://github.com/bwaidelich/subscription-engine#guides) explain how to rebuild projections, handle failures and more. To process events in the background, see [wwwision/subscription-engine-worker](https://github.com/bwaidelich/subscription-engine-worker).

## Contribution

Contributions in the form of [issues](https://github.com/bwaidelich/subscription-engine-neos-adapter/issues) or [pull requests](https://github.com/bwaidelich/subscription-engine-neos-adapter/pulls) are highly appreciated

## License

See [LICENSE](./LICENSE)
