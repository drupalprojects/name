<?php

namespace Drupal\name;

use Drupal\Component\Utility\Html;
use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Name format list builder for the admin page.
 */
class NameFormatListBuilder extends ConfigEntityListBuilder {

  /**
   * The name format parser.
   *
   * @var \Drupal\name\NameFormatParser
   */
  protected $parser;

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity.manager')->getStorage($entity_type->id()),
      $container->get('name.format_parser')
    );
  }

  /**
   * Constructs a new EntityListBuilder object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The entity storage class.
   * @param \Drupal\name\NameFormatParser $parser
   *   The name format parser.
   */
  public function __construct(EntityTypeInterface $entity_type, EntityStorageInterface $storage, NameFormatParser $parser) {
    parent::__construct($entity_type, $storage);
    $this->parser = $parser;
  }

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
   *
   * @return array
   *   An array of example names with formatting applied.
   */
  public function examples(EntityInterface $entity) {
    $examples = [];
    foreach ($this->nameExamples() as $index => $example_name) {
      $formatted = Html::escape($this->parser->parse($example_name, $entity->get('pattern')));
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
      'help' => $this->parser->renderableTokenHelp(),
    ];
  }

  /**
   * Example names.
   *
   * @return array
   *   Array of fields sourced from the config files.
   */
  public function nameExamples() {
    module_load_include('inc', 'name', 'name.admin');
    return name_example_names();
  }

}
