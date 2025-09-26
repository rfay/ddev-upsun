<?php

namespace Drupal\search_api_opensearch\SearchAPI\Query;

use Drupal\search_api\Query\QueryInterface;

/**
 * Provides a spell check builder.
 */
class SpellCheckBuilder {

  /**
   * Set up the SpellCheck clause of the Open Search query.
   *
   * See https://opensearch.org/docs/2.5/opensearch/search/did-you-mean/
   *
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The query.
   *
   * @return array
   *   Array of suggester query.
   */
  public function setSpellCheckQuery(QueryInterface $query): array {
    $suggester_query = [];
    $options = $query->getOption('search_api_spellcheck');
    if (isset($options['keys']) && isset($options['count'])) {
      $terms = implode(' ', $options['keys']);
      foreach ($this->getFulltextFields($query) as $field_name) {
        $suggester_query[$field_name] = [
          'text' => $terms,
          'term' => [
            'field' => $field_name,
            'size' => $options['count'],
          ],
        ];
      }
    }
    return $suggester_query;
  }

  /**
   * Get the full text fields for this search.
   *
   * QueryInterface::getFulltextFields will return NULL if all indexed fulltext
   * fields should be used. In that case, we get the full text fields from the
   * index.
   *
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The query.
   *
   * @return string[]
   *   Array of the fulltext fields that will be searched by this query.
   */
  private function getFullTextFields(QueryInterface $query): array {
    $fullTextFields = $query->getFulltextFields();
    if (is_null($fullTextFields)) {
      $fullTextFields = $query->getIndex()->getFulltextFields();
    }
    return $fullTextFields;
  }

}
