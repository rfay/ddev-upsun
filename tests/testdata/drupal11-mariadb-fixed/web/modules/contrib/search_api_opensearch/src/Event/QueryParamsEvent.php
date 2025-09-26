<?php

namespace Drupal\search_api_opensearch\Event;

use Drupal\search_api\Query\QueryInterface;

/**
 * Event triggered when search params are built.
 */
class QueryParamsEvent extends BaseParamsEvent {

  public function __construct(
    string $indexName,
    array $params,
    protected readonly ?QueryInterface $query = NULL,
  ) {
    parent::__construct($indexName, $params);
    if (!isset($this->query)) {
      @trigger_error('Calling ' . __METHOD__ . '() without a $query parameter is deprecated in search_api_opensearch:2.1.1 and will be required in search_api_opensearch:3.0.0. See https://www.drupal.org/node/3484242', E_USER_DEPRECATED);
    }
  }

  /**
   * Gets the query.
   *
   * @return \Drupal\search_api\Query\QueryInterface|null
   *   Query object.
   */
  public function getQuery(): ?QueryInterface {
    return $this->query;
  }

}
