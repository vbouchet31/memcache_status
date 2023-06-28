<?php

namespace Drupal\memcache_status\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Url;
use Drupal\memcache\Driver\MemcacheDriverFactory;
use Drupal\memcache_status\MemcacheStatusHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ItemView extends ControllerBase {

  public function __construct(MemcacheDriverFactory $memcacheDriverFactory, MemcacheStatusHelper $memcache_status_helper, DateFormatterInterface $date_formatter) {
    $this->memcacheDriverFactory = $memcacheDriverFactory;
    $this->memcacheStatusHelper = $memcache_status_helper;
    $this->dateFormatter = $date_formatter;
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
      $container->get('date.formatter'),
    );
  }

  public function render($key) {
    $bin = 'default';
    $bin = $this->memcacheStatusHelper->getBinMapping($bin);
    /** @var $memcache \Drupal\memcache\DrupalMemcacheInterface */
    $memcache = $this->memcacheDriverFactory->get($bin);

    $data = $memcache->getMemcache()->get(urlencode(rawurldecode($key)));

    if ($data) {
      return [
        [
          '#theme'  => 'table',
          '#header' => [],
          '#rows'   => [
            ['cid', $data->cid],
            ['created', $this->dateFormatter->format((int) $data->created)],
            ['expire', $data->expire],
            ['tags', ['data' => [
              '#theme' => 'item_list',
              '#items' => $data->tags,
            ]]],
            ['checksum', $data->checksum],
            ['data', [ 'data' => [
              '#markup' => '<pre>' . var_export($data->data, TRUE) . '</pre>',
            ]]],
          ],
        ],
        [
          '#type' => 'link',
          '#title' => $this->t('Flush'),
          // @TODO: Use Route instead and forward the destination.
          '#url' => Url::fromUri('base:/admin/reports/memcache-status/item/' . rawurlencode($key) . '/flush'),
          '#attributes' => [
            'class' => ['button', 'button--secondary'],
          ],
        ],
      ];
    }

    return [
      '#markup' => $this->t('Invalid key'),
    ];
  }
}
