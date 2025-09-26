<?php

namespace Drupal\search_api_opensearch\SearchAPI;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Utility\Utility as SearchApiUtility;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Provides a param builder for 'More Like This' queries.
 */
class MoreLikeThisParamBuilder {

  /**
   * The required options array keys.
   *
   * @var string[]
   */
  protected array $requiredKeys = ['id', 'fields'];

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    #[Autowire(service: 'logger.channel.search_api_opensearch')]
    protected LoggerInterface $logger,
  ) {
  }

  /**
   * Setup the More like this clause of the OpenSearch query.
   *
   * Adjusts $body to have a more like this query.
   *
   * @param array $mltOptions
   *   An associative array of query options with the keys:
   *   - id: To be used as the like_text in the more_like_this query.
   *   - fields: Array of fields.
   * @param \Drupal\search_api\IndexInterface $index
   *   The search index.
   *
   * @return array
   *   The MLT query params.
   */
  public function buildMoreLikeThisQuery(array $mltOptions, IndexInterface $index): array {

    $mltQuery['more_like_this'] = [];

    $missingKeys = $this->validate($mltOptions);
    if (!empty($missingKeys)) {
      $this->logger->warning("Missing required keys: %keys", ['%keys' => implode(",", $missingKeys)]);
      return [];
    }

    foreach ($index->getDatasources() as $datasource) {
      $id = $mltOptions['id'];
      if ($entity_type_id = $datasource->getEntityTypeId()) {
        $entity = $this->entityTypeManager->getStorage($entity_type_id)->load($id);
        $id = SearchApiUtility::createCombinedId(
          $datasource->getPluginId(),
          $datasource->getItemId($entity->getTypedData())
        );
      }

      $mltQuery['more_like_this']['like'][] = [
        '_id' => $id,
      ];
    }

    // Input parameter: fields.
    $mltQuery['more_like_this']['fields'] = array_values(
      $mltOptions['fields']
    );
    // @todo Make this settings configurable in the view.
    $mltQuery['more_like_this']['max_query_terms'] = 1;
    $mltQuery['more_like_this']['min_doc_freq'] = 1;
    $mltQuery['more_like_this']['min_term_freq'] = 1;

    return $mltQuery;
  }

  /**
   * Validates the MLT options.
   *
   * @param array $mltOptions
   *   The MLT options.
   *
   * @return string[]
   *   Missing required keys.
   */
  protected function validate(array $mltOptions): array {
    $missingKeys = [];
    foreach ($this->requiredKeys as $key) {
      if (!array_key_exists($key, $mltOptions)) {
        $missingKeys[] = $key;
      }
    }
    return $missingKeys;
  }

}
