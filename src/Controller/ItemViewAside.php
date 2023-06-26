<?php

namespace Drupal\memcache_status\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\memcache\Driver\MemcacheDriverFactory;
use Drupal\memcache_status\MemcacheStatusHelper;
use Drupal\memcache_status\Render\FilteredMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\HtmlDumper;

class ItemViewAside extends ControllerBase {

  public function __construct(MemcacheDriverFactory $memcacheDriverFactory, MemcacheStatusHelper $memcache_status_helper) {
    $this->memcacheDriverFactory = $memcacheDriverFactory;
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
      $container->get('memcache_status.memcache_helper'),
    );
  }

  public function render() {
    $bin = 'default';
    $bin = $this->memcacheStatusHelper->getBinMapping($bin);
    /** @var $memcache \Drupal\memcache\DrupalMemcacheInterface */
    $memcache = $this->memcacheDriverFactory->get($bin);

    $keys = [];
    foreach (\Drupal::service('tempstore.private')->get('memcache_item_view_aside')->get(\Drupal::currentUser()->id()) ?? [] as $key) {
      $keys[] = rawurldecode($key);
    }

    $data = $memcache->getMemcache()->getMulti($keys);
    $rows = [];

    // This is very much inspired by Devel module.
    $cloner = new VarCloner();
    $dumper = new HtmlDumper();
    // @TODO: Make this configurable (no UI).
    $dumper->setTheme('light');
    $dumper->setDisplayOptions(['maxDepth' => 25]);

    foreach ($data as $d) {
      if (isset($d->data)) {
        $output = fopen('php://memory', 'r+b');
        $dumper->dump($cloner->cloneVar($d->data), $output);
        $output = stream_get_contents($output, -1, 0);

        $rows[0][] = ['data' => ['#markup' => FilteredMarkup::create($output)]];
      }
    }

    return [
      [
        '#theme'  => 'table',
        '#header' => [],
        '#rows'   => $rows,
      ],
    ];
  }
}
