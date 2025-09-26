<?php

declare(strict_types=1);

namespace Drupal\Tests\search_api_aws_signature_connector\Kernel\Plugin\OpenSearch\Connector;

use Drupal\KernelTests\KernelTestBase;
use Drupal\search_api_aws_signature_connector\Plugin\OpenSearch\Connector\AwsSignatureConnector;
use Drupal\search_api_opensearch\Connector\ConnectorPluginManager;
use OpenSearch\Client;

/**
 * Tests the AWS Signature connector.
 *
 * @group search_api_opensearch
 * @group search_api_aws_signature_connector
 * @coversDefaultClass \Drupal\search_api_aws_signature_connector\Plugin\OpenSearch\Connector\AwsSignatureConnector
 */
class AwsSignatureConnectorTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'search_api',
    'search_api_opensearch',
    'search_api_aws_signature_connector',
  ];

  /**
   * @covers ::getClient
   */
  public function testGetClient(): void {
    /** @var \Drupal\search_api_opensearch\Connector\ConnectorPluginManager $manager */
    $manager = \Drupal::service(ConnectorPluginManager::class);
    $connector = $manager->createInstance('aws_signature', [
      'url' => 'http://localhost:9200',
      'ssl_verification' => FALSE,
      'api_key' => 'test_key',
      'api_secret' => 'test_secret',
      'aws_region' => 'ap-southeast-2',
    ]);

    $this->assertInstanceOf(AwsSignatureConnector::class, $connector);

    $client = $connector->getClient();

    $this->assertInstanceOf(Client::class, $client);
  }

}
