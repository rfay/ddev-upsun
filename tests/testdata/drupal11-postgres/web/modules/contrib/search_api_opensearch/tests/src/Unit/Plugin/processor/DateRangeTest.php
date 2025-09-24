<?php

declare(strict_types=1);

namespace Drupal\Tests\search_api_opensearch\Unit\Plugin\processor;

use Drupal\Tests\UnitTestCase;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Item\Field;
use Drupal\search_api\Item\Item;
use Drupal\search_api\Utility\FieldsHelper;
use Drupal\search_api_opensearch\Plugin\search_api\processor\DateRange;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * Tests the date range processor.
 *
 * @coversDefaultClass \Drupal\search_api_opensearch\Plugin\search_api\processor\DateRange
 * @group search_api_opensearch
 */
class DateRangeTest extends UnitTestCase {

  use ProphecyTrait;

  /**
   * Test making date range field types compatible with OS.
   *
   * @covers ::preprocessIndexItems
   * @dataProvider dateValueDataProvider
   */
  public function testPreProcessingDateRangeItems(
    array $extractItemResult,
    array $expectedResult,
  ): void {

    $dataSource = $this->prophesize(DatasourceInterface::class);
    $dataSource->getPluginId()->willReturn('entity:node');

    $index = $this->prophesize(IndexInterface::class);
    $indexId = "index_" . $this->randomMachineName();
    $index->id()->willReturn($indexId);

    $itemId = "item1_" . $this->randomMachineName();

    $item = (new Item($index->reveal(), $itemId, $dataSource->reveal()))
      ->setFieldsExtracted(TRUE)
      ->setField(
        'event_date_range',
        (new Field($index->reveal(), 'event_date_range'))
          ->setType("search_api_opensearch_date_range")
          ->setPropertyPath('field_event_instance')
      );

    $fieldsHelper = $this->prophesize(FieldsHelper::class);
    $fieldsHelper->extractItemValues([$item], [
      'entity:node' => [
        'field_event_instance:value' => 'start',
        'field_event_instance:end_value' => 'end',
      ],
    ])->willReturn($extractItemResult);

    $dateRange = new DateRange([], 'search_api_opensearch_date_range', []);
    $dateRange->setFieldsHelper($fieldsHelper->reveal());

    $dateRange->preprocessIndexItems([$item]);

    $fieldValues = $item->getField('event_date_range')->getValues();

    $this->assertEquals($expectedResult, $fieldValues);

  }

  /**
   * Test item values are isolated.
   *
   * @link https://www.drupal.org/project/search_api_opensearch/issues/3324701
   * @covers ::preprocessIndexItems
   */
  public function testProcessingMultipleItems(): void {

    $dataSource = $this->prophesize(DatasourceInterface::class);
    $dataSource->getPluginId()->willReturn('entity:node');

    $index = $this->prophesize(IndexInterface::class);
    $indexId = "index_" . $this->randomMachineName();
    $index->id()->willReturn($indexId);

    $item1Id = "item1_" . $this->randomMachineName();
    $item2Id = "item2_" . $this->randomMachineName();

    $item1 = (new Item($index->reveal(), $item1Id, $dataSource->reveal()))
      ->setFieldsExtracted(TRUE)
      ->setField(
        'event_date_range',
        (new Field($index->reveal(), 'event_date_range'))
          ->setType("search_api_opensearch_date_range")
          ->setPropertyPath('field_event_instance')
      );

    $item2 = (new Item($index->reveal(), $item2Id, $dataSource->reveal()))
      ->setFieldsExtracted(TRUE)
      ->setField(
        'event_date_range',
        (new Field($index->reveal(), 'event_date_range'))
          ->setType("search_api_opensearch_date_range")
          ->setPropertyPath('field_event_instance')
      );

    $fieldsHelper = $this->prophesize(FieldsHelper::class);
    $fieldsHelper->extractItemValues([$item1], [
      'entity:node' => [
        'field_event_instance:value' => 'start',
        'field_event_instance:end_value' => 'end',
      ],
    ])->willReturn([
      [
        'start' => [
          '2022-07-01T09:30:00',
        ],
        'end' => [
          '2022-07-01T13:00:00',
        ],
      ],
    ]);

    $fieldsHelper->extractItemValues([$item2], [
      'entity:node' => [
        'field_event_instance:value' => 'start',
        'field_event_instance:end_value' => 'end',
      ],
    ])->willReturn([
      [
        'start' => [],
        'end' => [],
      ],
    ]);

    $dateRange = new DateRange([], 'search_api_opensearch_date_range', []);
    $dateRange->setFieldsHelper($fieldsHelper->reveal());

    $dateRange->preprocessIndexItems([$item1, $item2]);

    $item1FieldValues = $item1->getField('event_date_range')->getValues();
    $item2FieldValues = $item2->getField('event_date_range')->getValues();

    $this->assertEquals([
      [
        'gte' => '2022-07-01T09:30:00',
        'lte' => '2022-07-01T13:00:00',
      ],
    ], $item1FieldValues);

    $this->assertEmpty($item2FieldValues);

  }

  /**
   * Provides dummy date range data.
   */
  public static function dateValueDataProvider(): array {
    return [
      'Single date range' => [
        [
          [
            'start' => [
              '2022-07-01T09:30:00',
            ],
            'end' => [
              '2022-07-01T13:00:00',
            ],
          ],
        ],
        [
          [
            'gte' => '2022-07-01T09:30:00',
            'lte' => '2022-07-01T13:00:00',
          ],
        ],
      ],
      'Multiple date ranges' => [
        [
          [
            'start' => [
              '2022-07-01T09:30:00',
              '2022-08-05T09:30:00',
            ],
            'end' => [
              '2022-07-01T13:00:00',
              '2022-08-05T13:00:00',
            ],
          ],
        ],
        [
          [
            'gte' => '2022-07-01T09:30:00',
            'lte' => '2022-07-01T13:00:00',
          ],
          [
            'gte' => '2022-08-05T09:30:00',
            'lte' => '2022-08-05T13:00:00',
          ],
        ],
      ],
      'No date ranges' => [
        [
          [
            'start' => [],
            'end' => [],
          ],
        ],
        [],
      ],
    ];
  }

}
