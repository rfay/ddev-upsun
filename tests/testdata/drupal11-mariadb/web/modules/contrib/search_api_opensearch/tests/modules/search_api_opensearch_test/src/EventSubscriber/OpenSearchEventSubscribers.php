<?php

declare(strict_types=1);

namespace Drupal\search_api_opensearch_test\EventSubscriber;

use Drupal\search_api\IndexInterface;
use Drupal\search_api_opensearch\Event\IndexCreatedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * OpenSearch test event subscribers.
 */
class OpenSearchEventSubscribers implements EventSubscriberInterface {

  /**
   * The index that was created.
   */
  private static IndexInterface $index;

  /**
   * Event handler for when an index is created.
   */
  public function onIndexCreated(IndexCreatedEvent $event): void {
    self::$index = $event->getIndex();
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      IndexCreatedEvent::class => ['onIndexCreated'],
    ];
  }

  /**
   * Get the index that was created.
   */
  public static function getIndex(): IndexInterface {
    return self::$index;
  }

}
