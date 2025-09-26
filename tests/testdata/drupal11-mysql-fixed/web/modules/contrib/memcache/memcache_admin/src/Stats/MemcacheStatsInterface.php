<?php

namespace Drupal\memcache_admin\Stats;

/**
 * Defines the Memcache connection interface.
 */
interface MemcacheStatsInterface {

  /**
   * Sets an array of raw data for the memcache server.
   *
   * @param array $raw_data
   *   Set of raw data.
   *
   * @return void
   *   Setting the raw data.
   */
  public function setRaw(array $raw_data): void;

  /**
   * Returns raw data from the memcache server.
   *
   * @return array
   *   Get raw data.
   */
  public function getRaw(): array;

  /**
   * Returns the memcache server version.
   *
   * @return string
   *   Get memcache server version.
   */
  public function getVersion(): string;

  /**
   * Returns the uptime for the memcache server.
   *
   * @return string
   *   Get memcache server uptime.
   */
  public function getUptime(): string;

  /**
   * Returns the PECL extension for the memcache server.
   *
   * @return string
   *   Get memcache server extension.
   */
  public function getExtension(): string;

  /**
   * Returns the total connections for the memcache server.
   *
   * @return string
   *   Get total connections for memcache server.
   */
  public function getTotalConnections(): string;

  /**
   * Returns the cache sets for the memcache server.
   *
   * @return string
   *   Get cache set of memcache server.
   */
  public function getSets(): string;

  /**
   * Returns the cache gets for the memcache server.
   *
   * @return string
   *   Get cache gets of memcache server.
   */
  public function getGets(): string;

  /**
   * Returns the counters for the memcache server.
   *
   * @return string
   *   Get counters for memcache server.
   */
  public function getCounters(): string;

  /**
   * Returns the data transferred for the memcache server.
   *
   * @return string
   *   Get data transferred of memcache server.
   */
  public function getTransferred(): string;

  /**
   * Returns the connection averages for the memcache server.
   *
   * @return string
   *   Get connection average of memcache server.
   */
  public function getConnectionAvg(): string;

  /**
   * Returns the memory available for the memcache server.
   *
   * @return string
   *   Get memory available for memcache server.
   */
  public function getMemory(): string;

  /**
   * Returns the evictions for the memcache server.
   *
   * @return string
   *   Get memcache server evictions.
   */
  public function getEvictions(): string;

}
