<?php

declare(strict_types=1);

namespace Drupal\Tests\search_api_opensearch\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\search_api\DataType\DataTypeInterface;
use Drupal\search_api\DataType\DataTypePluginManager;

/**
 * Tests the data types.
 *
 * @group search_api_opensearch
 */
class DataTypeTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['search_api', 'search_api_opensearch'];

  /**
   * The data type plugin manager.
   *
   * @var \Drupal\search_api\DataType\DataTypePluginManager
   */
  protected DataTypePluginManager $pluginManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->pluginManager = $this->container->get('plugin.manager.search_api.data_type');
  }

  /**
   * Tests the data types.
   *
   * @dataProvider provideDataTypes
   */
  public function testDataType(string $pluginId) {
    $definitions = $this->pluginManager->getDefinitions();
    $this->assertArrayHasKey($pluginId, $definitions);
    $definition = $definitions[$pluginId];
    $this->assertEquals($pluginId, $definition['id']);
    $this->assertEquals('search_api_opensearch', $definition['provider']);
    $plugin = $this->pluginManager->createInstance($pluginId);
    $this->assertInstanceOf(DataTypeInterface::class, $plugin);
  }

  /**
   * Data provider for data types.
   */
  public static function provideDataTypes(): array {
    return [
      ['object'],
      ['search_api_opensearch_date_range'],
      ['search_api_opensearch_date_range'],
      ['search_api_opensearch_edge_ngram'],
      ['search_api_opensearch_ngram'],
      ['search_api_opensearch_search_as_you_type'],
    ];
  }

}
