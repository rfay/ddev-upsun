<?php

namespace Drupal\search_api_opensearch_location\EventSubscriber;

use Drupal\search_api_opensearch\Event\SupportsDataTypeEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Search API OpenSearch Location event subscriber.
 */
class DataTypeEventSubscriber implements EventSubscriberInterface {

  /**
   * Event called when supported data types are determined.
   */
  public function onSupportsDataType(SupportsDataTypeEvent $event): void {
    if ($event->getType() === 'location') {
      $event->setIsSupported(TRUE);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      SupportsDataTypeEvent::class => ['onSupportsDataType'],
    ];
  }

}
