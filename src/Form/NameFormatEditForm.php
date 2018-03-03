<?php

namespace Drupal\name\Form;

use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a form controller for adding a name format.
 */
class NameFormatEditForm extends NameFormatFormBase {

  public function delete(array $form, FormStateInterface $form_state) {
    $form_state['redirect_route'] = array(
      'route_name' => 'name_format_delete_confirm',
      'route_parameters' => array(
        'name_format' => $this->entity->id(),
      ),
    );
  }

}
