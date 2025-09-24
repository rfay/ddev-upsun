<?php

namespace Drupal\Tests\memcache\Kernel;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceModifierInterface;
use Drupal\KernelTests\Core\Cache\GenericCacheBackendUnitTestBase;
use Drupal\memcache\MemcacheBackendFactory;

/**
 * Tests the MemcacheBackend.
 *
 * @group memcache
 */
class MemcacheBackendTest extends GenericCacheBackendUnitTestBase implements ServiceModifierInterface {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['system', 'memcache'];

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    $service_definition = $container->getDefinition('memcache.timestamp.invalidator.bin');
    // Set tolerance to 0 for timestamp invalidator.
    $service_definition->setArgument(2, 0);
  }

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $host = getenv('MEMCACHED_HOST') ?: '127.0.0.1:11211';
    $this->setSetting('memcache', [
      'servers' => [$host => 'default'],
      'bin' => ['default' => 'default'],
      'debug' => TRUE,
    ]);
  }

  /**
   * Creates a new instance of DatabaseBackend.
   *
   * @return \Drupal\memcache\MemcacheBackend
   *   A new MemcacheBackend object.
   */
  protected function createCacheBackend($bin) {
    $factory = new MemcacheBackendFactory(
      $this->container->get('memcache.factory'),
      $this->container->get('cache_tags.invalidator.checksum'),
      $this->container->get('memcache.timestamp.invalidator.bin'),
    );
    return $factory->get($bin);
  }

  /**
   * Gets a backend to test; this will get a shared instance set in the object.
   *
   * @return \Drupal\Core\Cache\CacheBackendInterface
   *   Cache backend to test.
   */
  protected function getCacheBackend($bin = NULL) {
    $backend = parent::getCacheBackend($bin);
    usleep(10000);
    return $backend;
  }

}
