<?php

namespace Drupal\name\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\name\NameFormatParser;
use Drupal\name\Entity\NameFormat;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a base form controller for date formats.
 */
class NameFormatForm extends EntityForm {

  /**
   * The name format parser for token help.
   *
   * @var \Drupal\name\NameFormatParser
   */
  protected $parser;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('name.format_parser')
    );
  }

  /**
   * Constructs a new NameListFormatForm object.
   *
   * @param \Drupal\name\NameFormatParser $parser
   *   The name format parser.
   */
  public function __construct(NameFormatParser $parser) {
    $this->parser = $parser;
  }

  /**
   * {@inheritdoc}
   */
  public function exists($entity_id, array $element,  FormStateInterface $form_state) {
    return NameFormat::load($entity_id);
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $element = parent::form($form, $form_state);

    $element['label'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
      '#default_value' => $this->entity->label(),
      '#maxlength' => 255,
      '#required' => TRUE,
    );

    $element['id'] = array(
      '#type' => 'machine_name',
      '#title' => $this->t('Machine-readable name'),
      '#description' => $this->t('A unique machine-readable name. Can only contain lowercase letters, numbers, and underscores.'),
      '#disabled' => !$this->entity->isNew(),
      '#default_value' => $this->entity->id(),
      '#machine_name' => array(
        'exists' => array($this, 'exists'),
      ),
    );

    $element['pattern'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Format'),
      '#default_value' => $this->entity->get('pattern'),
      '#maxlength' => 255,
      '#required' => TRUE,
    );

    $element['help'] = $this->parser->renderableTokenHelp();

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $actions = parent::actions($form, $form_state);
    $actions['submit']['#value'] = $this->t('Save format');
    return $actions;
  }

  public function delete(array $form, FormStateInterface $form_state) {
    $form_state['redirect_route'] = array(
      'route_name' => 'name_format_delete_confirm',
      'route_parameters' => array(
        'name_format' => $this->entity->id(),
      ),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $form_state->setRedirect('name.name_format_list');
    if ($this->entity->isNew()) {
      drupal_set_message($this->t('Name format %label added.', ['%label' => $this->entity->label()]));
    }
    else {
      drupal_set_message($this->t('Name format %label has been updated.', ['%label' => $this->entity->label()]));
    }
    $this->entity->save();
  }

}
