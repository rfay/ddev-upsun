<?php

declare(strict_types=1);

namespace Drupal\search_api_opensearch\Analyser;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\search_api_opensearch\Annotation\OpenSearchAnalyser;

/**
 * Defines a plugin manager for analyser plugins.
 */
final class AnalyserManager extends DefaultPluginManager {

  /**
   * Constructs a AnalyserManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    $this->alterInfo('opensearch_analyser_info');
    $this->setCacheBackend($cache_backend, 'opensearch_analyser_plugins');

    parent::__construct('Plugin/OpenSearch/Analyser', $namespaces, $module_handler, AnalyserInterface::class, OpenSearchAnalyser::class);
  }

}
