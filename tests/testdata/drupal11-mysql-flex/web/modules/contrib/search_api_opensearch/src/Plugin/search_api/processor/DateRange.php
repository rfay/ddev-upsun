<?php

namespace Drupal\search_api_opensearch\Plugin\search_api\processor;

use Drupal\search_api\Processor\ProcessorPluginBase;

/**
 * Add date ranges to the index.
 *
 * @SearchApiProcessor(
 *   id = "search_api_opensearch_date_range",
 *   label = @Translation("Date ranges"),
 *   description = @Translation("Date ranges."),
 *   stages = {
 *     "preprocess_index" = 0,
 *   },
 *   locked = true,
 *   hidden = true,
 * )
 */
class DateRange extends ProcessorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function preprocessIndexItems(array $items) {
    foreach ($items as $item) {
      foreach ($item->getFields() as $field) {
        if ('search_api_opensearch_date_range' == $field->getType()) {
          $values = [];
          $required_properties = [
            $item->getDatasourceId() => [
              $field->getPropertyPath() . ':value' => 'start',
              $field->getPropertyPath() . ':end_value' => 'end',
            ],
          ];
          $item_values = $this->getFieldsHelper()->extractItemValues([$item], $required_properties);
          foreach ($item_values as $dates) {
            $start_dates = $dates['start'];
            $end_dates = $dates['end'];
            for ($i = 0, $n = count($start_dates); $i < $n; $i++) {
              $values[$i] = [
                'gte' => $start_dates[$i],
                'lte' => $end_dates[$i],
              ];
            }
          }
          if (!empty($values)) {
            $field->setValues($values);
          }
        }
      }
    }
  }

}
