<?php

namespace Drupal\memcache_status\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

class ItemEditForm extends FormBase {

  public function getFormId() {
    return 'memcache_status_item_edit_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    return [
      '#markup' => 'WIP',
    ];
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    return TRUE;
  }

}
