<?php

namespace Drupal\search_api_opensearch\Plugin\search_api\data_type;

use Drupal\search_api\Plugin\search_api\data_type\TextDataType;

/**
 * Provides data type to feed the suggester component.
 *
 * @SearchApiDataType(
 *   id = "search_api_opensearch_text_spellcheck",
 *   label = @Translation("Open Search Spellcheck"),
 *   description = @Translation("Full text field to feed the spellcheck component."),
 *   fallback_type = "text"
 * )
 */
class SpellcheckTextDataType extends TextDataType {}
