<?php

namespace Drupal\search_api_opensearch\SearchAPI\Query;

use Drupal\search_api\Query\Condition;
use Drupal\search_api\Query\ConditionGroupInterface;
use Drupal\search_api\SearchApiException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Provides a query filter builder.
 */
class FilterBuilder {

  public function __construct(
    #[Autowire(service: 'logger.channel.search_api_opensearch')]
    protected LoggerInterface $logger,
  ) {
  }

  /**
   * Recursively parse Search API condition group.
   *
   * @param \Drupal\search_api\Query\ConditionGroupInterface $condition_group
   *   The condition group object that holds all conditions that should be
   *   expressed as filters.
   * @param \Drupal\search_api\Item\FieldInterface[] $index_fields
   *   An array of all indexed fields for the index, keyed by field identifier.
   *
   * @return array
   *   Array of filter parameters to apply to query based on the given Search
   *   API condition group.
   *
   * @throws \Drupal\search_api\SearchApiException
   *   Thrown if an invalid condition occurs.
   */
  public function buildFilters(ConditionGroupInterface $condition_group, array $index_fields) {

    $filters = [];
    $backend_fields = [
      'search_api_id' => TRUE,
      'search_api_language' => TRUE,
    ];

    if (!empty($condition_group->getConditions())) {
      $conjunction = $condition_group->getConjunction();

      foreach ($condition_group->getConditions() as $condition) {
        $filter = NULL;

        // Simple filter [field_id, value, operator].
        if ($condition instanceof Condition) {

          if (!$condition->getField() || !$condition->getValue() || !$condition->getOperator()) {
            // @todo When using views the sort field is coming as a filter and
            // messing with this section.
            $this->logger->warning("Invalid condition %condition", ['%condition' => $condition]);
          }

          $field_id = $condition->getField();
          if (!isset($index_fields[$field_id]) && !isset($backend_fields[$field_id])) {
            throw new SearchApiException(sprintf("Invalid field '%s' in search filter", $field_id));
          }

          // Check operator.
          if (!$condition->getOperator()) {
            throw new SearchApiException(sprintf('Unspecified filter operator for field "%s"', $field_id));
          }

          // For some data type, we need to do conversions here.
          if (isset($index_fields[$field_id])) {
            $field = $index_fields[$field_id];
            if ($field->getType() === 'boolean') {
              $condition->setValue((bool) $condition->getValue());
            }
          }

          // Builder filter term.
          $filter = $this->buildFilterTerm($condition);

          if (!empty($filter)) {
            $filters[] = $filter;
          }
        }
        // Nested filters.
        elseif ($condition instanceof ConditionGroupInterface) {
          $nested_filters = $this->buildFilters(
            $condition,
            $index_fields
          );

          if (!empty($nested_filters)) {
            $filters[] = $nested_filters;
          }
        }
      }

      // If we have more than 1 filter, we need to nest with a conjunction.
      if (count($filters) > 1) {
        $filters = $this->wrapWithConjunction($filters, $conjunction);
      }
      else {
        // Return just the filter.
        $filters = array_pop($filters);
      }
    }

    return $filters;
  }

  /**
   * Build a filter term from a Search API condition.
   *
   * @param \Drupal\search_api\Query\Condition $condition
   *   The condition.
   *
   * @return array
   *   The filter term array.
   *
   * @throws \Exception
   */
  public function buildFilterTerm(Condition $condition) {
    // Handles "empty", "not empty" operators.
    if (is_null($condition->getValue())) {
      return match ($condition->getOperator()) {
        '<>' => ['exists' => ['field' => $condition->getField()]],
        '=' => ['bool' => ['must_not' => ['exists' => ['field' => $condition->getField()]]]],
        default => throw new SearchApiException(sprintf('Invalid condition for field %s', $condition->getField())),
      };
    }

    // Normal filters.
    return match ($condition->getOperator()) {
      '=' => [
        'term' => [$condition->getField() => $condition->getValue()],
      ],
      'IN' => [
        'terms' => [$condition->getField() => array_values($condition->getValue())],
      ],
      'NOT IN' => [
        'bool' => ['must_not' => ['terms' => [$condition->getField() => array_values($condition->getValue())]]],
      ],
      '<>' => [
        'bool' => ['must_not' => ['term' => [$condition->getField() => $condition->getValue()]]],
      ],
      '>' => [
        'range' => [
          $condition->getField() => [
            'from' => $condition->getValue(),
            'to' => NULL,
            'include_lower' => FALSE,
            'include_upper' => FALSE,
          ],
        ],
      ],
      '>=' => [
        'range' => [
          $condition->getField() => [
            'from' => $condition->getValue(),
            'to' => NULL,
            'include_lower' => TRUE,
            'include_upper' => FALSE,
          ],
        ],
      ],
      '<' => [
        'range' => [
          $condition->getField() => [
            'from' => NULL,
            'to' => $condition->getValue(),
            'include_lower' => FALSE,
            'include_upper' => FALSE,
          ],
        ],
      ],
      '<=' => [
        'range' => [
          $condition->getField() => [
            'from' => NULL,
            'to' => $condition->getValue(),
            'include_lower' => FALSE,
            'include_upper' => TRUE,
          ],
        ],
      ],
      'BETWEEN' => [
        'range' => [
          $condition->getField() => [
            'from' => (!empty($condition->getValue()[0])) ? $condition->getValue()[0] : NULL,
            'to' => (!empty($condition->getValue()[1])) ? $condition->getValue()[1] : NULL,
            'include_lower' => FALSE,
            'include_upper' => FALSE,
          ],
        ],
      ],
      'NOT BETWEEN' => [
        'bool' => [
          'must_not' => [
            'range' => [
              $condition->getField() => [
                'from' => (!empty($condition->getValue()[0])) ? $condition->getValue()[0] : NULL,
                'to' => (!empty($condition->getValue()[1])) ? $condition->getValue()[1] : NULL,
                'include_lower' => FALSE,
                'include_upper' => FALSE,
              ],
            ],
          ],
        ],
      ],
      default => throw new SearchApiException(sprintf('Undefined operator "%s" for field "%s" in filter condition.', $condition->getOperator(), $condition->getField())),
    };

  }

  /**
   * Wraps filters with the conjunction.
   *
   * @param array $filters
   *   Array of filter parameters.
   * @param string $conjunction
   *   The conjunction used by the corresponding Search API condition group –
   *   either 'AND' or 'OR'.
   *
   * @return array
   *   Returns the passed $filters array wrapped in an array keyed by 'should'
   *   or 'must', as appropriate, based on the given conjunction.
   *
   * @throws \Drupal\search_api\SearchApiException
   *   Thrown if there is an invalid conjunction.
   */
  protected function wrapWithConjunction(array $filters, string $conjunction) {
    $f = match ($conjunction) {
      "OR" => ['should' => $filters],
      "AND" => ['must' => $filters],
      default => throw new SearchApiException(sprintf('Unknown filter conjunction "%s". Valid values are "OR" or "AND"', $conjunction)),
    };
    return ['bool' => $f];
  }

}
