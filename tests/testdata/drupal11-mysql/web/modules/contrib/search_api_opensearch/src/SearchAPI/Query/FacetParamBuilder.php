<?php

namespace Drupal\search_api_opensearch\SearchAPI\Query;

use Drupal\search_api\Query\QueryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Builds facet params.
 */
class FacetParamBuilder {

  /**
   * The default facet size.
   */
  protected const DEFAULT_FACET_SIZE = 10;

  /**
   * The unlimited facet size.
   *
   * By default, OpenSearch does not generate more than 10,000 buckets.
   */
  protected const UNLIMITED_FACET_SIZE = 10000;

  public function __construct(
    #[Autowire(service: 'logger.channel.search_api_opensearch')]
    protected LoggerInterface $logger,
  ) {
  }

  /**
   * Fill the aggregation array of the request.
   *
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   Search API query.
   * @param array $indexFields
   *   The index field, keyed by field identifier.
   *
   * @return array
   *   The facets params.
   */
  public function buildFacetParams(QueryInterface $query, array $indexFields) {
    $params = [];
    $facets = $query->getOption('search_api_facets', []);
    if (empty($facets)) {
      return $params;
    }

    foreach ($facets as $facet_id => $facet) {
      $field = $facet['field'];
      if (!isset($indexFields[$field])) {
        $this->logger->warning('Unknown facet field: %field', ['%field' => $field]);
        continue;
      }
      // Default to term bucket aggregation.
      $params += $this->buildTermBucketAgg($facet_id, $facet);
    }

    return $params;
  }

  /**
   * Builds a bucket aggregation.
   *
   * @param string $facet_id
   *   The key.
   * @param array $facet
   *   The facet.
   *
   * @return array
   *   The bucket aggregation.
   */
  protected function buildTermBucketAgg(string $facet_id, array $facet): array {
    $param = [
      $facet_id => ['terms' => ['field' => $facet['field']]],
    ];

    $size = $facet['limit'] ?? self::DEFAULT_FACET_SIZE;
    $size = (int) $size;

    // Facets uses zero in its configuration form to mean 'No limit'.
    if ($size === 0) {
      $size = self::UNLIMITED_FACET_SIZE;
    }

    // Only set size if it is not the default.
    if ($size !== self::DEFAULT_FACET_SIZE) {
      $param[$facet_id]['terms']['size'] = $size;
    }

    // If operator is OR we need to set to global and nest the agg.
    if (isset($facet['operator']) && $facet['operator'] === 'or') {
      $param = [
        $facet_id . '_global' => [
          'global' => (object) NULL,
          'aggs' => $param,
        ],
      ];
    }

    return $param;
  }

}
