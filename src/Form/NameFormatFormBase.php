<?php

namespace Drupal\name\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\name\Entity\NameFormat;

/**
 * Provides a base form controller for date formats.
 */
abstract class NameFormatFormBase extends EntityForm {

  /**
   * {@inheritdoc}
   */
  public function exists($entity_id, array $element, FormStateInterface $form_state) {
    return NameFormat::load($entity_id);
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $element = parent::form($form, $form_state);

    $element['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
      '#default_value' => $this->entity->label(),
      '#maxlength' => 255,
      '#required' => TRUE,
    ];

    $element['id'] = [
      '#type' => 'machine_name',
      '#title' => $this->t('Machine-readable name'),
      '#description' => $this->t('A unique machine-readable name. Can only contain lowercase letters, numbers, and underscores.'),
      '#disabled' => !$this->entity->isNew(),
      '#default_value' => $this->entity->id(),
      '#machine_name' => [
        'exists' => [$this, 'exists'],
      ],
    ];

    $element['pattern'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Format'),
      '#default_value' => $this->entity->get('pattern'),
      '#maxlength' => 255,
      '#required' => TRUE,
    ];

    $element['help'] = $this->nameFormatHelp();

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $this->entity->save();
    $form_state->setRedirect('name.name_format_list');
  }

  /**
   * Help box.
   *
   * @return array
   *   FAPI build of the help section.
   */
  public function nameFormatHelp() {
    module_load_include('inc', 'name', 'name.admin');
    return _name_get_name_format_help_form();
  }

}
