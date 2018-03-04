<?php

namespace Drupal\name\Form;

use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a form controller for adding a name format.
 */
class NameFormatEditForm extends NameFormatFormBase {

  /**
   * {@inheritdoc}
   */
  public function delete(array $form, FormStateInterface $form_state) {
    $form_state['redirect_route'] = [
      'route_name' => 'name_format_delete_confirm',
      'route_parameters' => [
        'name_format' => $this->entity->id(),
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    parent::save($form, $form_state);
    drupal_set_message($this->t('Name format %label has been updated.', ['%label' => $this->entity->label()]));
  }

}
