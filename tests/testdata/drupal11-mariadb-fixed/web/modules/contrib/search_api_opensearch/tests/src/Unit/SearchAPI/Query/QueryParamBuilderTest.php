<?php

declare(strict_types=1);

namespace Drupal\Tests\search_api_opensearch\Unit\SearchAPI\Query;

use Drupal\Tests\UnitTestCase;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Item\FieldInterface;
use Drupal\search_api\Query\ConditionGroupInterface;
use Drupal\search_api\Query\QueryInterface;
use Drupal\search_api\Utility\FieldsHelperInterface;
use Drupal\search_api_opensearch\Event\QueryParamsEvent;
use Drupal\search_api_opensearch\SearchAPI\MoreLikeThisParamBuilder;
use Drupal\search_api_opensearch\SearchAPI\Query\FacetParamBuilder;
use Drupal\search_api_opensearch\SearchAPI\Query\FilterBuilder;
use Drupal\search_api_opensearch\SearchAPI\Query\QueryParamBuilder;
use Drupal\search_api_opensearch\SearchAPI\Query\QuerySortBuilder;
use Drupal\search_api_opensearch\SearchAPI\Query\SearchParamBuilder;
use Drupal\search_api_opensearch\SearchAPI\Query\SpellCheckBuilder;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Log\NullLogger;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Tests the query param builder.
 *
 * @coversDefaultClass \Drupal\search_api_opensearch\SearchAPI\Query\QueryParamBuilder
 * @group search_api_opensearch
 */
class QueryParamBuilderTest extends UnitTestCase {

  use ProphecyTrait;

  /**
   * @covers ::
   */
  public function testBuildQueryParams() {
    $indexId = "foo";

    $fieldsHelper = $this->prophesize(FieldsHelperInterface::class);

    $sortBuilder = $this->prophesize(QuerySortBuilder::class);
    $sortBuilder->getSortSearchQuery(Argument::any())
      ->willReturn([]);

    $filterBuilder = $this->prophesize(FilterBuilder::class);
    $filterBuilder->buildFilters(Argument::any(), Argument::any())
      ->willReturn([]);

    $searchParamBuilder = $this->prophesize(SearchParamBuilder::class);
    $searchParamBuilder->buildSearchParams(Argument::any(), Argument::any(), Argument::any())
      ->willReturn([]);

    $mltParamBuilder = $this->prophesize(MoreLikeThisParamBuilder::class);
    $facetParamBuilder = $this->prophesize(FacetParamBuilder::class);
    $spellCheckBuilder = $this->prophesize(SpellCheckBuilder::class);
    $eventDispatcher = $this->prophesize(EventDispatcherInterface::class);

    $logger = new NullLogger();

    $queryParamBuilder = new QueryParamBuilder($fieldsHelper->reveal(), $sortBuilder->reveal(), $filterBuilder->reveal(), $searchParamBuilder->reveal(), $mltParamBuilder->reveal(), $facetParamBuilder->reveal(), $spellCheckBuilder->reveal(), $eventDispatcher->reveal(), $logger);

    $index = $this->prophesize(IndexInterface::class);
    $index->id()->willReturn($indexId);

    $field1 = $this->prophesize(FieldInterface::class);

    $fields = [$field1->reveal()];

    $index->getFields()
      ->willReturn($fields);

    $query = $this->prophesize(QueryInterface::class);
    $query->getOption('offset')
      ->willReturn(0);
    $query->getOption('limit')
      ->willReturn(10);
    $query->getOption('opensearch_exclude_source_fields', Argument::any())
      ->willReturn([]);
    $query->getOption('search_api_mlt')
      ->willReturn(NULL);
    $query->getOption('search_api_facets')
      ->willReturn(NULL);
    $query->getOption('search_api_spellcheck')
      ->willReturn(NULL);
    $query->getLanguages()
      ->willReturn(NULL);
    $query->getIndex()
      ->willReturn($index->reveal());
    $conditionGroup = $this->prophesize(ConditionGroupInterface::class);
    $query->getConditionGroup()
      ->willReturn($conditionGroup->reveal());

    $expected = ['index' => 'foo', 'body' => ['from' => 0, 'size' => 10]];
    $query->setOption('OpenSearchParams', Argument::exact($expected))
      ->willReturn(Argument::any());

    $event = new QueryParamsEvent($indexId, [], $query->reveal());
    $eventDispatcher->dispatch(Argument::any())->willReturn($event);

    $settings = ['fuzziness' => 'auto'];
    $queryParams = $queryParamBuilder->buildQueryParams($indexId, $query->reveal(), $settings);

    $this->assertEquals($expected, $queryParams);

  }

}
