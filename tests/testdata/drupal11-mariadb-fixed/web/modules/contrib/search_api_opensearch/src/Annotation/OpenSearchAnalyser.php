<?php

declare(strict_types=1);

namespace Drupal\search_api_opensearch\Annotation;

use Drupal\Component\Annotation\Plugin;
use Drupal\Core\Annotation\Translation;

/**
 * Defines an annotation for open search analyser plugins.
 *
 * @Annotation
 */
final class OpenSearchAnalyser extends Plugin {

  /**
   * Plugin ID.
   */
  public string $id;

  /**
   * Plugin label.
   */
  public string|Translation $label;

}
