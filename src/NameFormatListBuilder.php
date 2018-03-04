<?php

namespace Drupal\name;

use Drupal\Component\Utility\Html;
use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;

/**
 *
 */
class NameFormatListBuilder extends ConfigEntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $row = [];
    $row['label'] = $this->t('Label');
    $row['id'] = $this->t('Machine name');
    $row['format'] = $this->t('Format');
    $row['examples'] = $this->t('Examples');
    $row['operations'] = $this->t('Operations');
    return $row;
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $row = [];
    $row['label'] = $entity->label();
    $row['id'] = $entity->id();
    $row['format'] = $entity->get('pattern');
    $row['examples'] = [
      'data' => [
        '#markup' => implode('<br/>', $this->examples($entity)),
      ],
    ];
    $operations = $this->buildOperations($entity);
    $row['operations']['data'] = $operations;
    return $row;
  }

  /**
   * Provides some example based on names with various components set.
   */
  public function examples(EntityInterface $entity) {
    $examples = [];
    foreach ($this->nameExamples() as $index => $example_name) {
      $formatted = Html::escape(NameFormatParser::parse($example_name, $entity->get('pattern')));
      if (empty($formatted)) {
        $formatted = '<em>&lt;&lt;empty&gt;&gt;</em>';
      }
      $examples[] = $formatted . " <sup>{$index}</sup>";
    }
    return $examples;
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    return [
      'list' => parent::render(),
      'help' => $this->nameFormatHelp(),
    ];
  }

  /**
   * Help box.
   *
   * @return array
   */
  public function nameFormatHelp() {
    module_load_include('inc', 'name', 'name.admin');
    return _name_get_name_format_help_form();
  }

  /**
   * Example names.
   *
   * @return array
   */
  public function nameExamples() {
    module_load_include('inc', 'name', 'name.admin');
    return name_example_names();
  }

}
