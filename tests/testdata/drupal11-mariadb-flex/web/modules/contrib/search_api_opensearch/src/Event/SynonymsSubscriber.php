<?php

declare(strict_types=1);

namespace Drupal\search_api_opensearch\Event;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

// cspell:ignore asciifolding querytime

/**
 * Subscribes to AlterSettingsEvents to add synonym settings.
 */
class SynonymsSubscriber implements EventSubscriberInterface {

  /**
   * Handles the AlterSettingsEvent.
   *
   * @param \Drupal\search_api_opensearch\Event\AlterSettingsEvent $event
   *   The AlterSettingsEvent.
   */
  public function onAlterSettings(AlterSettingsEvent $event): void {
    $synonyms = $event->getBackendConfig()['advanced']['synonyms'] ?? [];
    if ($synonyms) {
      $settings = $event->getSettings();
      $settings['analysis']['filter']['synonyms'] = [
        'type' => 'synonym_graph',
        'lenient' => TRUE,
        'synonyms' => array_map('trim', $synonyms),
      ];
      $settings['analysis']['analyzer']['default'] = [
        'type' => 'custom',
        'tokenizer' => 'standard',
        'filter' => ['lowercase', 'asciifolding', 'synonyms'],
      ];
      $event->setSettings($settings);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      AlterSettingsEvent::class => 'onAlterSettings',
    ];
  }

}
