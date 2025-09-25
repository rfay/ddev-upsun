<?php

namespace Drupal\Tests\memcache\Functional;

use Drupal\Tests\system\Functional\Lock\LockFunctionalTest;

/**
 * Confirm locking works between two separate requests.
 *
 * @group memcache
 */
class MemcacheLockFunctionalTest extends LockFunctionalTest {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['system_test', 'memcache', 'memcache_test'];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $host = getenv('MEMCACHED_HOST') ?: '127.0.0.1:11211';
    $settings['settings']['memcache'] = (object) [
      'value' => [
        'servers' => [$host => 'default'],
        'bin' => ['default' => 'default'],
      ],
      'required' => TRUE,
    ];

    $settings['settings']['hash_salt'] = (object) [
      'value' => $this->randomMachineName(),
      'required' => TRUE,
    ];

    $this->writeSettings($settings);
  }

}
