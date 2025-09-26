<?php

declare(strict_types=1);

namespace Drupal\Tests\search_api_opensearch\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\search_api\Functional\ExampleContentTrait;
use Drupal\search_api\Utility\Utility;
use Drupal\search_api_opensearch\SearchAPI\MoreLikeThisParamBuilder;

/**
 * Tests the more like this param builder.
 *
 * @group search_api_opensearch
 */
class MoreLikeThisParamBuilderKernelTest extends KernelTestBase {

  use OpenSearchTestTrait;
  use ExampleContentTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'search_api',
    'user',
    'system',
    'entity_test',
    'filter',
    'text',
    'search_api_test_example_content',
    'search_api_opensearch',
    'search_api_opensearch_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected ?string $serverId = 'opensearch_server';

  /**
   * {@inheritdoc}
   */
  protected ?string $indexId = 'test_opensearch_index';

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->installSchema('search_api', ['search_api_item']);
    $this->installSchema('user', ['users_data']);
    $this->installEntitySchema('entity_test_mulrev_changed');
    $this->installEntitySchema('search_api_task');
    $this->installConfig('search_api_test_example_content');
    $this->installConfig('search_api');

    // Do not use a batch for tracking the initial items after creating an
    // index when running the tests via the GUI. Otherwise, it seems Drupal's
    // Batch API gets confused and the test fails.
    if (!Utility::isRunningInCli()) {
      \Drupal::state()->set('search_api_use_tracking_batch', FALSE);
    }

    $this->setUpExampleStructure();
    $this->installConfig([
      'search_api_opensearch',
      'search_api_opensearch_test',
    ]);

    $this->recreateIndex();
    $this->insertExampleContent();
  }

  /**
   * Tests the build function.
   */
  public function testBuild(): void {
    $mltBuilder = $this->container->get('search_api_opensearch.more_like_this_param_builder');
    assert($mltBuilder instanceof MoreLikeThisParamBuilder);
    $id = array_key_first($this->ids);
    $this->assertNotNull($id);
    $params = $mltBuilder->buildMoreLikeThisQuery([
      'id' => $id,
      'fields' => ['*'],
    ], $this->getIndex());
    $expected = [
      'more_like_this' => [
        'like' => [
          [
            '_id' => 'entity:entity_test_mulrev_changed/1:en',
          ],
        ],
        'fields' => ['*'],
        'max_query_terms' => 1,
        'min_doc_freq' => 1,
        'min_term_freq' => 1,
      ],
    ];
    $this->assertEquals($expected, $params);
  }

}
