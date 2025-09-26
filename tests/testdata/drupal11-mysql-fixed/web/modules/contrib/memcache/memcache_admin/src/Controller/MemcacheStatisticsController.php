<?php

namespace Drupal\memcache_admin\Controller;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Link;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\ByteSizeMarkup;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\memcache\Driver\MemcacheDriverFactory;
use Drupal\memcache\MemcacheSettings;
use Drupal\memcache_admin\Event\MemcacheStatsEvent;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Defines Memcache Statistics.
 */
class MemcacheStatisticsController extends ControllerBase {

  use MessengerTrait, StringTranslationTrait;

  /**
   * Memcache Driver Factory.
   *
   * @var \Drupal\memcache\Driver\MemcacheDriverFactory
   */
  protected $memcacheDriverFactory;

  /**
   * Event Dispatcher Service.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcher
   */
  protected $dispatcher;

  /**
   * Core Date Formatter Service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * Core DateTime Service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * Memcache Settings Service.
   *
   * @var \Drupal\memcache\MemcacheSettings
   */
  protected $settings;

  /**
   * The route match service.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * The current user service.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * Memcache Statistics Page.
   *
   * Displays a page of memcache statistics for an administrator to debug.
   *
   * @param \Drupal\memcache\Driver\MemcacheDriverFactory $memcacheDriverFactory
   *   The Memcache Driver.
   * @param \Drupal\Component\EventDispatcher\ContainerAwareEventDispatcher $dispatcher
   *   Event Dispatcher service.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $dateFormatter
   *   Date Formatter service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   DateTime Service.
   * @param \Drupal\memcache\MemcacheSettings $settings
   *   Memcache Settings service.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   Route match interface.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   Account interface.
   */
  public function __construct(MemcacheDriverFactory $memcacheDriverFactory, EventDispatcherInterface $dispatcher, DateFormatterInterface $dateFormatter, TimeInterface $time, MemcacheSettings $settings, RouteMatchInterface $route_match, AccountInterface $current_user) {
    $this->memcacheDriverFactory = $memcacheDriverFactory;
    $this->dispatcher = $dispatcher;
    $this->dateFormatter = $dateFormatter;
    $this->time = $time;
    $this->settings = $settings;
    $this->routeMatch = $route_match;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The Drupal service container.
   *
   * @return static
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('memcache.factory'),
      $container->get('event_dispatcher'),
      $container->get('date.formatter'),
      $container->get('datetime.time'),
      $container->get('memcache.settings'),
      $container->get('current_route_match'),
      $container->get('current_user'),
    );
  }

  /**
   * Callback for the Memcache Stats page.
   *
   * @param string $cluster
   *   The cluster name.
   *
   * @return array
   *   The page output.
   */
  public function statsTable($cluster = 'default') {
    $bin = $this->getBinMapping($cluster);
    /** @var \Drupal\memcache\DrupalMemcacheInterface $memcache */
    $memcache = $this->memcacheDriverFactory->get($bin, TRUE);

    // Instantiate our event.
    $event = new MemcacheStatsEvent($memcache, $bin);

    // Get the event_dispatcher service and dispatch the event.
    $this->dispatcher->dispatch($event, MemcacheStatsEvent::BUILD_MEMCACHE_STATS);

    $raw_stats = [];
    // Report the PHP Memcache(d) driver version.
    if ($memcache->getMemcache() instanceof \Memcached) {
      $raw_stats['driver_version'] = $this->t('PECL Driver in Use: Memcached v@version', ['@version' => phpversion('Memcached')]);
    }
    elseif ($memcache->getMemcache() instanceof \Memcache) {
      $raw_stats['driver_version'] = $this->t('PECL Driver in Use: Memcache v@version', ['@version' => phpversion('Memcache')]);
    }

    // Get the event_dispatcher service and dispatch the event.
    $this->dispatcher->dispatch($event, MemcacheStatsEvent::REPORT_MEMCACHE_STATS);

    $output = ['#markup' => '<p>' . $raw_stats['driver_version'] . '</p>'];

    // Get endpoints.
    $servers_settings = $this->settings->get('servers', []);
    $bins_settings = $this->settings->get('bins', []);
    if (isset($servers_settings) && count($servers_settings) > 1) {
      $bins = [];
      if (isset($bins_settings)) {
        foreach ($bins_settings as $bin_name => $cluster_name) {
          if (!isset($bins[$cluster_name])) {
            $bins[$cluster_name] = [];
          }
          $bins[$cluster_name][] = $bin_name;
        }
      }
      $links = [];
      asort($servers_settings);
      foreach ($servers_settings as $end_point => $cluster_name) {
        $bin_description = $this->t("No bins allocated to this endpoint");
        if (isset($bins[$cluster_name])) {
          sort($bins[$cluster_name]);
          $bin_description = implode(',', $bins[$cluster_name]);
        }
        $links[] = Link::createFromRoute(
          $end_point . ' (' . $bin_description . ')',
          'memcache_admin.reports_cluster', ['cluster' => $cluster_name]
        )->toRenderable();
      }
      $output[] = [
        '#theme' => 'item_list',
        '#list_type' => 'ul',
        '#title' => $this->t('Configured services'),
        '#items' => $links,
        '#attributes' => ['class' => 'memcache_services'],
        '#wrapper_attributes' => ['class' => 'memcache_container'],
      ];
    }
    $output[] = $this->statsTablesOutput($bin, $event->getServers(), $event->getReport());

    return $output;
  }

  /**
   * Callback for the Memcache Stats page.
   *
   * @param string $cluster
   *   The Memcache cluster name.
   * @param string $server
   *   The Memcache server name.
   * @param string $type
   *   The type of statistics to retrieve when using the Memcache extension.
   *
   * @return string
   *   The page output.
   */
  public function statsTableRaw($cluster, $server, $type = 'default') {
    $cluster = $this->binMapping($cluster);
    $server = str_replace('!', '/', $server);

    $slab = $this->routeMatch->getParameter('slab');
    $memcache = $this->memcacheDriverFactory->get($cluster, TRUE);
    if ($type == 'slabs' && !empty($slab)) {
      $stats = $memcache->stats($cluster, $slab, FALSE);
    }
    else {
      $stats = $memcache->stats($cluster, $type, FALSE);
    }

    // @codingStandardsIgnoreStart
    // @todo - breadcrumb
    // $breadcrumbs = [
    //   l(t('Home'), NULL),
    //   l(t('Administer'), 'admin'),
    //   l(t('Reports'), 'admin/reports'),
    //   l(t('Memcache'), 'admin/reports/memcache'),
    //   l(t($bin), "admin/reports/memcache/$bin"),
    // ];
    // if ($type == 'slabs' && arg(6) == 'cachedump' && user_access('access slab cachedump')) {
    //   $breadcrumbs[] = l($server, "admin/reports/memcache/$bin/$server");
    //   $breadcrumbs[] = l(t('slabs'), "admin/reports/memcache/$bin/$server/$type");
    // }
    // drupal_set_breadcrumb($breadcrumbs);
    // @codingStandardsIgnoreEnd
    if (isset($stats[$cluster][$server]) && is_array($stats[$cluster][$server]) && count($stats[$cluster][$server])) {
      $output = $this->statsTablesRawOutput($cluster, $server, $stats[$cluster][$server], $type);
    }
    elseif ($type == 'slabs' && is_array($stats[$cluster]) && count($stats[$cluster])) {
      $output = $this->statsTablesRawOutput($cluster, $server, $stats[$cluster], $type);
    }
    else {
      $output = $this->statsTablesRawOutput($cluster, $server, [], $type);
      $this->messenger()->addMessage($this->t('No @type statistics for this bin.', ['@type' => $type]));
    }

    return $output;
  }

  /**
   * Helper function, reverse map the memcache_bins variable.
   */
  private function binMapping($bin = 'cache') {
    $memcache = $this->memcacheDriverFactory->get(NULL, TRUE);
    $memcache_bins = $memcache->getBins();

    $bins = array_flip($memcache_bins);
    if (isset($bins[$bin])) {
      return $bins[$bin];
    }
    else {
      return $this->defaultBin($bin);
    }
  }

  /**
   * Generates render array for output.
   */
  private function statsTablesOutput($bin, $servers, $stats) {
    $memcache = $this->memcacheDriverFactory->get(NULL, TRUE);
    $memcache_bins = $memcache->getBins();

    $links = [];
    if (!is_array($servers)) {
      return;
    }
    foreach ($servers as $server) {

      // Convert socket file path so it works with an argument, this should
      // have no impact on non-socket configurations. Convert / to !.
      $links[] = Link::fromTextandUrl($server, Url::fromUri('base:/admin/reports/memcache/' . $memcache_bins[$bin] . '/' . str_replace('/', '!', $server)))->toString();
    }

    if (count($servers) > 1) {
      $headers = array_merge(['', $this->t('Totals')], $links);
    }
    else {
      $headers = array_merge([''], $links);
    }

    $output = [];
    foreach ($stats as $table => $data) {
      $rows = [];
      foreach ($data as $data_row) {
        $row = [];
        $row[] = $data_row['label'];
        if (isset($data_row['total'])) {
          $row[] = $data_row['total'];
        }
        foreach ($data_row['servers'] as $server) {
          $row[] = $server;
        }
        $rows[] = $row;
      }
      $output[$table] = [
        '#theme' => 'table',
        '#header' => $headers,
        '#rows' => $rows,

      ];
    }

    return $output;
  }

  /**
   * Generates render array for output.
   */
  private function statsTablesRawOutput($cluster, $server, $stats, $type) {
    $current_type  = $type ?? 'default';
    $memcache = $this->memcacheDriverFactory->get(NULL, TRUE);
    $memcache_bins = $memcache->getBins();
    $bin = $memcache_bins[$cluster] ?? 'default';
    $slab = $this->routeMatch->getParameter('slab');

    // Provide navigation for the various memcache stats types.
    $links = [];
    if (count($memcache->statsTypes())) {
      foreach ($memcache->statsTypes() as $type) {
        // @todo render array
        $link = Link::fromTextandUrl($type, Url::fromUri('base:/admin/reports/memcache/' . $bin . '/' . str_replace('/', '!', $server) . '/' . ($type == 'default' ? '' : $type)))->toString();
        if ($current_type == $type) {
          $links[] = '<strong>' . $link . '</strong>';
        }
        else {
          $links[] = $link;
        }
      }
    }
    $build = [
      'links' => [
        '#markup' => !empty($links) ? implode(' | ', $links) : '',
      ],
    ];

    $build['table'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Property'),
        $this->t('Value'),
      ],
    ];

    $row = 0;

    // Items are returned as an array within an array within an array.  We step
    // in one level to properly display the contained statistics.
    if ($current_type == 'items' && isset($stats['items'])) {
      $stats = $stats['items'];
    }
    $user = $this->currentUser;
    foreach ($stats as $key => $value) {
      // Add navigation for getting a cachedump of individual slabs.
      if (($current_type == 'slabs' || $current_type == 'items') && is_int($key) && $user->hasPermission('access slab cachedump')) {
        $build['table'][$row]['key'] = [
          '#type' => 'link',
          '#title' => $this->t('Slab @slab', ['@slab' => $key]),
          '#url' => Url::fromUri('base:/admin/reports/memcache/' . $bin . '/' . str_replace('/', '!', $server) . '/slabs/cachedump/' . $key),
        ];
      }
      else {
        $build['table'][$row]['key'] = ['#plain_text' => $key];
      }

      if (is_array($value)) {
        $subrow = 0;
        $build['table'][$row]['value'] = ['#type' => 'table'];
        foreach ($value as $k => $v) {

          // Format timestamp when viewing cachedump of individual slabs.
          if ($current_type == 'slabs' && $user->hasPermission('access slab cachedump') && !empty($slab) && $k == 0) {
            $k = $this->t('Size');
            // @phpstan-ignore-next-line
            $v = class_exists(ByteSizeMarkup::class) ? ByteSizeMarkup::create($v) : format_size($v);
          }
          elseif ($current_type == 'slabs' && $user->hasPermission('access slab cachedump') && !empty($slab) && $k == 1) {
            $k = $this->t('Expire');
            $full_stats = $memcache->stats($cluster);
            $infinite = $full_stats[$cluster][$server]['time'] - $full_stats[$cluster][$server]['uptime'];
            if ($v == $infinite) {
              $v = $this->t('infinite');
            }
            else {
              $v = $this->t('in @time', ['@time' => $this->dateFormatter->formatInterval($v - $this->time->getRequestTime())]);
            }
          }
          $build['table'][$row]['value'][$subrow] = [
            'key' => ['#plain_text' => $k],
            'value' => ['#plain_text' => $v],
          ];
          $subrow++;
        }
      }
      else {
        $build['table'][$row]['value'] = ['#plain_text' => $value];
      }
      $row++;
    }

    return $build;
  }

  /**
   * Helper function, reverse map the memcache_bins variable.
   */
  protected function getBinMapping($bin = 'cache') {
    $memcache      = $this->memcacheDriverFactory->get(NULL, TRUE);
    $memcache_bins = $memcache->getBins();

    $bins = array_flip($memcache_bins);
    if (isset($bins[$bin])) {
      return $bins[$bin];
    }
    else {
      return $this->defaultBin($bin);
    }
  }

  /**
   * Helper function. Returns the bin name.
   */
  protected function defaultBin($bin) {
    if ($bin == 'default') {
      return 'cache';
    }

    return $bin;
  }

}
