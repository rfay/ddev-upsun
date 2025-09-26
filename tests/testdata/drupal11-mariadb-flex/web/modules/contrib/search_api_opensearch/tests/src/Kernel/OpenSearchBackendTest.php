<?php

declare(strict_types=1);

namespace Drupal\Tests\search_api_opensearch\Kernel;

use Drupal\Tests\search_api\Functional\ExampleContentTrait;
use Drupal\Tests\search_api\Kernel\BackendTestBase;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\Entity\Server;
use Drupal\search_api\Query\QueryInterface;
use Drupal\search_api_opensearch_test\EventSubscriber\OpenSearchEventSubscribers;
use OpenSearch\Client;

/**
 * Tests the end-to-end functionality of the backend.
 *
 * @group search_api_opensearch
 */
class OpenSearchBackendTest extends BackendTestBase {

  use OpenSearchTestTrait;
  use ExampleContentTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'search_api_opensearch',
    'search_api_opensearch_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected $serverId = 'opensearch_server';

  /**
   * {@inheritdoc}
   */
  protected $indexId = 'test_opensearch_index';

  /**
   * The index prefix.
   */
  protected string $prefix = 'test_';

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->installConfig([
      'search_api_opensearch',
      'search_api_opensearch_test',
    ]);
  }

  /**
   * Tests various indexing scenarios for the search backend.
   *
   * Uses a single method to save time.
   */
  public function testBackend() {
    $this->recreateIndex();
    $this->insertExampleContent();
    $this->checkDefaultServer();
    $this->checkServerBackend();
    $this->checkDefaultIndex();
    $this->updateIndex();
    $this->searchNoResults();
    $this->indexItems($this->indexId);
    $this->refreshIndices();
    $this->searchSuccess();
  }

  /**
   * Tests making changes to fields on the index.
   *
   * Adding a new field should not clear the index
   * but changing an existing field mapping should.
   */
  public function testUpdatingIndex(): void {
    $server = Server::load($this->serverId);
    /** @var \Drupal\search_api_opensearch\Plugin\search_api\backend\OpenSearchBackend $backend */
    $backend = $server->getBackend();
    $osClient = $backend->getClient();
    $this->recreateIndex();
    $this->insertExampleContent();
    $this->indexItems($this->indexId);
    $this->refreshIndices();

    // Check that all our items are indexed both
    // by tracker and on server.
    $index = Index::load($this->indexId);
    $tracker = $index->getTrackerInstance();
    $this->assertEquals(5, $tracker->getIndexedItemsCount());
    $result = $this->queryAllResults($osClient);
    $this->assertEquals(5, $result['hits']['total']['value']);

    // Add a new field to the index.
    FieldStorageConfig::create([
      'field_name' => 'new_field',
      'entity_type' => 'entity_test_mulrev_changed',
      'type' => 'string',
    ])->save();
    FieldConfig::create([
      'entity_type' => 'entity_test_mulrev_changed',
      'field_name' => 'new_field',
      'bundle' => 'item',
    ])->save();
    /** @var \Drupal\search_api\Utility\FieldsHelperInterface $fieldsHelper */
    $fieldsHelper = \Drupal::getContainer()->get('search_api.fields_helper');
    $field = $fieldsHelper->createField($index, 'new_field', [
      'label' => 'New field',
      'type' => 'string',
      'property_path' => 'new_field',
      'datasource_id' => 'entity:entity_test_mulrev_changed',
    ]);
    $index->addField($field);
    $index->save();

    // Now check that the tracker is reset but items remain on
    // server.
    $this->assertEquals(0, $tracker->getIndexedItemsCount());
    $result = $this->queryAllResults($osClient);
    $this->assertEquals(5, $result['hits']['total']['value']);

    // Re-index the items for next test.
    $this->indexItems($this->indexId);
    $this->refreshIndices();
    $this->assertEquals(5, $tracker->getIndexedItemsCount());
    $result = $this->queryAllResults($osClient);
    $this->assertEquals(5, $result['hits']['total']['value']);

    // Changing the fields type should clear the index completely
    // as OS doesn't allow changes to existing fields with data.
    $field = $index->getField('new_field');
    $field->setType('text');
    $index->addField($field);
    $index->save();
    $this->assertEquals(0, $tracker->getIndexedItemsCount());
    $result = $this->queryAllResults($osClient);
    $this->assertEquals(0, $result['hits']['total']['value']);

    // Re-index the items for next test.
    $this->indexItems($this->indexId);
    $this->refreshIndices();
    $this->assertEquals(5, $tracker->getIndexedItemsCount());
    $result = $this->queryAllResults($osClient);
    $this->assertEquals(5, $result['hits']['total']['value']);

    // Add another new field and change the first field
    // back to its original type.
    FieldStorageConfig::create([
      'field_name' => 'new_field_2',
      'entity_type' => 'entity_test_mulrev_changed',
      'type' => 'string',
    ])->save();
    FieldConfig::create([
      'entity_type' => 'entity_test_mulrev_changed',
      'field_name' => 'new_field_2',
      'bundle' => 'item',
    ])->save();
    $fieldsHelper = \Drupal::getContainer()->get('search_api.fields_helper');
    $field = $fieldsHelper->createField($index, 'new_field_2', [
      'label' => 'New field 2',
      'type' => 'string',
      'property_path' => 'new_field_2',
      'datasource_id' => 'entity:entity_test_mulrev_changed',
    ]);
    $index->addField($field);
    $existingField = $index->getField('new_field');
    $existingField->setType('string');
    $index->addField($existingField);
    $index->save();

    // The index should be completely cleared because we have both
    // a new field and a changed field.
    $this->assertEquals(0, $tracker->getIndexedItemsCount());
    $result = $this->queryAllResults($osClient);
    $this->assertEquals(0, $result['hits']['total']['value']);
  }

  /**
   * Returns the OS response for all documents.
   */
  protected function queryAllResults(Client $client): array {
    return $client->search([
      'index' => $this->prefix . $this->indexId,
      'body' => [
        'query' => [
          'match_all' => new \StdClass(),
        ],
      ],
      'track_total_hits' => TRUE,
    ]);
  }

  /**
   * Re-creates the index.
   *
   * @throws \Drupal\search_api\SearchApiException
   */
  protected function recreateIndex() {
    $server = Server::load($this->serverId);
    /** @var \Drupal\search_api_opensearch\Plugin\search_api\backend\OpenSearchBackend $backend */
    $backend = $server->getBackend();
    $index = Index::load($this->indexId);
    $client = $backend->getBackendClient();
    if ($client->indexExists($index)) {
      $client->removeIndex($index);
    }
    $client->addIndex($index);
    $this->assertEquals($this->indexId, OpenSearchEventSubscribers::getIndex()->id());
  }

  /**
   * Tests whether some test searches have the correct results.
   */
  protected function searchSuccess() {
    // @codingStandardsIgnoreStart

    $results = $this->buildSearch('test')
      ->range(1, 2)
      ->execute();
    $this->assertEquals(4, $results->getResultCount(), 'Search for »test« returned correct number of results.');
    $this->assertEquals($this->getItemIds([
      2,
      3,
    ]), array_keys($results->getResultItems()), 'Search for »test« returned correct result.');
    $this->assertEmpty($results->getIgnoredSearchKeys());
    $this->assertEmpty($results->getWarnings());

    $id = $this->getItemIds([2])[0];
    $this->assertEquals($id, key($results->getResultItems()));
    $this->assertEquals($id, $results->getResultItems()[$id]->getId());
    $this->assertEquals('entity:entity_test_mulrev_changed', $results->getResultItems()[$id]->getDatasourceId());

    $results = $this->buildSearch('test foo')->execute();
    $this->assertResults([1, 2, 4], $results, 'Search for »test foo«');

    $results = $this->buildSearch('foo', ['type,item'])->execute();
    $this->assertResults([1, 2], $results, 'Search for »foo«');

    $keys = [
      '#conjunction' => 'AND',
      'test',
      [
        '#conjunction' => 'OR',
        'baz',
        'foobar',
      ],
      [
        '#conjunction' => 'OR',
        '#negation' => TRUE,
        'bar',
        // cspell:ignore fooblob
        'fooblob',
      ],
    ];
    $results = $this->buildSearch($keys)->execute();
    //    $this->assertResults([4], $results, 'Complex search 1');
    $query = $this->buildSearch();
    $conditions = $query->createConditionGroup('OR');
    $conditions->addCondition('name', 'bar');
    $conditions->addCondition('body', 'bar');
    $query->addConditionGroup($conditions);
    $results = $query->execute();
    $this->assertResults([
      1,
      2,
      3,
      5,
    ], $results, 'Search with multi-field fulltext filter');

    $results = $this->buildSearch()
      ->addCondition('keywords', ['grape', 'apple'], 'IN')
      ->execute();
    $this->assertResults([2, 4, 5], $results, 'Query with IN filter');

    $results = $this->buildSearch()->addCondition('keywords', [
      'grape',
      'apple',
    ], 'NOT IN')->execute();
    $this->assertResults([1, 3], $results, 'Query with NOT IN filter');

    $results = $this->buildSearch()->addCondition('width', [
      '0.9',
      '1.5',
    ], 'BETWEEN')->execute();
    $this->assertResults([4], $results, 'Query with BETWEEN filter');

    $results = $this->buildSearch()
      ->addCondition('width', ['0.9', '1.5'], 'NOT BETWEEN')
      ->execute();
    $this->assertResults([
      1,
      2,
      3,
      5,
    ], $results, 'Query with NOT BETWEEN filter');

    $results = $this->buildSearch()
      ->setLanguages(['und', 'en'])
      ->addCondition('keywords', ['grape', 'apple'], 'IN')
      ->execute();
    $this->assertResults([2, 4, 5], $results, 'Query with IN filter');

    $results = $this->buildSearch()
      ->setLanguages(['und'])
      ->execute();
    $this->assertResults([], $results, 'Query with languages');

    $query = $this->buildSearch();
    $conditions = $query->createConditionGroup('OR')
      ->addCondition('search_api_language', 'und')
      ->addCondition('width', ['0.9', '1.5'], 'BETWEEN');
    $query->addConditionGroup($conditions);
    $results = $query->execute();
    $this->assertResults([4], $results, 'Query with _language filter');

    $results = $this->buildSearch()
      ->addCondition('search_api_language', 'und')
      ->addCondition('width', ['0.9', '1.5'], 'BETWEEN')
      ->execute();
    $this->assertResults([], $results, 'Query with _language filter');

    $results = $this->buildSearch()
      ->addCondition('search_api_language', ['und', 'en'], 'IN')
      ->addCondition('width', ['0.9', '1.5'], 'BETWEEN')
      ->execute();
    $this->assertResults([4], $results, 'Query with _language filter');

    $results = $this->buildSearch()
      ->addCondition('search_api_language', ['und', 'de'], 'NOT IN')
      ->addCondition('width', ['0.9', '1.5'], 'BETWEEN')
      ->execute();
    $this->assertResults([4], $results, 'Query with _language "NOT IN" filter');

    $results = $this->buildSearch()
      ->addCondition('search_api_id', $this->getItemIds([1])[0])
      ->execute();
    $this->assertResults([1], $results, 'Query with _id filter');

    $results = $this->buildSearch()
      ->addCondition('search_api_id', $this->getItemIds([2, 4]), 'NOT IN')
      ->execute();
    $this->assertResults([1, 3, 5], $results, 'Query with _id "NOT IN" filter');

    $results = $this->buildSearch()
      ->addCondition('search_api_id', $this->getItemIds([3])[0], '>')
      ->execute();
    $this->assertResults([
      4,
      5,
    ], $results, 'Query with _id "greater than" filter');

    $results = $this->buildSearch()
      ->addCondition('search_api_datasource', 'foobar')
      ->execute();
    $this->assertResults([], $results, 'Query for a non-existing datasource');

    $results = $this->buildSearch()
      ->addCondition('search_api_datasource', [
        'foobar',
        'entity:entity_test_mulrev_changed',
      ], 'IN')
      ->execute();
    $this->assertResults([
      1,
      2,
      3,
      4,
      5,
    ], $results, 'Query with _id "IN" filter');

    $results = $this->buildSearch()
      ->addCondition('search_api_datasource', [
        'foobar',
        'entity:entity_test_mulrev_changed',
      ], 'NOT IN')
      ->execute();
    $this->assertResults([], $results, 'Query with _id "NOT IN" filter');

    // For a query without keys, all of these except for the last one should
    // have no effect. Therefore, we expect results with IDs in descending
    // order.
    $results = $this->buildSearch(NULL, [], [], FALSE)
      ->sort('search_api_relevance')
      ->sort('search_api_datasource', QueryInterface::SORT_DESC)
      ->sort('search_api_language')
      ->sort('search_api_id', QueryInterface::SORT_DESC)
      ->execute();
    $this->assertResults([5, 4, 3, 2, 1], $results, 'Query with magic sorts');

    // @codingStandardsIgnoreEnd
  }

  /**
   * {@inheritdoc}
   */
  protected function checkServerBackend() {
  }

  /**
   * {@inheritdoc}
   */
  protected function updateIndex() {
  }

  /**
   * {@inheritdoc}
   */
  protected function checkSecondServer() {
  }

  /**
   * {@inheritdoc}
   */
  protected function checkModuleUninstall() {
  }

}
