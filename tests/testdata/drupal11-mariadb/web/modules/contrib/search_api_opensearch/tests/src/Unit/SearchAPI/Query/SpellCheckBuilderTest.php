<?php

declare(strict_types=1);

namespace Drupal\Tests\search_api_opensearch\Unit\SearchAPI\Query;

use Drupal\Tests\UnitTestCase;
use Drupal\search_api\Query\QueryInterface;
use Drupal\search_api_opensearch\SearchAPI\Query\SpellCheckBuilder;

/**
 * Tests the spell check builder.
 *
 * @coversDefaultClass \Drupal\search_api_opensearch\SearchAPI\Query\SpellCheckBuilder
 * @group search_api_opensearch
 */
class SpellCheckBuilderTest extends UnitTestCase {

  /**
   * @covers ::setSpellCheckQuery
   */
  public function testSetSpellCheckQuery() {
    $builder = new SpellCheckBuilder();

    // We can't use prophecy here like the other tests in this module do as
    // getFulltextFields returns an array by reference, and prophecy doesn't
    // allow that.
    $query = $this->createMock(QueryInterface::class);
    $query->method('getFulltextFields')->willReturn([
      'field1' => 'field1',
      'field2' => 'field2',
    ]);
    $query->method('getOption')->willReturn([
      'keys' => ['keys1', 'keys2'],
      'count' => 1,
    ]);

    $expected = [
      'field1' => [
        'text' => 'keys1 keys2',
        'term' => [
          'field' => 'field1',
          'size' => 1,
        ],
      ],
      'field2' => [
        'text' => 'keys1 keys2',
        'term' => [
          'field' => 'field2',
          'size' => 1,
        ],
      ],
    ];

    $this->assertEquals($expected, $builder->setSpellCheckQuery($query));
  }

}
