<?php

namespace Drupal\search_api_opensearch\Event;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * Provides a base class for events that alter params.
 */
abstract class BaseParamsEvent extends Event {

  /**
   * BaseParamsEvent constructor.
   *
   * @param string $indexName
   *   The index name.
   * @param array $params
   *   The params.
   */
  public function __construct(
    protected string $indexName,
    protected array $params,
  ) {
  }

  /**
   * Gets the params.
   *
   * @return array
   *   The params.
   */
  public function getParams(): array {
    return $this->params;
  }

  /**
   * Sets the params.
   *
   * @param array $params
   *   The params.
   */
  public function setParams(array $params): void {
    $this->params = $params;
  }

  /**
   * Gets the index name.
   *
   * @return string
   *   The index name.
   */
  public function getIndexName(): string {
    return $this->indexName;
  }

}
