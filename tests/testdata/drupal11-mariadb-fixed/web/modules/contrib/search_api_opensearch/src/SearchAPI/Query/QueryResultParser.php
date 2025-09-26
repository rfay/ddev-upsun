<?php

namespace Drupal\search_api_opensearch\SearchAPI\Query;

use Drupal\search_api\Query\QueryInterface;
use Drupal\search_api\Query\ResultSetInterface;
use Drupal\search_api\Utility\FieldsHelperInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Provides a result set parser.
 */
class QueryResultParser {

  public function __construct(
    #[Autowire(service: 'search_api.fields_helper')]
    protected FieldsHelperInterface $fieldsHelper,
    protected FacetResultParser $facetResultParser,
    protected SpellCheckResultParser $spellCheckResultParser,
  ) {
  }

  /**
   * Parse a OpenSearch response into a ResultSetInterface.
   *
   * @todo Add excerpt handling.
   *
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   Search API query.
   * @param array $response
   *   Raw response array back from OpenSearch.
   *
   * @return \Drupal\search_api\Query\ResultSetInterface
   *   The results of the search.
   */
  public function parseResult(QueryInterface $query, array $response): ResultSetInterface {
    $index = $query->getIndex();

    // Set up the results array.
    $results = $query->getResults();
    $results->setExtraData('opensearch_response', $response);
    $results->setResultCount($response['hits']['total']['value']);
    // Add each search result to the results array.
    if (!empty($response['hits']['hits'])) {
      foreach ($response['hits']['hits'] as $result) {
        $result_item = $this->fieldsHelper->createItem($index, $result['_id']);
        $result_item->setScore($result['_score']);

        // Set each item in _source as a field in Search API.
        foreach ($result['_source'] as $id => $values) {
          // Make everything a multifield.
          if (!is_array($values)) {
            $values = [$values];
          }
          $field = $this->fieldsHelper->createField($index, $id, ['property_path' => $id]);
          $field->setValues($values);
          $result_item->setField($id, $field);
        }

        $results->addResultItem($result_item);
      }
    }

    if (!empty($response['aggregations'])) {
      $facets = $this->facetResultParser->parseFacetResult($query, $response);
      $results->setExtraData('search_api_facets', $facets);
    }

    if (!empty($response['suggest'])) {
      $candidates = $this->spellCheckResultParser->parseSpellCheckResult($query, $response);
      // Set under the search_api_spellcheck->suggestions key.
      $results->setExtraData('search_api_spellcheck', [
        'suggestions' => $candidates,
      ]);
    }

    return $results;
  }

}
