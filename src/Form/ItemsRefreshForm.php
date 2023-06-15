<?php

namespace Drupal\memcache_status\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\memcache_status\MemcacheStatusHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ItemsRefreshForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function __construct(MemcacheStatusHelper $memcache_status_helper) {
    $this->memcacheStatusHelper = $memcache_status_helper;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('memcache_status.memcache_helper')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'memcache_status_refresh_items_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $memcache_status_helper = \Drupal::service('memcache_status.memcache_helper');

    $bin = 'default';
    $bin = $memcache_status_helper->getBinMapping($bin);
    /** @var $memcache \Drupal\memcache\DrupalMemcacheInterface */
    $memcache = $memcache_status_helper->memcacheDriverFactory->get($bin);

    foreach ($memcache->getMemcache()->getServerList() as $server_data) {
      $slabs = $memcache->stats($bin, 'slabs')[$bin][$server_data['host'] . ':' . $server_data['port']];

      unset($slabs['active_slabs']);
      unset($slabs['total_malloced']);

      $data = [];
      foreach ($slabs as $key => $value) {
        [$slab, $variable] = explode(':', $key);
        $data[$slab][$variable] = $value;
      }

      $options = [];
      foreach ($data as $id => $info) {
        $options[$id] = $this->t('Slab @id (@count items)', ['@id' => $id, '@count' => $info['used_chunks']]);
      }

      $form[$server_data['host'] . ':' . $server_data['port']] = [
        '#type' => 'checkboxes',
        '#title' => $server_data['host'] . ':' . $server_data['port'],
        '#options' => $options,
        '#default_value' => array_keys($options),
      ];
    }

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => t('Submit'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_state->cleanValues();

    $operations = [];
    foreach ($form_state->getValues() as $server => $slabs) {
      $slabs = array_filter($slabs);

      foreach ($slabs as $slab) {
        $operations[] = [
          'memcache_status_process_refresh_batch', [$server, $slab]
        ];
      }
    }

    if (!empty($operations)) {
      \Drupal::database()->truncate('memcache_status_dump_data')->execute();

      $batch = [
        'title' => $this->t('Refreshing memcache items...'),
        'operations' => $operations,
        'finished' => 'memcache_status_finish_refresh_batch',
      ];

      batch_set($batch);

      $this->messenger()->addMessage($this->t('The memcache items metadata have been refreshed.'));
    }
    else {
      $this->messenger()->addMessage($this->t('No slab have been selected'));
    }

    $form_state->setRedirect('view.memcache_items.page_1');
  }

}
