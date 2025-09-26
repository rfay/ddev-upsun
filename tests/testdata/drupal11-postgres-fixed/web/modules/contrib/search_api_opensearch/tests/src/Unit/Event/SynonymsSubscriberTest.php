<?php

declare(strict_types=1);

namespace Drupal\Tests\search_api_opensearch\Unit\Event;

use Drupal\Tests\UnitTestCaseTest;
use Drupal\search_api_opensearch\Event\AlterSettingsEvent;
use Drupal\search_api_opensearch\Event\SynonymsSubscriber;

/**
 * Tests the SynonymsSubscriber.
 *
 * @group search_api_opensearch
 * @coversDefaultClass \Drupal\search_api_opensearch\Event\SynonymsSubscriber
 */
class SynonymsSubscriberTest extends UnitTestCaseTest {

  /**
   * @covers ::onAlterSettings
   */
  public function testOnSettingsAlter(): void {
    $synonyms = ['foo, bar', 'cat, dog'];

    // Provide some existing settings to test array merging.
    $settings = [
      'whiz' => 'bang',
      'analysis' => [
        'filter' => ['foo' => ['bar']],
      ],
    ];

    $backendConfig = ['advanced' => ['synonyms' => $synonyms]];
    $event = new AlterSettingsEvent($settings, $backendConfig);

    $subscriber = new SynonymsSubscriber();
    $subscriber->onAlterSettings($event);

    $settings = $event->getSettings();

    $expectedSettings = [
      'whiz' => 'bang',
      'analysis' => [
        'filter' => [
          'foo' => ['bar'],
          'synonyms' => [
            'type' => 'synonym_graph',
            'lenient' => TRUE,
            'synonyms' => $synonyms,
          ],
        ],
        'analyzer' => [
          'default' => [
            'type' => 'custom',
            'tokenizer' => 'standard',
            'filter' => ['lowercase', 'asciifolding', 'synonyms'],
          ],
        ],
      ],
    ];

    $this->assertEquals($expectedSettings, $settings);

  }

}
