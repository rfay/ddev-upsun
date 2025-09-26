<?php

namespace Drupal\search_api_opensearch\SearchAPI;

use Drupal\search_api_opensearch\Event\DeleteParamsEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Provides a param builder for delete operations.
 */
class DeleteParamBuilder {

  public function __construct(
    protected EventDispatcherInterface $eventDispatcher,
  ) {
  }

  /**
   * Builds the params for a delete operation.
   *
   * @param string $indexId
   *   The index ID.
   * @param \Drupal\search_api\Item\ItemInterface[] $item_ids
   *   The items.
   *
   * @return array
   *   The index operation params.
   */
  public function buildDeleteParams(string $indexId, array $item_ids): array {
    $params = [
      'index' => $indexId,
    ];

    foreach ($item_ids as $id) {
      $params['body'][] = [
        'delete' => [
          '_index' => $params['index'],
          '_id' => $id,
        ],
      ];
    }

    // Allow modification of delete params.
    $event = new DeleteParamsEvent($indexId, $params);
    $this->eventDispatcher->dispatch($event);
    $params = $event->getParams();

    return $params;
  }

}
