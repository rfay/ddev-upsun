<?php

namespace Drupal\search_api_opensearch\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a connector plugin annotation object.
 *
 * Condition plugins provide generalized conditions for use in other
 * operations, such as conditional block placement.
 *
 * Plugin Namespace: Plugin\OpenSearch
 *
 * @see \Drupal\search_api_opensearch\Connector\ConnectorPluginManager
 * @see \Drupal\search_api_opensearch\Connector\OpenSearchConnectorInterface
 *
 * @ingroup plugin_api
 *
 * @Annotation
 */
class OpenSearchConnector extends Plugin {

  /**
   * The OpenSearch connector plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The human-readable name of the OpenSearch connector.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $label;

  /**
   * The backend description.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $description;

}
