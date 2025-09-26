<?php

declare(strict_types=1);

namespace Drupal\Tests\search_api_opensearch\Unit\SearchAPI\Query;

use Drupal\Tests\UnitTestCase;
use Drupal\search_api\Query\QueryInterface;
use Drupal\search_api_opensearch\SearchAPI\Query\SpellCheckResultParser;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * Tests the facets result parser.
 *
 * @coversDefaultClass \Drupal\search_api_opensearch\SearchAPI\Query\SpellCheckResultParser
 * @group search_api_opensearch
 */
class SpellCheckResultParserTest extends UnitTestCase {

  use ProphecyTrait;

  /**
   * @covers ::parseSpellCheckResult
   */
  public function testParseSpellCheckResult(): void {
    $parser = new SpellCheckResultParser();

    $query = $this->prophesize(QueryInterface::class);
    $query->getOption('search_api_spellcheck')->willReturn([
      'keys' => ['keys1', 'keys2'],
      'count' => 1,
    ]);

    // cspell:ignore cyclong
    $response = [
      'suggest' => [
        'field_1' => [
          [
            'text' => 'cyclong',
            'offset' => 0,
            'length' => 7,
            'options' => [
              [
                'text' => 'cycling',
                'score' => 0.85,
                'freq' => 7,
              ],
            ],
          ],
        ],
        'field_2' => [
          [
            'text' => 'cyclong',
            'offset' => 0,
            'length' => 7,
            'options' => [
              [
                'text' => 'cyclone',
                'score' => 0.95,
                'freq' => 2,
              ],
            ],
          ],
        ],
      ],
    ];

    // Expect cyclone, as it scores higher than cycling.
    $expected = [
      'cyclong' => [
        'cyclone',
      ],
    ];

    $result = $parser->parseSpellCheckResult(
      $query->reveal(),
      $response
    );
    $this->assertEquals($expected, $result);

    // Check when count is not specified.
    $query->getOption('search_api_spellcheck')->willReturn([
      'keys' => ['keys1', 'keys2'],
    ]);

    $expected = [
      'cyclong' => [
        'cyclone',
        'cycling',
      ],
    ];

    $result = $parser->parseSpellCheckResult(
      $query->reveal(),
      $response
    );
    $this->assertEquals($expected, $result);
  }

}
