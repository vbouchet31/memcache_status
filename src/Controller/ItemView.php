<?php

namespace Drupal\memcache_status\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\memcache\Driver\MemcacheDriverFactory;
use Drupal\memcache_status\MemcacheStatusHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ItemView extends ControllerBase {

  /**
   * ModalFormContactController constructor.
   */
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

  public function render($key) {
    $bin = 'default';
    $bin = $this->memcacheStatusHelper->getBinMapping($bin);
    /** @var $memcache \Drupal\memcache\DrupalMemcacheInterface */
    $memcache = $this->memcacheDriverFactory->get($bin);

    $data = $memcache->getMemcache()->get(urlencode($key));

    if ($data) {
      return [
        '#markup' => '<pre>' . print_r($data, TRUE) . '</pre>',
      ];
    }

    return [
      '#markup' => $this->t('Invalid key'),
    ];


  }
}
