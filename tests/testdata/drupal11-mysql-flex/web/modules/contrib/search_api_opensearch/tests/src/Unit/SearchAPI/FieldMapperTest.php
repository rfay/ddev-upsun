<?php

declare(strict_types=1);

namespace Drupal\Tests\search_api_opensearch\Unit\SearchAPI;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Item\Field;
use Drupal\search_api\Utility\DataTypeHelperInterface;
use Drupal\search_api\Utility\FieldsHelper;
use Drupal\search_api\Utility\ThemeSwitcherInterface;
use Drupal\search_api_opensearch\Event\FieldMappingEvent;
use Drupal\search_api_opensearch\SearchAPI\FieldMapper;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Tests the field mapper.
 *
 * @coversDefaultClass \Drupal\search_api_opensearch\SearchAPI\FieldMapper
 * @group search_api_opensearch
 */
class FieldMapperTest extends UnitTestCase {

  use ProphecyTrait;

  /**
   * @covers ::mapFieldParams
   */
  public function testMapFieldParams() {

    $entityTypeManager = $this->prophesize(EntityTypeManagerInterface::class);
    $entityFieldManager = $this->prophesize(EntityFieldManagerInterface::class);
    $entityTypeBundleInfo = $this->prophesize(EntityTypeBundleInfoInterface::class);
    $dataTypeHelper = $this->prophesize(DataTypeHelperInterface::class);
    $themeSwitcher = $this->prophesize(ThemeSwitcherInterface::class);
    $fieldsHelper = new FieldsHelper($entityTypeManager->reveal(), $entityFieldManager->reveal(), $entityTypeBundleInfo->reveal(), $dataTypeHelper->reveal(), $themeSwitcher->reveal());

    $event = $this->prophesize(FieldMappingEvent::class);
    $eventDispatcher = $this->prophesize(EventDispatcherInterface::class);
    $eventDispatcher->dispatch(Argument::any())->willReturn($event->reveal());
    $fieldMapper = new FieldMapper($fieldsHelper, $eventDispatcher->reveal());

    $index = $this->prophesize(IndexInterface::class);

    $field1Id = $this->randomMachineName(8);
    $field1 = (new Field($index->reveal(), $field1Id))
      ->setType("text")
      ->setBoost(1.1);

    $field2Id = $this->randomMachineName(8);
    $field2 = (new Field($index->reveal(), $field2Id))
      ->setType("string");

    $fields = [
      $field1Id => $field1,
      $field2Id => $field2,
    ];

    $indexId = $this->randomMachineName();
    $index->id()->willReturn($indexId);
    $index->getFields()->willReturn($fields);

    $params = $fieldMapper->mapFieldParams($indexId, $index->reveal());

    $expectedParams = [
      "index" => $indexId,
      "body" => [
        "properties" => [
          "id" => [
            "type" => "keyword",
            "index" => "true",
          ],
          $field1Id => [
            'type' => 'text',
            'boost' => 1.1,
            'fields' => [
              'keyword' => ['type' => 'keyword', 'ignore_above' => 256],
            ],
          ],
          $field2Id => [
            'type' => 'keyword',
          ],
          'search_api_id' => [
            "type" => "keyword",
          ],
          'search_api_datasource' => [
            "type" => "keyword",
          ],
          'search_api_language' => [
            "type" => "keyword",
          ],
          'search_api_boost' => [
            "type" => "rank_feature",
          ],
        ],
      ],
    ];
    $this->assertEquals($expectedParams, $params);

  }

}
