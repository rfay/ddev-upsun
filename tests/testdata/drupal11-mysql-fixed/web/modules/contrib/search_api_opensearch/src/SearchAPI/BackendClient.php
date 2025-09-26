<?php

namespace Drupal\search_api_opensearch\SearchAPI;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Utility\Error;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Query\QueryInterface;
use Drupal\search_api\Query\ResultSetInterface;
use Drupal\search_api\SearchApiException;
use Drupal\search_api\Utility\FieldsHelperInterface;
use Drupal\search_api_opensearch\Analyser\AnalyserInterface;
use Drupal\search_api_opensearch\Analyser\AnalyserManager;
use Drupal\search_api_opensearch\Event\AlterSettingsEvent;
use Drupal\search_api_opensearch\Event\IndexCreatedEvent;
use Drupal\search_api_opensearch\SearchAPI\Query\QueryParamBuilder;
use Drupal\search_api_opensearch\SearchAPI\Query\QueryResultParser;
use OpenSearch\Client;
use OpenSearch\Exception\OpenSearchExceptionInterface;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Provides an OpenSearch Search API client.
 */
class BackendClient implements BackendClientInterface {

  use DependencySerializationTrait {
    __sleep as traitSleep;
  }

  /**
   * {@inheritdoc}
   */
  public function isAvailable() {
    try {
      return $this->client->ping();
    }
    catch (\Exception $e) {
      $this->logger->error('%type: @message in %function (line %line of %file).', Error::decodeException($e));
      return FALSE;
    }
  }

  /**
   * Constructs a new BackendClient.
   *
   * @param \Drupal\search_api_opensearch\SearchAPI\Query\QueryParamBuilder $queryParamBuilder
   *   The query param builder.
   * @param \Drupal\search_api_opensearch\SearchAPI\Query\QueryResultParser $resultParser
   *   The query result parser.
   * @param \Drupal\search_api_opensearch\SearchAPI\DeleteParamBuilder $deleteParamBuilder
   *   The delete param builder.
   * @param \Drupal\search_api_opensearch\SearchAPI\IndexParamBuilder $indexParamBuilder
   *   The index param builder.
   * @param \Drupal\search_api\Utility\FieldsHelperInterface $fieldsHelper
   *   The fields helper.
   * @param \Drupal\search_api_opensearch\SearchAPI\FieldMapper $fieldParamsBuilder
   *   THe field mapper.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   * @param \OpenSearch\Client $client
   *   The OpenSearch client.
   * @param \Drupal\search_api_opensearch\Analyser\AnalyserManager $analyserManager
   *   Analyser manager.
   * @param \Symfony\Contracts\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   The event dispatcher.
   * @param array $settings
   *   The settings.
   */
  public function __construct(
    protected QueryParamBuilder $queryParamBuilder,
    protected QueryResultParser $resultParser,
    protected DeleteParamBuilder $deleteParamBuilder,
    protected IndexParamBuilder $indexParamBuilder,
    #[Autowire(service: 'search_api.fields_helper')]
    protected FieldsHelperInterface $fieldsHelper,
    protected FieldMapper $fieldParamsBuilder,
    protected LoggerInterface $logger,
    protected Client $client,
    protected AnalyserManager $analyserManager,
    protected EventDispatcherInterface $eventDispatcher,
    protected array $settings = [],
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function indexItems(IndexInterface $index, array $items): array {
    if (empty($items)) {
      return [];
    }

    // Handle someone trying to push to an index that doesn't exist.
    if (!$this->indexExists($index)) {
      $this->addIndex($index);
    }

    $indexId = $this->getIndexId($index);
    $params = $this->indexParamBuilder->buildIndexParams($indexId, $index, $items);

    try {
      $response = $this->client->bulk($params);
      // If there were any errors, log them and throw an exception.
      if (!empty($response['errors'])) {
        foreach ($response['items'] as $item) {
          if (!empty($item['index']['status']) && $item['index']['status'] >= 400) {
            $this->logger->error('%reason %caused_by for id: %id. Status code: %code', [
              '%reason' => $item['index']['error']['reason'],
              '%caused_by' => $item['index']['error']['caused_by']['reason'] ?? '',
              '%id' => $item['index']['_id'],
              '%code' => $item['index']['status'],
            ]);
          }
        }
        throw new SearchApiException('An error occurred indexing items.');
      }
    }
    catch (OpenSearchExceptionInterface | ClientExceptionInterface $e) {
      throw new SearchApiException(sprintf('%s when indexing items in index %s.', $e->getMessage(), $indexId), 0, $e);
    }

    return array_keys($items);

  }

  /**
   * {@inheritdoc}
   */
  public function deleteItems(IndexInterface $index, array $item_ids): void {
    if (empty($item_ids)) {
      return;
    }

    $indexId = $this->getIndexId($index);
    $params = $this->deleteParamBuilder->buildDeleteParams($indexId, $item_ids);
    try {
      $this->client->bulk($params);
    }
    catch (OpenSearchExceptionInterface | ClientExceptionInterface $e) {
      throw new SearchApiException(sprintf('An error occurred deleting items from the index %s.', $indexId), 0, $e);
    }

  }

  /**
   * {@inheritdoc}
   */
  public function search(QueryInterface $query): ResultSetInterface {
    $resultSet = $query->getResults();
    $index = $query->getIndex();
    $indexId = $this->getIndexId($index);
    $params = [
      'index' => $indexId,
    ];
    try {
      // Check index exists.
      if (!$this->client->indices()->exists($params)) {
        $this->logger->warning('Index "%index" does not exist.', ["%index" => $indexId]);
        return $resultSet;
      }
    }
    catch (OpenSearchExceptionInterface | ClientExceptionInterface $e) {
      throw new SearchApiException(sprintf('Network error: %s', $e->getMessage()), 0, $e);
    }

    // Build OpenSearch query.
    $params = $this->queryParamBuilder->buildQueryParams($indexId, $query, $this->settings);

    try {

      // When set to true the search response will always track the number of
      // hits that match the query accurately.
      $params['track_total_hits'] = TRUE;

      // Do search.
      $response = $this->client->search($params);
      $resultSet = $this->resultParser->parseResult($query, $response);

      return $resultSet;
    }
    catch (OpenSearchExceptionInterface | ClientExceptionInterface $e) {
      throw new SearchApiException(sprintf('Error querying index %s', $indexId), 0, $e);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function removeIndex($index): void {
    if (!$this->indexExists($index)) {
      return;
    }
    $indexId = $this->getIndexId($index);
    try {
      $this->client->indices()->delete([
        'index' => [$indexId],
      ]);
    }
    catch (OpenSearchExceptionInterface | ClientExceptionInterface $e) {
      throw new SearchApiException(sprintf('An error occurred removing the index %s.', $indexId), 0, $e);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function addIndex(IndexInterface $index): void {
    $indexId = $this->getIndexId($index);
    if ($this->indexExists($index)) {
      return;
    }

    try {
      $this->client->indices()->create([
        'index' => $indexId,
      ]);
      $this->updateSettings($index);
      $this->updateFieldMapping($index);
      $event = new IndexCreatedEvent($index);
      $this->eventDispatcher->dispatch($event);
    }
    catch (OpenSearchExceptionInterface | ClientExceptionInterface $e) {
      throw new SearchApiException(sprintf('An error occurred creating the index %s.', $indexId), 0, $e);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function updateIndex(IndexInterface $index): void {
    if ($this->indexExists($index)) {
      $result = $this->indexNeedsClearing($index);
      if ($result) {
        $index->clear();
      }
      $this->updateSettings($index);
      $this->updateFieldMapping($index);
      if (!$result) {
        $index->reindex();
      }
    }
    else {
      $this->addIndex($index);
    }
  }

  /**
   * Updates the field mappings for an index.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   The index.
   *
   * @throws \Drupal\search_api\SearchApiException
   *   Thrown when an underlying OpenSearch error occurs.
   */
  public function updateFieldMapping(IndexInterface $index): void {
    $indexId = $this->getIndexId($index);
    try {
      $params = $this->fieldParamsBuilder->mapFieldParams($indexId, $index);
      $this->client->indices()->putMapping($params);
    }
    catch (OpenSearchExceptionInterface | ClientExceptionInterface $e) {
      throw new SearchApiException(sprintf('An error occurred updating field mappings for index %s.', $indexId), 0, $e);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function clearIndex(IndexInterface $index, ?string $datasource_id = NULL): void {
    $this->removeIndex($index);
    $this->addIndex($index);
  }

  /**
   * {@inheritdoc}
   */
  public function indexExists(IndexInterface $index): bool {
    $indexId = $this->getIndexId($index);
    try {
      return $this->client->indices()->exists([
        'index' => $indexId,
      ]);
    }
    catch (OpenSearchExceptionInterface | ClientExceptionInterface $e) {
      throw new SearchApiException(sprintf('An error occurred checking if the index %s exists.', $indexId), 0, $e);
    }
  }

  /**
   * Gets the index ID.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   The index.
   *
   * @return string
   *   The index ID.
   */
  public function getIndexId(IndexInterface $index) {
    return $this->settings['prefix'] . $index->id();
  }

  /**
   * {@inheritdoc}
   *
   * Make sure that the client does not get serialized.
   */
  public function __sleep() {
    $vars = $this->traitSleep();
    unset($vars[array_search('client', $vars)]);
    return $vars;
  }

  /**
   * Updates index settings.
   *
   * @param \Drupal\search_api\IndexInterface $index_param
   *   Index.
   */
  public function updateSettings(IndexInterface $index_param): void {
    $indexId = $this->getIndexId($index_param);
    $params = $this->fieldParamsBuilder->mapFieldParams($indexId, $index_param);
    $analyzers = array_reduce($params['body']['properties'], function (array $carry, array $field_definition) {
      if (isset($field_definition['analyzer'])) {
        $carry[$field_definition['analyzer']] = $field_definition['analyzer_settings'] ?? [];
      }
      return $carry;
    }, []);
    $settings = [];
    foreach ($analyzers as $analyzer_id => $configuration) {
      $analyser = $this->analyserManager->createInstance($analyzer_id, $configuration);
      assert($analyser instanceof AnalyserInterface);
      $settings = NestedArray::mergeDeep($settings, $analyser->getSettings());
    }

    $backendConfig = $index_param->getServerInstance()->getBackendConfig();

    $settings['max_ngram_diff'] = $backendConfig['advanced']['max_ngram_diff'] ?? 1;

    $event = new AlterSettingsEvent($settings, $backendConfig);
    $this->eventDispatcher->dispatch($event);
    $settings = $event->getSettings();

    if (!$settings) {
      // Nothing to push.
      return;
    }

    try {
      $index_param = [
        'index' => $indexId,
      ];
      $this->client->indices()->close($index_param);
      $this->client->indices()->putSettings($index_param + [
        'body' => $settings,
      ]);
    }
    catch (OpenSearchExceptionInterface | ClientExceptionInterface $e) {
      throw new SearchApiException(sprintf('An error occurred updating settings for index %s.', $indexId), 0, $e);
    }
    finally {
      $this->client->indices()->open($index_param);
    }
  }

  /**
   * Determine whether the index needs clearing.
   *
   * OpenSearch does not allow changing existing field
   * mappings with data but does allow adding new fields.
   * OpenSearch has dynamic mappings which means it will automatically
   * add types to fields based on the indexed data if no mapping
   * is explicitly provided. It will also override the mapping
   * if it is wrong, e.g. if you provide a string type but
   * the indexed data for the field is a float it will override the
   * mapping. In order to make sure the index is not cleared for minor
   * changes It's important to make sure the types for fields are correct
   * and that any custom fields are explicitly mapped.
   */
  private function indexNeedsClearing(IndexInterface $index): bool {
    try {
      $openSearchMapping = $this->client->indices()->getMapping([
        'index' => $this->getIndexId($index),
      ]);
    }
    catch (OpenSearchExceptionInterface | ClientExceptionInterface) {
      // If we can't get mappings for some reason, then return early.
      return TRUE;
    }
    $drupalMapping = $this->fieldParamsBuilder->mapFieldParams($this->getIndexId($index), $index);

    // If mappings have yet to be set no need to clear.
    if (!isset($openSearchMapping[$this->getIndexId($index)]['mappings']['properties'])) {
      return FALSE;
    }
    // Recursively check for differences in the mappings between the
    // $openSearchMapping and $drupalMapping arrays. Before comparing,
    // convert $drupalMapping to a json format returned as an
    // associative array, so it is in the same format as the openSearchMapping.
    // For example the putMappings method on the OS client expects an empty
    // object but getMapping returns an empty array. Ensure float values are
    // preserved as json_encode doesn't preserve them by default.
    return $this->mappingsHaveDifferences(
      $openSearchMapping[$this->getIndexId($index)]['mappings']['properties'],
      json_decode(
        json_encode($drupalMapping['body']['properties'], JSON_PRESERVE_ZERO_FRACTION),
        TRUE
      )
    );
  }

  /**
   * Recursively diff an associative array to find differences.
   *
   * If any difference is found bail early.
   */
  private function mappingsHaveDifferences(array $array1, array $array2): bool {
    foreach ($array1 as $key => $value) {
      if (is_array($value)) {
        if (!isset($array2[$key]) || !is_array($array2[$key])) {
          return TRUE;
        }
        else {
          $newDiff = $this->mappingsHaveDifferences($value, $array2[$key]);
          if (!empty($newDiff)) {
            return TRUE;
          }
        }
      }
      elseif (!array_key_exists($key, $array2) || $array2[$key] !== $value) {
        return TRUE;
      }
    }
    return FALSE;
  }

}
