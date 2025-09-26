<?php

namespace Drupal\search_api_opensearch\Event;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * Provides a class for events that alter client options.
 */
class ClientOptionsEvent extends Event {

  public function __construct(
    private array $options,
  ) {}

  /**
   * Gets the client options.
   *
   * @return array<string, mixed>
   *   The client options.
   */
  public function getOptions(): array {
    return $this->options;
  }

  /**
   * Sets the client options.
   *
   * @param array<string, mixed> $options
   *   The client options.
   */
  public function setOptions(array $options): void {
    $this->options = $options;
  }

}
