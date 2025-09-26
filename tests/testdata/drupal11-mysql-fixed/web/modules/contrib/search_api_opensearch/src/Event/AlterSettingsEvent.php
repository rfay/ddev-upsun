<?php

namespace Drupal\search_api_opensearch\Event;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * An event allowing OS settings to be altered.
 */
class AlterSettingsEvent extends Event {

  /**
   * Creates a new event.
   *
   * @param array $settings
   *   OpenSearch settings that can be altered..
   * @param array $backendConfig
   *   The server backend config.
   */
  public function __construct(protected array $settings, protected array $backendConfig) {}

  /**
   * Alter the settings.
   *
   * @param array $settings
   *   The settings.
   */
  public function setSettings(array $settings): void {
    $this->settings = $settings;
  }

  /**
   * Get the settings.
   */
  public function getSettings(): array {
    return $this->settings;
  }

  /**
   * Get the backend config.
   *
   * @return array
   *   The backend config.
   */
  public function getBackendConfig(): array {
    return $this->backendConfig;
  }

}
