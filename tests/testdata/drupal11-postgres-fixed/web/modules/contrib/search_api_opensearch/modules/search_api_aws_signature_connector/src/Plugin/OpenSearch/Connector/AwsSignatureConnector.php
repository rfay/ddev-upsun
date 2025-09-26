<?php

namespace Drupal\search_api_aws_signature_connector\Plugin\OpenSearch\Connector;

use Drupal\Core\Form\FormStateInterface;
use Drupal\search_api_opensearch\Plugin\OpenSearch\Connector\StandardConnector;

/**
 * Provides an OpenSearch connector using AWS Signature.
 *
 * @OpenSearchConnector(
 *   id = "aws_signature",
 *   label = @Translation("AWS Signature"),
 *   description = @Translation("OpenSearch connector with AWS Signature."),
 *   depends = { "search_api_opensearch" }
 * )
 */
class AwsSignatureConnector extends StandardConnector {

  /**
   * {@inheritdoc}
   */
  public function getClientOptions(): array {
    $options = parent::getClientOptions() + [
      'auth_aws' => [
        'region' => $this->configuration['aws_region'] ?? 'us-east-1',
      ],
    ];

    // Set credentials if provided, otherwise fall back to defaults.
    if ('' !== $this->configuration['api_key'] && '' !== $this->configuration['api_secret']) {
      $options['auth_aws']['credentials'] = [
        'access_key' => $this->configuration['api_key'],
        'secret_key' => $this->configuration['api_secret'],
      ];
    }
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return parent::defaultConfiguration() + [
      'api_key' => '',
      'api_secret' => '',
      'aws_region' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildConfigurationForm($form, $form_state);

    /** @var \Drupal\Core\Config\Entity\ConfigEntityInterface $entity */
    $entity = $form_state->getFormObject()->getEntity();
    $machine_name = $entity->id();

    $form['url']['#description'] .= $this->t(
      "<br>You may override this field in your settings.php \$config['search_api.server.@machine-name']['backend_config']['connector_config']['uri'].",
      ['@machine-name' => $machine_name ?: '{machine_name}']
    );

    $form['api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Access Key'),
      '#default_value' => $this->configuration['api_key'] ?? '',
      '#required' => FALSE,
      '#description' => $this->t(
        "If you don't set this field, you must set it in your settings.php \$config['search_api.server.@machine-name']['backend_config']['connector_config']['api_key'].",
        ['@machine-name' => $machine_name ?: '{machine_name}']
      ),
    ];

    $form['api_secret'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Secret Key'),
      '#default_value' => $this->configuration['api_secret'] ?? '',
      '#required' => FALSE,
      '#description' => $this->t(
        "If you don't set this field, you must set it in your settings.php \$config['search_api.server.@machine-name']['backend_config']['connector_config']['api_secret'].",
        ['@machine-name' => $machine_name ?: '{machine_name}']
      ),
    ];

    $form['aws_region'] = [
      '#type' => 'textfield',
      '#title' => $this->t('AWS Region'),
      '#default_value' => $this->configuration['aws_region'] ?? '',
      '#required' => FALSE,
      '#description' => $this->t(
        "If you don't set this field, you must set it in your settings.php \$config['search_api.server.@machine-name']['backend_config']['connector_config']['aws_region'].",
        ['@machine-name' => $machine_name ?: '{machine_name}']
      ),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    parent::submitConfigurationForm($form, $form_state);

    $this->configuration['api_key'] = trim($form_state->getValue('api_key'));
    $this->configuration['api_secret'] = trim($form_state->getValue('api_secret'));
    $this->configuration['aws_region'] = trim($form_state->getValue('aws_region'));
  }

}
