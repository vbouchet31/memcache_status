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

class ItemFlushConfirmForm extends ConfirmFormBase {

  protected $key;

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
    return 'memcache_status_flush_item_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, string $key = NULL) {
    $this->key = rawurldecode($key);
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to flush the item %key?', ['%key' => $this->key]);
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

    $result = $memcache->getMemcache()->delete(urlencode($this->key));
    if ($result) {
      $count = $this->database->delete('memcache_status_dump_data')->condition('raw_key', $this->key)->execute();
      if ($count) {
        Cache::invalidateTags(['memcache_list:items']);
        $this->messenger()->addMessage($this->t('The item %key has been flushed from memcache and removed from the items dumped into the database.', ['%key' => $this->key]));
      }
      else {
        $this->messenger()->addWarning($this->t('The item %key has been flushed from memcache but has not been found in the items dumped into the database.', ['%key' => $this->key]));
      }
    }
    else {
      \Drupal::messenger()->addError($this->t('The item %key has not been flushed from memcache as it does not exist.', ['%key' => $this->key]));
    }
  }

}
