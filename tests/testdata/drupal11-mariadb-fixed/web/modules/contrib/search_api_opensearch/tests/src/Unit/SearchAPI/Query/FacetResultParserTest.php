<?php

declare(strict_types=1);

namespace Drupal\Tests\search_api_opensearch\Unit\SearchAPI\Query;

use Drupal\Tests\UnitTestCase;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Item\FieldInterface;
use Drupal\search_api\Query\QueryInterface;
use Drupal\search_api_opensearch\SearchAPI\Query\FacetResultParser;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Log\LoggerInterface;

/**
 * Tests the facets result parser.
 *
 * @coversDefaultClass \Drupal\search_api_opensearch\SearchAPI\Query\FacetResultParser
 * @group search_api_opensearch
 */
class FacetResultParserTest extends UnitTestCase {

  use ProphecyTrait;

  /**
   * @covers ::parseFacetResult
   */
  public function testParseFacetResult() {
    $logger = $this->prophesize(LoggerInterface::class);
    $parser = new FacetResultParser($logger->reveal());

    $query = $this->prophesize(QueryInterface::class);
    $query->getOption('search_api_facets', [])
      ->willReturn([
        'facet1' => [
          'field' => 'field1',
          'operator' => 'and',
        ],
        'facet2' => [
          'field' => 'field1',
          'operator' => 'or',
        ],
        'facet3' => [
          'field' => 'field3',
          'operator' => 'and',
        ],
      ]);

    $index = $this->prophesize(IndexInterface::class);
    $query->getIndex()->willReturn($index);

    $field1 = $this->prophesize(FieldInterface::class);
    $field1->getType()->willReturn('string');
    $field2 = $this->prophesize(FieldInterface::class);
    $field2->getType()->willReturn('string');
    $field3 = $this->prophesize(FieldInterface::class);
    $field3->getType()->willReturn('date');

    $index->getFields()->willReturn([
      'facet1' => $field1,
      'facet2' => $field2,
      'facet3' => $field3,
    ]);

    $response = [
      'aggregations' => [
        'facet1' => [
          'doc_count_error_upper_bound' => 0,
          'sum_other_doc_count' => 0,
          'buckets' => [
            [
              'key' => 'foo',
              'doc_count' => 100,
            ],
            [
              'key' => 'bar',
              'doc_count' => 200,
            ],
          ],
        ],
        'facet2_global' => [
          'facet2' => [
            'buckets' => [
              [
                'key' => 'whizz',
                'doc_count' => 400,
              ],
            ],
          ],
        ],
        'facet3' => [
          'doc_count_error_upper_bound' => 0,
          'sum_other_doc_count' => 0,
          'buckets' => [
            [
              'key' => 1704974400000,
              'key_as_string' => '2024-01-11T12:00:00.000Z',
              'doc_count' => 3,
            ],
            [
              'key' => 1706184000000,
              'key_as_string' => '2024-01-25T12:00:00.000Z',
              'doc_count' => 2,
            ],
          ],
        ],
      ],
    ];

    $facetData = $parser->parseFacetResult($query->reveal(), $response);

    $expected = [
      'facet1' => [
        [
          'count' => 100,
          'filter' => '"foo"',
        ],
        [
          'count' => 200,
          'filter' => '"bar"',
        ],
      ],
      'facet2' => [
        [
          'count' => 400,
          'filter' => '"whizz"',
        ],
      ],
      'facet3' => [
        [
          'count' => 3,
          'filter' => '"1704974400"',
        ],
        [
          'count' => 2,
          'filter' => '"1706184000"',
        ],
      ],
    ];
    $this->assertNotEmpty($facetData);
    $this->assertEquals($expected, $facetData);
  }

}
