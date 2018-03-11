<?php

namespace Drupal\name\Plugin\Field\FieldFormatter;

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\Element;
use Drupal\name\NameFormatParser;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'name' formatter.
 *
 * The 'Default' formatter is different for integer fields on the one hand, and
 * for decimal and float fields on the other hand, in order to be able to use
 * different settings.
 *
 * @FieldFormatter(
 *   id = "name_default",
 *   module = "name",
 *   label = @Translation("Name formatter"),
 *   field_types = {
 *     "name",
 *   }
 * )
 */
class NameFormatter extends FormatterBase implements ContainerFactoryPluginInterface {

  /**
   * The entity manager to load name_format entities.
   *
   * @var \Drupal\Core\Entity\EntityManagerInterface
   */
  protected $entityManager;

  /**
   * The name formatter.
   *
   * @var \Drupal\name\NameFormatter
   */
  protected $formatter;

  /**
   * The name format parser.
   *
   * Directly called to format the examples without the fallback.
   *
   * @var \Drupal\name\NameFormatParser
   */
  protected $parser;

  /**
   * Constructs a NameFormatter instance.
   *
   * @param string $plugin_id
   *   The plugin_id for the formatter.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the formatter is associated.
   * @param array $settings
   *   The formatter settings.
   * @param string $label
   *   The formatter label display setting.
   * @param string $view_mode
   *   The view mode.
   * @param array $third_party_settings
   *   Any third party settings settings.
   * @param \Drupal\Core\Entity\EntityManagerInterface $entity_manager
   *   The entity manager.
   * @param \Drupal\name\NameFormatter $formatter
   *   The name formatter.
   * @param \Drupal\name\NameFormatParser $parser
   *   The name format parser.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, EntityManagerInterface $entity_manager, \Drupal\name\NameFormatter $formatter, NameFormatParser $parser) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);

    $this->entityManager = $entity_manager;
    $this->formatter = $formatter;
    $this->parser = $parser;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('entity.manager'),
      $container->get('name.formatter'),
      $container->get('name.format_parser')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    $settings = parent::defaultSettings();

    $settings += [
      "format" => "default",
      "markup" => FALSE,
      "output" => "default",
      "list_format" => "",
    ];

    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $elements = parent::settingsForm($form, $form_state);
    $field_name = $this->fieldDefinition->getName();

    $elements['format'] = [
      '#type' => 'select',
      '#title' => $this->t('Name format'),
      '#default_value' => $this->getSetting('format'),
      '#options' => name_get_custom_format_options(),
      '#required' => TRUE,
    ];

    $elements['list_format'] = [
      '#type' => 'select',
      '#title' => $this->t('List format'),
      '#default_value' => $this->getSetting('list_format'),
      '#empty_option' => $this->t('-- individually --'),
      '#options' => name_get_custom_list_format_options(),
    ];

    $elements['markup'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Markup'),
      '#default_value' => $this->getSetting('markup'),
      '#description' => $this->t('This option wraps the individual components of the name in SPAN elements with corresponding classes to the component.'),
    ];

    $elements['output'] = [
      '#type' => 'radios',
      '#title' => $this->t('Output'),
      '#default_value' => $this->getSetting('output'),
      '#options' => _name_formatter_output_options(),
      '#description' => $this->t('This option provides additional options for rendering the field. <strong>Normally, using the "Raw value" option would be a security risk.</strong>'),
      '#required' => TRUE,
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $settings = $this->getSettings();
    $summary = [];

    $field_name = $this->fieldDefinition->getName();

    $machine_name = isset($settings['format']) ? $settings['format'] : 'default';
    $name_format = $this->entityManager->getStorage('name_format')->load($machine_name);
    if ($name_format) {
      $summary[] = $this->t('Format: %format (@machine_name)', [
        '%format' => $name_format->label(),
        '@machine_name' => $name_format->id(),
      ]);
    }
    else {
      $summary[] = $this->t('Format: <strong>Missing format.</strong><br/>This field will be displayed using the Default format.');
      $machine_name = 'default';
    }

    $summary[] = $this->t('Markup: @yesno', [
      '@yesno' => $this->useMarkup() ? $this->t('yes') : $this->t('no'),
    ]);

    $output_options = _name_formatter_output_options();
    $output = empty($settings['output']) ? 'default' : $settings['output'];
    $summary[] = $this->t('Output: @format', [
      '@format' => $output_options[$output],
    ]);

    // Provide an example of the selected format.
    module_load_include('admin.inc', 'name');
    $used_components = $this->getFieldSetting('components');
    $excluded_components = array_diff_key($used_components, _name_translations());
    $examples = name_example_names($excluded_components, $field_name);
    if ($examples && $example = array_shift($examples)) {
      $format = name_get_format_by_machine_name($machine_name);
      $formatted = Html::escape($this->parser->parse($example, $format));
      if (empty($formatted)) {
        $summary[] = $this->t('Example: <em>&lt;&lt;empty&gt;&gt;</em>');
      }
      else {
        $summary[] = $this->t('Example: @example', [
          '@example' => $formatted,
        ]);
      }
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    if (!$items->count()) {
      return $elements;
    }

    $settings = $this->settings;

    $format = isset($settings['format']) ? $settings['format'] : 'default';
    $is_multiple = $this->fieldDefinition->getFieldStorageDefinition()->isMultiple() && $items->count() > 1;
    $list_format = $is_multiple && !empty($settings['list_format']) ? $settings['list_format'] : '';

    $item_array = [];
    foreach ($items as $item) {
      $components = $item->toArray();
      $item_array[] = $components;
    }
    $this->formatter->setSetting('markup', $this->useMarkup());

    if ($list_format) {
      $elements[0]['#markup'] = $this->formatter->formatList($item_array, $format, $list_format, $langcode);
    }
    else {
      foreach ($item_array as $delta => $item) {
        $elements[$delta]['#markup'] = $this->formatter->format($item, $format, $langcode);
      }
    }

    return $elements;
  }

  /**
   * Determines with markup should be added to the results.
   *
   * @return bool
   *   Returns TRUE if markup should be applied.
   */
  protected function useMarkup() {
    return $this->settings['markup'];
  }

}
