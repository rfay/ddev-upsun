<?php

declare(strict_types=1);

namespace Drupal\search_api_opensearch\Plugin\OpenSearch\Analyser;

use Drupal\search_api_opensearch\Analyser\AnalyserBase;

/**
 * Defines an Edge N-gram analyser.
 *
 * @OpenSearchAnalyser(
 *   id = \Drupal\search_api_opensearch\Plugin\OpenSearch\Analyser\EdgeNgram::PLUGIN_ID,
 *   label = @Translation("Edge N-gram analyzer"),
 * )
 */
final class EdgeNgram extends AnalyserBase {

  /**
   * The plugin ID.
   */
  public const PLUGIN_ID = 'edge_ngram_analyzer';

  /**
   * The filter ID.
   */
  public const FILTER_ID = 'edge_ngram_filter';

  /**
   * {@inheritdoc}
   */
  public function getSettings(): array {
    return [
      'analysis' => [
        'filter' => [
          self::FILTER_ID => [
            'type' => 'edge_ngram',
            'min_gram' => 1,
            'max_gram' => 20,
          ],
        ],
        'analyzer' => [
          self::PLUGIN_ID => [
            'type' => 'custom',
            'tokenizer' => 'standard',
            'filter' => ['lowercase', 'asciifolding', self::FILTER_ID],
          ],
        ],
      ],
    ];
  }

}
