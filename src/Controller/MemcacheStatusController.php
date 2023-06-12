<?php

namespace Drupal\memcache_status\Controller;

use Drupal\Component\Datetime\Time;
use Drupal\Component\EventDispatcher\ContainerAwareEventDispatcher;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Link;
use Drupal\Core\Site\Settings;
use Drupal\Core\Url;
use Drupal\memcache\Driver\MemcacheDriverFactory;
use Drupal\memcache_status\MemcacheStatusHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * VBO.
 */
class MemcacheStatusController extends ControllerBase {

  /**
   * ModalFormContactController constructor.
   */
  public function __construct(MemcacheDriverFactory $memcacheDriverFactory, ContainerAwareEventDispatcher $dispatcher, DateFormatterInterface $dateFormatter, Time $time, MemcacheStatusHelper $memcache_status_helper) {
    $this->memcacheDriverFactory = $memcacheDriverFactory;
    $this->dispatcher = $dispatcher;
    $this->dateFormatter = $dateFormatter;
    $this->time = $time;
    $this->memcacheStatusHelper = $memcache_status_helper;
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
      $container->get('memcache_status.memcache_helper'),
    );
  }

  // @TODO: Rework so it uses data from the database instead.
  public function RenderSlab($server, $slab) {
    $bin = 'default';
    $bin = $this->memcacheStatusHelper->getBinMapping($bin);
    /** @var $memcache \Drupal\memcache\DrupalMemcacheInterface */
    $memcache = $this->memcacheDriverFactory->get($bin);

    $servers = [];
    if ($server === 'all') {
      foreach ($memcache->getMemcache()->getServerList() as $server_data) {
        $servers[] = $server_data['host'] . ':' . $server_data['port'];
      }
    }
    else {
      $servers[] = $server;
    }

    $items = [];
    foreach ($servers as $s) {
      [$host, $port] = explode(':', $s);
      $tmp = $this->memcacheStatusHelper->sendMemcacheCommand('lru_crawler metadump ' . $slab, $host, $port, 'parseDumpResult');

      $items = array_merge($items, $tmp);
    }

    $headers = [
      [
        'data' => $this->t('Server'),
        'class' => $server === 'all' ? '' : 'hidden',
      ],
      [
        'data' => $this->t('Slab #'),
        'class' => $slab === 'all' ? '' : 'hidden',
      ],
      $this->t('Bin'),
      $this->t('Cid'),
      $this->t('Expiration time'),
      $this->t('Last access'),
      $this->t('Size'),
    ];

    $rows = [];
    foreach ($items as $item) {
      $rows[] = [
        [
          'data' => $item['server'],
          'class' => $server === 'all' ? '' : 'hidden',
        ],
        [
          'data' => $item['slab'],
          'class' => $slab === 'all' ? '' : 'hidden',
        ],
        $item['bin'],
        $item['cid'],
        ($item['expire'] === -1) ? $this->t('Never') : $item['expire'],
        $item['fetched'] ? $item['last_access'] : $this->t('Never'),
        format_size($item['size']),
      ];
    }

    \Drupal::messenger()->addWarning($this->t('This is work in progress interface. Check the "Raw data" fieldset to see all the available data.'));

    return [
      [
        '#theme' => 'table',
        '#header' => $headers,
        '#rows' => $rows,
      ],
      [
        '#type' => 'details',
        '#title' => $this->t('Raw data'),
        '#open' => FALSE,
        [
          '#markup' => $this->t('These raw data are given by the <em>lru_crawler metadump %slab</em> command.')
        ],
        [
          '#type' => 'markup',
          '#markup' => '<pre>' . var_export($items, TRUE) . '</pre>',
        ],
      ],
    ];
  }

  // @TODO: Move to service helper.
  public function parseCommandResult($str) {
    $res = [];
    $lines = explode("\r\n", $str);

    $cnt = count($lines);
    for($i=0; $i< $cnt; $i++){
      $line = $lines[$i];
      $l = explode(' ',$line,3);
      if (count($l)==3){
        $res[$l[0]][$l[1]]=$l[2];
        if ($l[0]=='VALUE'){ // next line is the value
          $res[$l[0]][$l[1]] = array();
          [$flag,$size]=explode(' ',$l[2]);
          $res[$l[0]][$l[1]]['stat']=array('flag'=>$flag,'size'=>$size);
          $res[$l[0]][$l[1]]['value']=$lines[++$i];
        }
      }elseif( $l[0] == 'VERSION' ){
        return $l[1];
      }elseif($line=='DELETED' || $line=='NOT_FOUND' || $line=='OK' || $line=='RESET'){
        return $line;
      }
    }
    return $res;
  }

  public function RenderSlabs($server) {
    $server_original = $server;

    $bin = 'default';
    $bin = $this->memcacheStatusHelper->getBinMapping($bin);
    /** @var $memcache \Drupal\memcache\DrupalMemcacheInterface */
    $memcache = $this->memcacheDriverFactory->get($bin);

    $aggregate = ($server === 'all');
    $server = ($server === 'all') ? 'total' : $server;

    // @TODO: Aggregate does not work properly for slabs and may even not make
    // sense. See https://www.drupal.org/project/memcache/issues/3365640.

    $slabs = $memcache->stats($bin, 'slabs', $aggregate)[$bin][$server];
    $items = $memcache->stats($bin, 'items', $aggregate)[$bin][$server];

    $active_slabs = $slabs['active_slabs'];
    unset($slabs['active_slabs']);
    $total_malloced = $slabs['total_malloced'];
    unset($slabs['total_malloced']);

    // Regroup values per slab.
    $data = [];
    foreach ($slabs as $key => $value) {
      [$slab, $variable] = explode(':', $key);
      $data[$slab][$variable] = $value;
    }
    foreach ($items as $key => $value) {
      [, $slab, $variable] = explode(':', $key);
      $data[$slab][$variable] = $value;
    }

    $headers = [
      $this->t('Slab #'),
      $this->t('Item size'),
      $this->t('Usage (items)'),
    ];

    $rows = [];
    foreach ($data as $id => $values) {
      $rows[] = [
        Link::fromTextandUrl($id, Url::fromUri('base:/admin/reports/memcache-status/' . str_replace('/', '!', $server_original) . '/slab/' . $id))->toString(),
        format_size($values['chunk_size']),
        $values['used_chunks'] . ' / ' . $values['total_chunks'] . ' (' . round((($values['used_chunks']*100)/$values['total_chunks']), 2) . '%)',
      ];
    }

    \Drupal::messenger()->addWarning($this->t('This is work in progress interface. Check the "Raw data" fieldset to see all the available data.'));

    return [
      [
        '#type' => 'link',
        '#title' => 'View all slabs content',
        '#url' => Url::fromUri('base:/admin/reports/memcache-status/' . str_replace('/', '!', $server_original) . '/slab/all'),
        '#attributes' => [
          'class' => ['button', 'button--action', 'button--primary'],
        ],
      ],
      [
        '#theme'  => 'table',
        '#header' => $headers,
        '#rows'   => $rows,
      ],
      [
        '#type' => 'details',
        '#title' => $this->t('Raw data'),
        '#open' => FALSE,
        [
          '#markup' => $this->t('These raw data are given by the <em>stats slabs</em> and <em>stats items</em> commands.')
        ],
        [
          '#type' => 'markup',
          '#markup' => '<pre>' . var_export($slabs, TRUE) . '</pre>',
        ],
        [
          '#type' => 'markup',
          '#markup' => '<pre>' . var_export($items, TRUE) . '</pre>',
        ],
      ],
    ];
  }

  public function RenderServers() {
    if (!empty(Settings::get('memcache')['key_prefix'])) {
      \Drupal::messenger()->addWarning($this->t("The \$settings['memcache']['key_prefix'] is set to <em>@key_prefix</em>. It is very likely that the servers are shared with multiple sites. The statistics displayed on this page are related to the servers, not a specific site.", [
        '@key_prefix' => Settings::get('memcache')['key_prefix'],
      ]));
    }

    // @TODO: Check if it must be dynamic.
    $bin = 'cache';
    /** @var $memcache \Drupal\memcache\DrupalMemcacheInterface */
    $memcache = $this->memcacheDriverFactory->get($bin);

    $stats = $memcache->stats($bin, 'default', TRUE);
    if (empty($stats[$bin])) {
      return [];
    }

    $config = \Drupal::config('memcache_status.settings');
    $server_statistics = array_fill_keys($config->get('server_statistics'), TRUE);

    $rows = [];
    foreach ($server_statistics as $key => $value) {
      $row = [];
      $index = 2;
      foreach ($stats[$bin] as $server => $bin_stats) {
        if ($server === 'total') {
          continue;
        }

        if (!isset($bin_stats[$key])) {
          $row = [];
          continue 2;
        }

        $row[$index++] = $bin_stats[$key];
      }

      if (!empty($row)) {
        $row[0] = $key;
        $row[1] = $stats['total'][$key] ?? '';
      }
      ksort($row);
      $rows[] = $row;
    }

    $headers = [
      '',
      Link::fromTextandUrl($this->t('Totals'), Url::fromUri('base:/admin/reports/memcache-status/all/slabs'))->toString(),
    ];
    foreach ($stats[$bin] as $server => $bin_stats) {
      if ($server === 'total') {
        continue;
      }
      $headers[] = Link::fromTextandUrl($server, Url::fromUri('base:/admin/reports/memcache-status/' . str_replace('/', '!', $server) . '/slabs'))->toString();
    }

    \Drupal::messenger()->addWarning($this->t('This is work in progress interface. Check the "Raw data" fieldset to see all the available data.'));

    return [
      [
        '#theme' => 'table',
        '#header' => $headers,
        '#rows' => $rows,
      ],
      [
        '#type' => 'details',
        '#title' => $this->t('Raw data'),
        '#open' => FALSE,
        [
          '#markup' => $this->t('These raw data are given by the <em>stats default</em> command.')
        ],
        [
          '#type' => 'markup',
          '#markup' => '<pre>' . var_export($stats, TRUE) . '</pre>',
        ],
      ],
    ];
  }
}
