<?php

namespace Drupal\memcache_status\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Site\Settings;
use Drupal\memcache\Driver\MemcacheDriverFactory;
use Drupal\memcache_status\MemcacheStatusHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Defines the controller to list down the servers statistics.
 */
class ServersView extends ControllerBase {

  /**
   * The Memcache Driver.
   *
   * @var \Drupal\memcache\Driver\MemcacheDriverFactory
   */
  protected MemcacheDriverFactory $memcacheDriverFactory;

  /**
   * The MemcacheStatus helper.
   *
   * @var \Drupal\memcache_status\MemcacheStatusHelper
   */
  protected MemcacheStatusHelper $memcacheStatusHelper;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * Constructs a new SlabsView instance.
   *
   * @param \Drupal\memcache\Driver\MemcacheDriverFactory $memcacheDriverFactory
   *   The Memcache Driver.
   * @param \Drupal\memcache_status\MemcacheStatusHelper $memcache_status_helper
   *   The MemcacheStatus helper.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   */
  public function __construct(MemcacheDriverFactory $memcacheDriverFactory, MemcacheStatusHelper $memcache_status_helper, RendererInterface $renderer) {
    $this->memcacheDriverFactory = $memcacheDriverFactory;
    $this->memcacheStatusHelper = $memcache_status_helper;
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('memcache.factory'),
      $container->get('memcache_status.memcache_helper'),
      $container->get('renderer'),
    );
  }

  /**
   * Render the "Servers" page displaying the servers statistics.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return array
   *   The render array.
   */
  public function render(Request $request) {
    // If there is a key_prefix, that probably means the servers are shared
    // among multiples sites. We display a warning message because the stats
    // are global and can't be made site's specific.
    if (!empty(Settings::get('memcache')['key_prefix'])) {
      $this->messenger()->addWarning($this->t("The \$settings['memcache']['key_prefix'] is set to <em>@key_prefix</em>. It is very likely that the servers are shared with multiple sites. The statistics displayed on this page are related to the servers, not a specific site.", [
        '@key_prefix' => Settings::get('memcache')['key_prefix'],
      ]));
    }

    $bin = 'cache';
    /** @var $memcache \Drupal\memcache\DrupalMemcacheInterface */
    $memcache = $this->memcacheDriverFactory->get($bin);

    // Get a list of Memcache servers and normalize the name using "host:port".
    $servers = $memcache->getMemcache()->getServerList();
    array_walk($servers, function (&$server_data) { $server_data = $server_data['host'] . ':' . $server_data['port']; });

    // Validate the server from the query argument actually exists.
    $server = $request->query->get('server') ?? FALSE;
    if (!in_array($server, $servers)) {
      $server = FALSE;
    }

    // If there are multiple servers, build links to switch the view between
    // servers.
    $links = [];
    if (count($servers) > 1) {
      $link = Link::createFromRoute($this->t('All'), 'memcache_status.servers')->toString();
      if (!$server) {
        $links[] = '<strong>' . $link . '</strong>';
      }
      else {
        $links[] = $link;
      }

      foreach ($servers as $server_data) {
        $link = Link::createFromRoute($server_data, 'memcache_status.servers', [], ['query' => ['server' => $server_data]])->toString();
        if ($server === $server_data) {
          $links[] = '<strong>' . $link . '</strong>';
        }
        else {
          $links[] = $link;
        }
      }
    }

    // Get the servers' statistics.
    $stats = $memcache->stats($bin,'default', TRUE);
    if (empty($stats[$bin])) {
      return [];
    }

    // To calculate some statistics related to memory, we need to gather the
    // slabs' statistics.
    $raw_slabs = $memcache->stats($bin, 'slabs');

    // Normalize the result from the stats slab command, it is easier to browse.
    $slabs = [];
    foreach ($raw_slabs[$bin] as $server_name => $data) {
      unset($data['active_slabs']);
      unset($data['total_malloced']);

      foreach ($data as $key => $value) {
        [$slab, $variable] = explode(':', $key);
        $slabs[$server_name][$slab][$variable] = $value;
      }
    }

    // Calculate wasted memory, total and free chunks from the slabs' stats.
    $wasted = 0;
    $free_chunks = 0;
    $total_chunks = 0;
    foreach ($slabs as $server_name => $server_slabs) {
      // If we have filtered on a specific server, we only need to do the
      // sums on this specific server's slabs.
      if ($server && $server !== $server_name) {
        continue;
      }

      foreach ($server_slabs as $info) {
        $wasted += ($info['chunk_size'] * $info['used_chunks']) - $info['mem_requested'];
        $free_chunks += $info['free_chunks'];
        $total_chunks += $info['total_chunks'];
      }
    }

    // Before we filter and alter the data for easy rendering, add these to
    // a details fieldset so users can see raw data.
    $raw_data = [
      '#type' => 'details',
      '#title' => $this->t('Raw data'),
      '#open' => FALSE,
      [
        '#markup' => '<h6>' . $this->t('Servers stats') . '</h6><pre>' . var_export($stats, TRUE) . '</pre>',
      ],
      [
        '#markup' => '<h6>' . $this->t('Slab stats') . '</h6><pre>' . var_export($raw_slabs, TRUE) . '</pre>',
      ],
    ];

    // If we are filtering a specific server, keep only this one. Otherwise,
    // keep the totals only.
    $stats = $server ? $stats[$bin][$server] : $stats[$bin]['total'];

    $inline_template = [
      '#type' => 'inline_template',
      '#template' => '<div class="card" style="display:block; width: {{ width }}%; padding: 1.125rem; overflow-x: auto; box-sizing: border-box; margin-bottom: var(--space-l)"><h3 style="margin: 0 0 0.5rem; font-size: 1.125rem;">{{ title }}</h3>{{ content }}</div>',
      '#context' => [],
    ];

    // Build a card with memory usage (total, used, free, wasted).
    $usage = $inline_template;
    $usage['#context'] = [
      'title' => $this->t('Memory usage'),
      'content' => [
        [
          '#type' => 'table',
          '#headers' => [],
          '#rows' => [
            [$this->t('Total'), format_size($stats['limit_maxbytes'])],
            [$this->t('Used'), format_size($stats['bytes'])],
            [$this->t('Free'), format_size($stats['limit_maxbytes'] - $stats['bytes'])],
            [$this->t('Wasted'), format_size($wasted)],
          ],
        ],
        [
          '#markup' => '<a href="#usage-details" class="align-right">' . $this->t('Details') . '</a>',
        ],
      ],
      'width' => 32,
    ];

    // Build a card with the eviction information (numbers, rated, unfetched).
    $eviction = $inline_template;
    $eviction['#context'] = [
      'title' => $this->t('Eviction'),
      'content' => [
        [
          '#type' => 'table',
          '#headers' => [],
          '#rows' => [
            [$this->t('Evicted items'), $stats['evictions']],
            [$this->t('Eviction rate'), $this->t('@count evictions / second', ['@count' => round($stats['evictions'] / $stats['uptime'], 2)])],
            [$this->t('Expired unfetched'), $stats['expired_unfetched']],
            [$this->t('Evicted unfetched'), $stats['evicted_unfetched']],
          ],
        ],
        [
          '#markup' => '<a href="#eviction-details" class="align-right">' . $this->t('Details') . '</a>',
        ],
      ],
      'width' => 32,
    ];

    // Build a card with the hit & miss rates.
    $hit = $inline_template;
    $hit['#context'] = [
      'title' => $this->t('Hit & Miss rate'),
      'content' => [
        [
          '#type' => 'table',
          '#headers' => [],
          '#rows' => [
            [$this->t('Hits'), round(($stats['get_hits'] * 100) / ($stats['cmd_get'] ?? 1), 2) . '%'],
            [$this->t('Misses'), round(($stats['get_misses'] * 100) / ($stats['cmd_get'] ?? 1), 2) . '%'],
          ],
        ],
        [
          '#markup' => '<a href="#hit-miss-details" class="align-right">' . $this->t('Details') . '</a>',
        ],
      ],
      'width' => 32,
    ];

    $usage_details = [
      '#type' => 'details',
      '#title' => $this->t('Memory usage details'),
      '#open' => TRUE,
      [
        '#theme' => 'table',
        '#headers' => [],
        '#rows' => [
          [$this->t('Total chunks'), $total_chunks],
          [$this->t('Used chunks'), $total_chunks - $free_chunks],
        ],
      ],
      [
        '#markup' => $this->t('Memcache stores items in <em>slabs</em>, which are fixed-size chunks of memory. The allocated memory is divided into different slabs based on item sizes, allowing efficient storage and retrieval of cached data. Items are stored in a slab that hosts chunks of the same size or larger than the item. When Memcache stores an item which the size is smaller than the chunk size, the remaining space is considered wasted. For example, if Memcache stores a 90 bytes item in a 100 bytes chunk, 10 bytes are considered wasted. This may explain evictions despite Memcache not being 100% full.'),
      ],
      '#attributes' => [
        'id' => ['usage-details'],
      ],
    ];

    $eviction_details = [
      '#type' => 'details',
      '#title' => $this->t('Eviction details'),
      '#open' => TRUE,
      [
        '#markup' => $this->t('Memcache Least Recently Used (LRU) algorithm responsible for determining which item to evict is described in details on a <a href="https://memcached.org/blog/modern-lru/">Memcached blog post</a>.<br/>Expired unfetched is the number of expired items reclaimed from the LRU which were never touched after being set. Evicted unfetched is number of valid items evicted from the LRU which were never touched after being set.'),
      ],
      '#attributes' => [
        'id' => ['eviction-details'],
      ],
    ];

    $hit_miss_details = [
      '#type' => 'details',
      '#title' => $this->t('Hit & Miss details'),
      '#open' => TRUE,
      [
        '#markup' => '<h6>' . $this->t('Get commands') . '</h6>',
      ],
      [
        '#theme' => 'table',
        '#headers' => [],
        '#rows' => [
          [$this->t('Hits'), $stats['get_hits'] . ' (' . round(($stats['get_hits'] * 100) / ($stats['cmd_get'] ?? 1), 2) . '%)'],
          [$this->t('Miss'), $stats['get_misses'] . ' (' . round(($stats['get_misses'] * 100) / ($stats['cmd_get'] ?? 1), 2) . '%)'],
          [$this->t('Rate'), $this->t('@count requests / second', ['@count' => round($stats['cmd_get'] / $stats['uptime'], 2)])],
        ],
      ],
      [
        '#markup' => '<h6>' . $this->t('Delete commands') . '</h6>',
      ],
      [
        '#theme' => 'table',
        '#headers' => [],
        '#rows' => [
          [$this->t('Hits'), $stats['delete_hits'] . ' (' . round(($stats['delete_hits'] * 100) / ($stats['delete_hits'] + $stats['delete_misses']), 2) . '%)'],
          [$this->t('Miss'), $stats['delete_misses'] . ' (' . round(($stats['delete_misses'] * 100) / ($stats['delete_hits'] + $stats['delete_misses']), 2) . '%)'],
          [$this->t('Rate'), $this->t('@count requests / second', ['@count' => round(($stats['delete_hits'] + $stats['delete_misses']) / $stats['uptime'], 2)])],
        ],
      ],
      [
        '#markup' => $this->t('Rates are calculated by dividing the total number of get/delete requests by the number of seconds since the server restart.')
      ]
    ];

    $content = [$usage, $eviction, $hit];
    return [
      [
        '#markup' => !empty($links) ? '<div class="compact-link">' . implode(' | ', $links) . '</div>' : '',
      ],
      [
        '#type' => 'inline_template',
        '#template' => '<div style="display: flex; flex-wrap: wrap; justify-content: space-between;">{{ content }}</div>',
        '#context' => [
          'content' => ['#markup' => $this->renderer->render($content)],
        ],
      ],
      $usage_details,
      $eviction_details,
      $hit_miss_details,
      $raw_data,
    ];
  }

}
