<?php

namespace Drupal\memcache_admin\Stats;

use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Class MemcacheStats.
 *
 * @package Drupal\memcache_admin\Stats
 */
class McrouterStatsObject extends MemcacheStatsObject implements MemcacheStatsInterface {

  use StringTranslationTrait;

  /**
   * Statistics list.
   *
   * @var array
   */
  protected $stats;

  /**
   * {@inheritDoc}
   */
  public function getExtension(): string {
    return $this->stats['version'] ?? self::NA;
  }

  /**
   * {@inheritDoc}
   */
  public function getServerTime(): string {
    // @phpcs:ignore
    // @phpstan-ignore-next-line
    return isset($this->stats['time']) ? \Drupal::service('date.formatter')->format($this->stats['time']) : self::NA;
  }

  /**
   * Statistics report: format total and open connections.
   */
  public function getConnections() {
    return self::NA;
  }

  /**
   * {@inheritDoc}
   */
  public function getCurrentConnections(): string {
    return self::NA;
  }

  /**
   * {@inheritDoc}
   */
  public function getTotalConnections(): string {
    return self::NA;
  }

  /**
   * Statistics report: calculate # of get cmds, broken down by hits and misses.
   */
  public function getGets(): string {
    return self::NA;
  }

  /**
   * {@inheritDoc}
   */
  public function getCounters(): string {
    return self::NA;
  }

  /**
   * {@inheritDoc}
   */
  public function getTransferred(): string {
    return self::NA;
  }

  /**
   * {@inheritDoc}
   */
  public function getConnectionAvg(): string {
    return self::NA;
  }

  /**
   * {@inheritDoc}
   */
  public function getMemory(): string {
    return self::NA;
  }

  /**
   * {@inheritDoc}
   */
  public function getEvictions(): string {
    return isset($this->stats['evictions']) ? number_format($this->stats['evictions']) : self::NA;
  }

  /**
   * {@inheritDoc}
   */
  public function setRaw(array $raw_data): void {
    $this->stats = $raw_data;
  }

  /**
   * {@inheritDoc}
   */
  public function getRaw(): array {
    return $this->stats;
  }

  /**
   * {@inheritDoc}
   */
  public function getVersion(): string {
    return isset($this->stats['version']) ? (string) $this->stats['version'] : self::NA;
  }

}
