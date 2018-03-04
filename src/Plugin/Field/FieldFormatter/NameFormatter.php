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
 *   label = @Translation("Default"),
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
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, EntityManagerInterface $entity_manager) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);

    $this->entityManager = $entity_manager;
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
      $container->get('entity.manager')
    );
  }

  public static function defaultSettings() {
    $settings = parent::defaultSettings();

    $settings += array(
      "format" => "default",
      "markup" => FALSE,
      "output" => "default",
      "multiple" => "default",
      "multiple_delimiter" => ", ",
      "multiple_and" => "text",
      "multiple_delimiter_precedes_last" => "never",
      "multiple_el_al_min" => "3",
      "multiple_el_al_first" => "1"
    );

    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $elements = parent::settingsForm($form, $form_state);
    $field_name = $this->fieldDefinition->getName();

    $elements['format'] = array(
      '#type' => 'select',
      '#title' => $this->t('Name format'),
      '#default_value' => $this->getSetting('format'),
      '#options' => name_get_custom_format_options(),
      '#required' => TRUE,
    );

    $elements['markup'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Markup'),
      '#default_value' => $this->getSetting('markup'),
      '#description' => $this->t('This option wraps the individual components of the name in SPAN elements with corresponding classes to the component.'),
    );

    $elements['output'] = array(
      '#type' => 'radios',
      '#title' => $this->t('Output'),
      '#default_value' => $this->getSetting('output'),
      '#options' => _name_formatter_output_options(),
      '#description' => $this->t('This option provides additional options for rendering the field. <strong>Normally, using the "Raw value" option would be a security risk.</strong>'),
      '#required' => TRUE,
    );

    $elements['multiple'] = array(
      '#type' => 'radios',
      '#title' => $this->t('Multiple format options'),
      '#default_value' => $this->getSetting('multiple'),
      '#options' => _name_formatter_multiple_options(),
      '#required' => TRUE,
    );

    $base = array(
      '#states' => array(
        'visible' => array(
          ':input[name="fields[' . $field_name . '][settings_edit_form][settings][multiple]"]' => array('value' => 'inline_list'),
        ),
      ),
      '#prefix' => '<div style="padding: 0 2em;">',
      '#suffix' => '</div>',
    );
    // We can not nest this field, so use a prefix / suffix with padding to help
    // to provide context.
    $elements['multiple_delimiter'] = $base + array(
      '#type' => 'textfield',
      '#title' => $this->t('Delimiter'),
      '#default_value' => $this->getSetting('multiple_delimiter'),
      '#description' => $this->t('This specifies the delimiter between the second to last and the last name.'),
    );
    $elements['multiple_and'] = $base + array(
      '#type' => 'radios',
      '#title' => $this->t('Last delimiter type'),
      '#options' => array(
        'text' => $this->t('Textual (and)'),
        'symbol' => $this->t('Ampersand (&amp;)'),
      ),
      '#default_value' => $this->getSetting('multiple_and'),
      '#description' => $this->t('This specifies the delimiter between the second to last and the last name.'),
    );
    $elements['multiple_delimiter_precedes_last'] = $base + array(
      '#type' => 'radios',
      '#title' => $this->t('Standard delimiter precedes last delimiter'),
      '#options' => array(
        'never' => $this->t('Never (i.e. "J. Doe and T. Williams")'),
        'always' => $this->t('Always (i.e. "J. Doe<strong>,</strong> and T. Williams")'),
        'contextual' => $this->t('Contextual (i.e. "J. Doe and T. Williams" <em>or</em> "J. Doe, S. Smith<strong>,</strong> and T. Williams")'),
      ),
      '#default_value' => $this->getSetting('multiple_delimiter_precedes_last'),
      '#description' => $this->t('This specifies the delimiter between the second to last and the last name. Contextual means that the delimiter is only included for lists with three or more names.'),
    );
    $options = range(1, 20);
    $options = array_combine($options, $options);
    $elements['multiple_el_al_min'] = $base + array(
      '#type' => 'select',
      '#title' => $this->t('Reduce list and append <em>el al</em>'),
      '#options' => array(0 => $this->t('Never reduce')) + $options,
      '#default_value' => $this->getSetting('multiple_el_al_min'),
      '#description' => $this->t('This specifies a limit on the number of names to display. After this limit, names are removed and the abbrivation <em>et al</em> is appended. This Latin abbrivation of <em>et alii</em> means "and others".'),
    );
    $elements['multiple_el_al_first'] = $base + array(
      '#type' => 'select',
      '#title' => $this->t('Number of names to display when using <em>el al</em>'),
      '#options' => $options,
      '#default_value' => $this->getSetting('multiple_el_al_first'),
    );

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $settings = $this->getSettings();
    $summary = array();

    $field_name = $this->fieldDefinition->getName();

    $machine_name = isset($settings['format']) ? $settings['format'] : 'default';
    $name_format = $this->entityManager->getStorage('name_format')->load($machine_name);
    if ($name_format) {
      $summary[] = $this->t('Format: %format (@machine_name)', array(
        '%format' => $name_format->label(),
        '@machine_name' => $name_format->id()
      ));
    }
    else {
      $summary[] = $this->t('Format: <strong>Missing format.</strong><br/>This field will be displayed using the Default format.');
      $machine_name = 'default';
    }

    // Provide an example of the selected format.
    module_load_include('admin.inc', 'name');
    $used_components = $this->getFieldSetting('components');
    $excluded_components = array_diff_key($used_components, _name_translations());
    $examples = name_example_names($excluded_components, $field_name);
    if ($examples && $example = array_shift($examples)) {
      $format = name_get_format_by_machine_name($machine_name);
      $formatted = Html::escape(NameFormatParser::parse($example, $format));
      if (empty($formatted)) {
        $summary[] = $this->t('Example: <em>&lt;&lt;empty&gt;&gt;</em>');
      }
      else {
        $summary[] = $this->t('Example: @example', [
          '@example' => $formatted
        ]);
      }
    }

    $summary[] = $this->t('Markup: @yesno', array(
      '@yesno' => empty($settings['markup']) ? $this->t('no') : $this->t('yes')
    ));
    $output_options = _name_formatter_output_options();
    $output = empty($settings['output']) ? 'default' : $settings['output'];
    $summary[] = $this->t('Output: @format', array(
      '@format' => $output_options[$output],
    ));

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = array();
    $entity = $items->getEntity();

    $settings = $this->settings;
    $type = empty($settings['output']) ? 'default' : $settings['output'];
    $format = isset($settings['format']) ? $settings['format'] : 'default';

    $format = name_get_format_by_machine_name($format);
    if (empty($format)) {
      $format = name_get_format_by_machine_name('default');
    }

    foreach ($items as $delta => $item) {
      // We still have raw user input here unless the markup flag has been used.
      $value = NameFormatParser::parse($item->toArray(), $format, [
        'object' => $entity,
        'type' => $entity->getEntityTypeId(),
        'markup' => $this->useMarkup(),
      ]);
      if ($this->useMarkup()) {
        $elements[$delta] = array('#markup' => $value);
      }
      else {
        $elements[$delta] = array(
          '#markup' => _name_value_sanitize($value, NULL, $type)
        );
      }
    }

    if (isset($settings['multiple']) && $settings['multiple'] == 'inline_list') {
      $items = array();
      foreach (Element::children($elements) as $delta) {
        if (!empty($elements[$delta]['#markup'])) {
          $items[] = $elements[$delta]['#markup'];
          unset($elements[$delta]);
        }
      }

      if (!empty($items)) {
        $elements[0] = [
          '#theme' => 'name_item_list',
          '#items' => $items,
          '#settings' => $settings
        ];
      }
    }

    return $elements;
  }

  /**
   * Determines with markup should be added to the results.
   *
   * @return boolean
   */
  protected function useMarkup() {
    return $this->settings['markup'];
  }

}
