<?php

namespace Drupal\search_api_opensearch\SearchAPI;

use Drupal\search_api\IndexInterface;
use Drupal\search_api\Item\FieldInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Plugin\search_api\data_type\value\TextValue;
use Drupal\search_api\Utility\FieldsHelperInterface;
use Drupal\search_api_opensearch\Event\IndexParamsEvent;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Provides a param builder for Items.
 */
class IndexParamBuilder {

  public function __construct(
    #[Autowire(service: 'search_api.fields_helper')]
    protected FieldsHelperInterface $fieldsHelper,
    protected EventDispatcherInterface $eventDispatcher,
  ) {
  }

  /**
   * Builds the params for an index operation.
   *
   * @param string $indexId
   *   The index ID.
   * @param \Drupal\search_api\IndexInterface $index
   *   The index.
   * @param \Drupal\search_api\Item\ItemInterface[] $items
   *   The items.
   *
   * @return array
   *   The index operation params.
   */
  public function buildIndexParams(string $indexId, IndexInterface $index, array $items): array {
    $params = [];

    foreach ($items as $id => $item) {
      $data = [];
      $this->addSpecialFields($index, $item);
      /** @var \Drupal\search_api\Item\FieldInterface $field */
      foreach ($item as $field) {
        $field_type = $field->getType();
        if (!empty($field->getValues())) {
          $values = $this->buildFieldValues($field, $field_type);
          $data[$field->getFieldIdentifier()] = $values;
        }
      }
      $params['body'][] = ['index' => ['_id' => $id, '_index' => $indexId]];
      $params['body'][] = $data;
    }

    // Allow modification of search params.
    $event = new IndexParamsEvent($indexId, $params);
    $this->eventDispatcher->dispatch($event);
    $params = $event->getParams();

    return $params;
  }

  /**
   * Adds the "magic" field values on an item.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   The index.
   * @param \Drupal\search_api\Item\ItemInterface $item
   *   The item.
   */
  protected function addSpecialFields(IndexInterface $index, ItemInterface $item): void {
    $item->setField('search_api_id', $this->fieldsHelper
      ->createField($index, 'search_api_id', ['type' => 'string'])
      ->setValues([$item->getId()]));
    $item->setField('search_api_datasource', $this->fieldsHelper
      ->createField($index, 'search_api_datasource', ['type' => 'string'])
      ->setValues([$item->getDatasourceId()]));
    $item->setField('search_api_language', $this->fieldsHelper
      ->createField($index, 'search_api_language', ['type' => 'string'])
      ->setValues([$item->getLanguage()]));
    // Boost value, default to minimum of 0.1 to remain valid. Zero is not
    // allowed.
    $item->setField('search_api_boost', $this->fieldsHelper
      ->createField($index, 'search_api_boost', ['type' => 'search_api_opensearch_rank_feature'])
      ->setValues([max(floatval($item->getBoost()), 0.1)]));
  }

  /**
   * Builds field values.
   *
   * @param \Drupal\search_api\Item\FieldInterface $field
   *   The field.
   * @param string $field_type
   *   The field type.
   *
   * @return array
   *   The fields params.
   */
  public function buildFieldValues(FieldInterface $field, string $field_type): array {
    $values = [];
    foreach ($field->getValues() as $value) {
      if ($value instanceof TextValue) {
        $values[] = $value->toText();
        continue;
      }
      $values[] = match ($field_type) {
        'string' => (string) $value,
        'boolean' => (boolean) $value,
        default => $value,
      };
    }
    return $values;
  }

}
