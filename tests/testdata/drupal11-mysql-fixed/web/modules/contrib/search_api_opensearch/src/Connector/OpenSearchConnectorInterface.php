<?php

namespace Drupal\search_api_opensearch\Connector;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use OpenSearch\Client;

/**
 * Defines and interface for OpenSearch Connector plugins.
 */
interface OpenSearchConnectorInterface extends PluginFormInterface, ConfigurableInterface, PluginInspectionInterface {

  /**
   * Gets the connection label.
   *
   * @return string
   *   The label.
   */
  public function getLabel(): string;

  /**
   * Gets the connection description.
   *
   * @return string
   *   The description.
   */
  public function getDescription(): string;

  /**
   * Gets the OpenSearch client.
   *
   * @return \OpenSearch\Client
   *   The OpenSearch client.
   */
  public function getClient(): Client;

  /**
   * Gets the URL to the OpenSearch cluster.
   *
   * @return string
   *   The OpenSearch cluster URL.
   */
  public function getUrl(): string;

}
