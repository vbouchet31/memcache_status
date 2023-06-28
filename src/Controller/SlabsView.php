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
 * Defines the controller to list down the slabs statistics.
 */
class SlabsView extends ControllerBase {

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
   * Render the "Slabs" page displaying the slabs statistics.
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
      $link = Link::createFromRoute($this->t('All'), 'memcache_status.slabs')->toString();
      if (!$server) {
        $links[] = '<strong>' . $link . '</strong>';
      }
      else {
        $links[] = $link;
      }

      foreach ($servers as $server_data) {
        $link = Link::createFromRoute($server_data, 'memcache_status.slabs', [], ['query' => ['server' => $server_data]])->toString();
        if ($server === $server_data) {
          $links[] = '<strong>' . $link . '</strong>';
        }
        else {
          $links[] = $link;
        }
      }
    }

    // We need some server stats like uptime to calculate some metrics.
    $stats = $memcache->stats($bin,'default', TRUE);

    // Normalize the result from the stats slab command, it is easier to browse.
    $raw_slabs = $memcache->stats($bin, 'slabs', TRUE);
    $slabs = [];
    foreach ($raw_slabs[$bin] as $server_name => $data) {
      if ($server_name === 'total') {
        continue;
      }
      unset($data['active_slabs']);
      unset($data['total_malloced']);

      foreach ($data as $key => $value) {
        [$slab, $variable] = explode(':', $key);
        $slabs[$server_name][$slab][$variable] = $value;
      }
    }

    // If we are filtering a specific server, keep only this one.
    if ($server) {
      $slabs = [
        $server => $slabs[$server],
      ];
    }

    $inline_template = [
      '#type' => 'inline_template',
      '#template' => '<div class="card" style="display:block; width: {{ width }}%; padding: 1.125rem; overflow-x: auto; box-sizing: border-box; margin-bottom: var(--space-l)"><h3 style="margin: 0 0 0.5rem; font-size: 1.125rem;">{{ title }}</h3>{{ content }}</div>',
      '#context' => [],
    ];

    $build = [];
    foreach ($slabs as $server_name => $slab_data) {
      // If there is multiple servers, be sure the "View items" link contains
      // the filtering per server.
      $default_query = $servers ? ['server[]' => $server_name] : [];

      $server_build = [];
      foreach ($slab_data as $index => $data) {
        $slab_item = $inline_template;
        $slab_item['#context'] = [
          'title' => $this->t('Slab @index', ['@index' => $index]),
          'content' => [
            [
              '#theme' => 'table',
              '#headers' => [],
              '#rows' => [
                [$this->t('Chunk size'), format_size($data['chunk_size'])],
                [$this->t('Used chunks'), $data['used_chunks'] . ' / ' . $data['total_chunks']],
                [$this->t('Slab size'), format_size($data['chunk_size'] * $data['total_chunks'])],
                [$this->t('Free space'), format_size(($data['total_chunks'] - $data['used_chunks']) * $data['chunk_size'])],
                [$this->t('Allocated space'), format_size($data['mem_requested'])],
                [$this->t('Wasted'), format_size($data['chunk_size'] * $data['used_chunks'] - $data['mem_requested'])],
                [$this->t('Hits'), $this->t('@hits requests / second', ['@hits' => round($data['get_hits'] / $stats[$bin][$server_name]['uptime'], 2)])],
              ],
            ],
            [
              '#markup' => Link::createFromRoute($this->t('View items'), 'view.memcache_items.page_1', [], ['query' => $default_query + ['slab' => $index], 'attributes' => ['class' => ['align-right']]])->toString(),
            ],
          ],
          'width' => 19,
        ];

        $server_build[] = $slab_item;
      }

      // If there is multiple servers to be displayed, group the slabs per
      // server in a details fieldset.
      if (count($slabs) > 1) {
        $build[] = [
          '#type' => 'details',
          '#title' => $server_name,
          '#open' => TRUE,
          [
            '#type' => 'inline_template',
            '#template' => '<div style="display: flex; flex-wrap: wrap; justify-content: space-between;">{{ content }}</div>',
            '#context' => [
              'content' => ['#markup' => $this->renderer->render($server_build)],
            ],
          ],
        ];
      }
      else {
        $build[] = [
          '#type' => 'inline_template',
          '#template' => '<div style="display: flex; flex-wrap: wrap; justify-content: space-between;">{{ content }}</div>',
          '#context' => [
            'content' => ['#markup' => $this->renderer->render($server_build)],
          ],
        ];
      }
    }

    return [
      [
        '#markup' => !empty($links) ? '<div class="compact-link">' . implode(' | ', $links) . '</div>' : '',
      ],
      $build,
    ];
  }

}
