<?php

namespace Drupal\memcache_status\Form;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Database\Connection;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\memcache\Driver\MemcacheDriverFactory;
use Drupal\memcache_status\MemcacheStatusHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ItemMultipleFlushForm extends ConfirmFormBase {

  /**
   * ModalFormContactController constructor.
   */
  public function __construct(Connection $database, MemcacheDriverFactory $memcacheDriverFactory, MemcacheStatusHelper $memcache_status_helper) {
    $this->database = $database;
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
      $container->get('database'),
      $container->get('memcache.factory'),
      $container->get('memcache_status.memcache_helper'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'memcache_status_flush_multiple_item_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    foreach (\Drupal::service('tempstore.private')->get('memcache_item_bulk_flush')->get(\Drupal::currentUser()->id()) as $key) {
      $this->keys[] = urldecode(rawurldecode($key));
    }

    $form['keys'] = [
      '#markup' => '<pre>' . print_r($this->keys, TRUE) . '</pre>',
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to flush these @count items?', ['@count' => count($this->keys ?? [])]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('view.memcache_items.page_1');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Flush');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $bin = 'default';
    $bin = $this->memcacheStatusHelper->getBinMapping($bin);
    /** @var $memcache \Drupal\memcache\DrupalMemcacheInterface */
    $memcache = $this->memcacheDriverFactory->get($bin);

    foreach($this->keys as &$key) {
      $key = urlencode($key);
    }

    // @TODO: Check if we need to use batch for mass flushing.
    //if (count($this->keys) < 10) {
      $result = $memcache->getMemcache()->deleteMulti($this->keys);

      if ($result) {
        foreach($this->keys as &$key) {
          $key = urldecode($key);
        }
        $count = $this->database->delete('memcache_status_dump_data')->condition('raw_key', $this->keys, 'IN')->execute();

        if ($count == count($this->keys)) {
          Cache::invalidateTags(['memcache_list:items']);
          $this->messenger()->addMessage($this->t('The items have been flushed from memcache and removed from the items dumped into the database.'));
        }
        else {
          $this->messenger()->addWarning($this->t('The items has been flushed from memcache but has not been all found in the items dumped into the database.'));
        }
      }
    //}

    \Drupal::service('tempstore.private')->get('memcache_item_bulk_flush')->delete(\Drupal::currentUser()->id());
  }

}
