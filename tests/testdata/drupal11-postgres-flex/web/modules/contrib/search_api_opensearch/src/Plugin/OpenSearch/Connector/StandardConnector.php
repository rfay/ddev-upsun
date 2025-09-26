<?php

namespace Drupal\search_api_opensearch\Plugin\OpenSearch\Connector;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\search_api_opensearch\Connector\OpenSearchConnectorInterface;
use Drupal\search_api_opensearch\Event\ClientOptionsEvent;
use OpenSearch\Client;
use OpenSearch\ClientFactoryInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a standard OpenSearch connector.
 *
 * @OpenSearchConnector(
 *   id = "standard",
 *   label = @Translation("Standard"),
 *   description = @Translation("A standard connector without authentication")
 * )
 */
class StandardConnector extends PluginBase implements OpenSearchConnectorInterface, ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected readonly ClientFactoryInterface $clientFactory,
    protected readonly EventDispatcherInterface $eventDispatcher,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get(ClientFactoryInterface::class),
      $container->get(EventDispatcherInterface::class),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel(): string {
    return (string) $this->pluginDefinition['label'];
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription(): string {
    return (string) $this->pluginDefinition['description'];
  }

  /**
   * {@inheritdoc}
   */
  public function getUrl(): string {
    return (string) $this->configuration['url'];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration(): array {
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    $this->configuration = $configuration + $this->defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function getClient(): Client {
    $options = $this->getClientOptions();
    return $this->createClient($options);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'url' => '',
      'ssl_verification' => TRUE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['url'] = [
      '#type' => 'url',
      '#title' => $this->t('OpenSearch URL'),
      '#description' => $this->t('The URL of your OpenSearch server, e.g. <code>http://127.0.0.1:9200</code> or <code>https://www.example.com:443</code>. The port defaults to <code>9200</code> if not specified. <strong>Do not include a trailing slash.</strong>'),
      '#default_value' => $this->configuration['url'] ?? '',
      '#required' => TRUE,
    ];

    $form['ssl_verification'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('SSL Verification'),
      '#description' => $this->t('Whether to verify the SSL certificate of the OpenSearch server. This should be enabled in production environments.'),
      '#default_value' => $this->configuration['ssl_verification'] ?? '',
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    $url = $form_state->getValue('url');
    if (!UrlHelper::isValid($url)) {
      $form_state->setErrorByName('url', $this->t("Invalid URL"));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['url'] = trim($form_state->getValue('url'), '/ ');
    $this->configuration['ssl_verification'] = (bool) $form_state->getValue('ssl_verification');
  }

  /**
   * Creates an OpenSearch client.
   *
   * @param array<string,mixed> $options
   *   The HTTP client options.
   */
  protected function createClient(array $options): Client {
    $event = new ClientOptionsEvent($options);
    $this->eventDispatcher->dispatch($event);
    $options = $event->getOptions();
    return $this->clientFactory->create($options);
  }

  /**
   * Get the client options.
   *
   * @return array<string,mixed>
   *   The client options.
   */
  protected function getClientOptions(): array {
    return [
      'base_uri' => $this->configuration['url'],
      'verify' => $this->configuration['ssl_verification'],
    ];
  }

}
