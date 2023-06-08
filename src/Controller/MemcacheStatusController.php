<?php

namespace Drupal\memcache_status\Controller;

use Drupal\Component\Datetime\Time;
use Drupal\Component\EventDispatcher\ContainerAwareEventDispatcher;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\memcache\Driver\MemcacheDriverFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * VBO.
 */
class MemcacheStatusController extends ControllerBase {

  /**
   * ModalFormContactController constructor.
   *
   * @param \Drupal\Core\Form\FormBuilder $form_builder
   *   The form builder.
   */
  public function __construct(MemcacheDriverFactory $memcacheDriverFactory, ContainerAwareEventDispatcher $dispatcher, DateFormatterInterface $dateFormatter, Time $time) {
    $this->memcacheDriverFactory = $memcacheDriverFactory;
    $this->dispatcher = $dispatcher;
    $this->dateFormatter = $dateFormatter;
    $this->time = $time;
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
    );
  }

  public function RenderSlab($server, $slab) {
    $bin = 'default';
    $bin = $this->getBinMapping($bin);
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
    foreach ($servers as $server) {
      [$host, $port] = explode(':', $server);
      $items = array_merge($items, $this->sendMemcacheCommand('lru_crawler metadump ' . $slab, $host, $port, 'parseDumpResult'));
    }

    $headers = [
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
          'data' => $item['slab'],
          'class' => $slab === 'all' ? '' : 'hidden',
        ],
        $item['bin'],
        $item['cid'],
        ($item['expire'] === -1) ? $this->t('Never') : $item['expire'],
        $item['fetch'] ? $item['last_access'] : $this->t('Never'),
        $item['size'],
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
  public function sendMemcacheCommand($command, $host = 'memcache', $port = 11211, $callback = 'parseCommandResult') {
    $bin = 'default';
    $bin = $this->getBinMapping($bin);
    /** @var $memcache \Drupal\memcache\DrupalMemcacheInterface */
    $memcache = $this->memcacheDriverFactory->get($bin);

    $s = @fsockopen($host, $port);
    /*if (!$s){
      die("Cant connect to:" . $host . ':' . $port);
    }*/

    fwrite($s, $command . "\r\n");

    $buf = '';
    while (!feof($s)) {
      $buf .= fgets($s, 256);
      if (str_contains($buf, "END\r\n")) {
        break;
      }
    }
    fclose($s);

    return $this->{$callback}($buf);
  }

  // @TODO: Move to service helper.
  public function parseDumpResult($str) {
    $pattern = '/key=([^ ]+)\s+exp=(-?\d+)\s+la=(-?\d+)\s+cas=(-?\d+)\s+fetch=(\w+)\s+cls=(-?\d+)\s+size=(\d+)/';

    $items = [];
    foreach (explode("\n", $str) as $line) {
      $line = trim($line);

      if (empty($line) || $line === 'END') {
        continue;
      }

      $matches = [];
      preg_match($pattern, $line, $matches);

      if (count($matches) !== 8) {
        continue;
      }

      // CID is urlencoded by Drupal but also by Memcache hence we need to
      // decode twice.
      list($bin, $cid) = explode(':', urldecode(urldecode($matches[1])), 2);
      $cid = substr($cid, 1);

      $items[$cid] = [
        'bin' => $bin,
        'cid' => $cid,
        'expire' => (int) $matches[2],
        'last_access' => (int) $matches[3],
        'cas' => (int) $matches[4],
        'fetch' => $matches[5] === 'yes' ? TRUE : FALSE,
        'slab' => (int) $matches[6],
        'size' => (int) $matches[7],
      ];
    }

    return $items;
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
          list ($flag,$size)=explode(' ',$l[2]);
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
    $bin = $this->getBinMapping($bin);
    /** @var $memcache \Drupal\memcache\DrupalMemcacheInterface */
    $memcache = $this->memcacheDriverFactory->get($bin);

    $aggregate = ($server === 'all');
    $server = ($server === 'all') ? 'total' : $server;

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
      $this->t('Usage'),
    ];

    $rows = [];
    foreach ($data as $id => $values) {
      $rows[] = [
        Link::fromTextandUrl($id, Url::fromUri('base:/admin/reports/memcache-status/' . str_replace('/', '!', $server_original) . '/slab/' . $id))->toString(),
        $values['chunk_size'],
        $values['used_chunks'] . ' / ' . $values['total_chunks'] . ' (' . (($values['used_chunks']*100)/$values['total_chunks']) . '%)',
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
    // @TODO: Check if it must be dynamic.
    $bin = 'cache';
    /** @var $memcache \Drupal\memcache\DrupalMemcacheInterface */
    $memcache = $this->memcacheDriverFactory->get($bin);

    $stats = $memcache->stats($bin, 'default', TRUE);
    if (empty($stats[$bin])) {
      return [];
    }

    $headers = [
      '',
      Link::fromTextandUrl($this->t('Totals'), Url::fromUri('base:/admin/reports/memcache-status/all/slabs'))->toString(),
    ];

    $rows = [
      'usage' => [
        $this->t('Usage'),
        $stats[$bin]['total']['bytes'] . ' / ' . $stats[$bin]['total']['limit_maxbytes'] . ' (' . (($stats[$bin]['total']['bytes']*100)/$stats[$bin]['total']['limit_maxbytes']) . '%)'
      ],
    ];

    unset($stats[$bin]['total']);
    foreach ($stats[$bin] as $server => $bin_stats) {
      $headers[] = Link::fromTextandUrl($server, Url::fromUri('base:/admin/reports/memcache-status/' . str_replace('/', '!', $server) . '/slabs'))->toString();

      $rows['usage'][] = $bin_stats['bytes'] . ' / ' . $bin_stats['limit_maxbytes'] . ' (' . (($bin_stats['bytes']*100)/$bin_stats['limit_maxbytes']) . '%)';
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

  /**
   * Helper function, reverse map the memcache_bins variable.
   */
  protected function getBinMapping($bin = 'cache') {
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
   * Helper function. Returns the bin name.
   */
  protected function defaultBin($bin) {
    if ($bin == 'default') {
      return 'cache';
    }

    return $bin;
  }
}
