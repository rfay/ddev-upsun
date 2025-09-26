<?php

namespace Drupal\search_api_opensearch\SearchAPI\Query;

use Drupal\search_api\Query\QueryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Provides a facet result parser.
 */
class FacetResultParser {

  public function __construct(
    #[Autowire(service: 'logger.channel.search_api_opensearch')]
    protected LoggerInterface $logger,
  ) {
  }

  /**
   * Parse the facet result.
   *
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The query.
   * @param array $response
   *   The response.
   *
   * @return array
   *   The facet data in the format expected by facets module.
   */
  public function parseFacetResult(QueryInterface $query, array $response): array {
    $facetData = [];
    $facets = $query->getOption('search_api_facets', []);
    $indexFields = $query->getIndex()->getFields();

    foreach ($facets as $facet_id => $facet) {
      $type = $indexFields[$facet_id]->getType();

      // Handle 'and' operator.
      if ($facet['operator'] === 'and') {
        $buckets = $response['aggregations'][$facet_id]['buckets'];
        $facetData[$facet_id] = $this->getFacetValues($buckets, $type);
        continue;
      }
      if ($facet['operator'] === 'or') {
        if (!isset($response['aggregations'][$facet_id . '_global'])) {
          $this->logger->warning("Missing global facet ID %facet_id for 'or' operation", ['%facet_id' => $facet_id]);
          continue;
        }
        $buckets = $response['aggregations'][$facet_id . '_global'][$facet_id]['buckets'];
        $facetData[$facet_id] = $this->getFacetValues($buckets, $type);
        continue;
      }
      $this->logger->warning("Invalid operator: %operator", ['%operator' => $facet['operator']]);
    }

    return $facetData;
  }

  /**
   * Transform the aggregation response into an array of values for Facets.
   */
  protected function getFacetValues(array $buckets, string $type): array {
    $terms = [];
    foreach ($buckets as $bucket) {
      if ($type === 'date') {
        // key_as_string is an ISO 8601 date with millisecond precision.
        // EG: 2016-03-04T12:00:00.000Z.
        $datetime = new \DateTimeImmutable($bucket['key_as_string']);
        $filter = $datetime->getTimestamp();
      }
      else {
        $filter = $bucket['key'];
      }

      $terms[] = [
        'count' => $bucket['doc_count'],
        'filter' => '"' . $filter . '"',
      ];
    }
    return $terms;
  }

}
