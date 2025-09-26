<?php

declare(strict_types=1);

namespace Drupal\search_api_opensearch\Plugin\search_api\data_type;

use Drupal\search_api\Plugin\search_api\data_type\TextDataType;

/**
 * Defines a class for n-gram data type.
 *
 * @SearchApiDataType(
 *   id = "search_api_opensearch_edge_ngram",
 *   label = @Translation("Edge N-gram"),
 *   description = @Translation("Edge ngram"),
 *   fallback_type = "text",
 * )
 */
final class EdgeNgramDataType extends TextDataType {

}
