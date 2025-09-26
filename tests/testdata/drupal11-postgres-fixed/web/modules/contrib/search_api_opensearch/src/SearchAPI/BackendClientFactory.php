<?php

namespace Drupal\search_api_opensearch\SearchAPI;

use Drupal\search_api\Utility\FieldsHelperInterface;
use Drupal\search_api_opensearch\Analyser\AnalyserManager;
use Drupal\search_api_opensearch\SearchAPI\Query\QueryParamBuilder;
use Drupal\search_api_opensearch\SearchAPI\Query\QueryResultParser;
use OpenSearch\Client;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Provides a factory for creating a backend client.
 *
 * This is needed because the client is dynamically created based on the
 * connector plugin selected.
 */
class BackendClientFactory {

  public function __construct(
    protected QueryParamBuilder $queryParamBuilder,
    protected QueryResultParser $resultParser,
    protected DeleteParamBuilder $deleteParamBuilder,
    protected IndexParamBuilder $itemParamBuilder,
    #[Autowire(service: 'search_api.fields_helper')]
    protected FieldsHelperInterface $fieldsHelper,
    protected FieldMapper $fieldParamsBuilder,
    #[Autowire(service: 'logger.channel.search_api_opensearch')]
    protected LoggerInterface $logger,
    protected AnalyserManager $analyserManager,
    protected EventDispatcherInterface $eventDispatcher,
  ) {
  }

  /**
   * Creates a new OpenSearch Search API client.
   *
   * @param \OpenSearch\Client $client
   *   The OpenSearch client.
   * @param array $settings
   *   THe backend settings.
   *
   * @return \Drupal\search_api_opensearch\SearchAPI\BackendClientInterface
   *   The backend client.
   */
  public function create(Client $client, array $settings): BackendClientInterface {
    return new BackendClient(
      $this->queryParamBuilder,
      $this->resultParser,
      $this->deleteParamBuilder,
      $this->itemParamBuilder,
      $this->fieldsHelper,
      $this->fieldParamsBuilder,
      $this->logger,
      $client,
      $this->analyserManager,
      $this->eventDispatcher,
      $settings,
    );
  }

}
