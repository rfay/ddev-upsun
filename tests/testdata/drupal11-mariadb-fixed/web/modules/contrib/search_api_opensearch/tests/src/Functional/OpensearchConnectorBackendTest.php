<?php

declare(strict_types=1);

namespace Drupal\Tests\search_api_opensearch\Functional;

use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests for situations when backend is down.
 *
 * @group search_api_opensearch
 */
class OpensearchConnectorBackendTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'dblog',
    'search_api_opensearch',
    'search_api_opensearch_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create an admin user.
    $admin_user = $this->drupalCreateUser([
      'access administration pages',
      'access site reports',
      'administer search_api',
    ]);
    $this->drupalLogin($admin_user);
  }

  /**
   * Tests that no exception is thrown when visiting the Search API routes.
   */
  public function testSearchApiRoutes() {
    $assert_session = $this->assertSession();

    // Alter the Opensearch server configuration to cause failure to connect
    // to Opensearch server.
    $config = $this->config('search_api.server.opensearch_server');
    $config->set('backend_config.connector_config.url', 'http://opensearch:9999');
    $config->save();

    // Assert "search_api.overview" route loads without errors.
    $url = Url::fromRoute('search_api.overview');
    $this->drupalGet($url);
    $this->assertSession()->statusCodeEquals(200);
    $assert_session->elementTextContains('css', '.search-api-server-opensearch-server .search-api-status', 'Unavailable');

    // Assert "entity.search_api_server.canonical" route loads without errors.
    $url = Url::fromRoute('entity.search_api_server.canonical', [
      'search_api_server' => 'opensearch_server',
    ]);
    $this->drupalGet($url);
    $this->assertSession()->statusCodeEquals(200);
    $assert_session->pageTextContains('Local test server');

    // Assert "entity.search_api_index.canonical" route loads without errors.
    $url = Url::fromRoute('entity.search_api_index.canonical', [
      'search_api_index' => 'test_opensearch_index',
    ]);
    $this->drupalGet($url);
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains('Test Index');
    $assert_session->elementTextContains('css', '.search-api-index-summary--server-index-status', 'Error while checking server index status');

    // Assert error produced on "search_api.overview" route is logged.
    $this->drupalGet('/admin/reports/dblog');
    $assert_session->pageTextContains('ConnectException');
  }

}
