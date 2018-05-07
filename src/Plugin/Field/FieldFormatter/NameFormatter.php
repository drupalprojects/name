<?php

namespace Drupal\name\Plugin\Field\FieldFormatter;

use Drupal\Component\Utility\Html;
use Drupal\Core\Url;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Entity\Exception\UndefinedLinkTemplateException;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\name\NameFormatParser;
use Drupal\name\NameGeneratorInterface;
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
   * The field renderer for any additional components.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

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
   * The name generator.
   *
   * @var \Drupal\name\NameGeneratorInterface
   */
  protected $generator;

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
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The rendering service.
   * @param \Drupal\name\NameFormatter $formatter
   *   The name formatter.
   * @param \Drupal\name\NameFormatParser $parser
   *   The name format parser.
   * @param \Drupal\name\NameGeneratorInterface $generator
   *   The name format parser.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, EntityManagerInterface $entity_manager, RendererInterface $renderer, \Drupal\name\NameFormatter $formatter, NameFormatParser $parser, NameGeneratorInterface $generator) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);

    $this->entityManager = $entity_manager;
    $this->renderer = $renderer;
    $this->formatter = $formatter;
    $this->parser = $parser;
    $this->generator = $generator;
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
      $container->get('renderer'),
      $container->get('name.formatter'),
      $container->get('name.format_parser'),
      $container->get('name.generator')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    $settings = parent::defaultSettings();

    $settings += [
      "format" => "default",
      "markup" => "none",
      "list_format" => "",
      "link_target" => "",
      "preferred_field_reference" => "",
      "preferred_field_reference_separator" => ", ",
      "alternative_field_reference" => "",
      "alternative_field_reference_separator" => ", ",
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
      '#type' => 'select',
      '#title' => $this->t('Markup'),
      '#default_value' => $this->getSetting('markup'),
      '#options' => $this->parser->getMarkupOptions(),
      '#description' => $this->t('This option wraps the individual components of the name in SPAN elements with corresponding classes to the component.'),
      '#required' => TRUE,
    ];

    $elements['link_target'] = [
      '#type' => 'select',
      '#title' => $this->t('Link Target'),
      '#default_value' => $this->getSetting('link_target'),
      '#empty_option' => $this->t('-- no link --'),
      '#options' => $this->getLinkableTargets(),
    ];

    $elements['preferred_field_reference'] = [
      '#type' => 'select',
      '#title' => $this->t('Preferred component source'),
      '#default_value' => $this->getSetting('preferred_field_reference'),
      '#empty_option' => $this->t('-- none --'),
      '#options' => $this->getAdditionalSources(),
      '#description' => $this->t('A data source to use as the preferred given name within the name formats. A common use-case would be for a users nickname.<br>i.e. "q" and "v", plus the conditional "p", "d" and "D" name format options.'),
    ];

    $elements['preferred_field_reference_separator'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Preferred component source multivalue separator'),
      '#default_value' => $this->getSetting('preferred_field_reference_separator'),
      '#description' => $this->t('Used to separate multi-value items in an inline list.'),
      '#states' => [
        'invisible' => [
          ':input[name="fields[' . $field_name . '][settings_edit_form][settings][preferred_field_reference]"]' => ['value' => ''],
        ],
      ],
    ];

    $elements['alternative_field_reference'] = [
      '#type' => 'select',
      '#title' => $this->t('Alternative component source'),
      '#default_value' => $this->getSetting('alternative_field_reference'),
      '#empty_option' => $this->t('-- none --'),
      '#options' => $this->getAdditionalSources(),
      '#description' => $this->t('A data source to use as the alternative component within the name formats. Possible use-cases include; providing a custom fully formatted name alternative to use in citations; a separate field for a users creditatons / post-nominal letters.<br>i.e. "a" and "A" name format options.'),
    ];

    $elements['alternative_field_reference_separator'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Alternative component source multivalue separator'),
      '#default_value' => $this->getSetting('alternative_field_reference_separator'),
      '#description' => $this->t('Used to separate multi-value items in an inline list.'),
      '#states' => [
        'invisible' => [
          ':input[name="fields[' . $field_name . '][settings_edit_form][settings][alternative_field_reference]"]' => ['value' => ''],
        ],
      ],
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
      $summary[] = $this->t('Format: @format (@machine_name)', [
        '@format' => $name_format->label(),
        '@machine_name' => $name_format->id(),
      ]);
    }
    else {
      $summary[] = $this->t('Format: <strong>Missing format.</strong><br/>This field will be displayed using the Default format.');
      $machine_name = 'default';
    }

    $markup_options = $this->parser->getMarkupOptions();
    $summary[] = $this->t('Markup: @type', [
      '@type' => $markup_options[$this->getSetting('markup')],
    ]);

    if (!empty($settings['link_target'])) {
      $targets = $this->getLinkableTargets();
      $summary[] = $this->t('Link: @target', [
        '@target' => empty($targets[$settings['link_target']]) ? t('-- invalid --') : $targets[$settings['link_target']],
      ]);
    }
    if (!empty($settings['preferred_field_reference'])) {
      $targets = $this->getAdditionalSources();
      $summary[] = $this->t('Preferred: @label', [
        '@label' => empty($targets[$settings['preferred_field_reference']]) ? t('-- invalid --') : $targets[$settings['preferred_field_reference']],
      ]);
    }
    if (!empty($settings['alternative_field_reference'])) {
      $targets = $this->getAdditionalSources();
      $summary[] = $this->t('Alternative: @label', [
        '@label' => empty($targets[$settings['alternative_field_reference']]) ? t('-- invalid --') : $targets[$settings['alternative_field_reference']],
      ]);
    }

    // Provide an example of the selected format.
    if ($name_format) {
      $names = $this->generator->loadSampleValues(1, $this->fieldDefinition);
      if ($name = reset($names)) {
        $formatted = $this->parser->parse($name, $name_format->get('pattern'));
        if (empty($formatted)) {
          $summary[] = $this->t('Example: <em>&lt;&lt;empty&gt;&gt;</em>');
        }
        else {
          $summary[] = $this->t('Example: @example', [
            '@example' => $formatted,
          ]);
        }
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

    $extra = $this->parseAdditionalComponents($items);
    $extra['url'] = empty($settings['link_target']) ? NULL : $this->getLinkableTargetUrl($items);

    $item_array = [];
    foreach ($items as $item) {
      $components = $item->toArray() + $extra;
      $item_array[] = $components;
    }

    $this->formatter->setSetting('markup', $this->getSetting('markup'));

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

  /**
   * Find any linkable targets.
   *
   * @return array
   *   An array of possible targets.
   */
  protected function getLinkableTargets() {
    $targets = ['_self' => $this->t('Entity URL')];
    $bundle = $this->fieldDefinition->getTargetBundle();
    $entity_type_id = $this->fieldDefinition->getTargetEntityTypeId();
    $fields = $this->entityManager->getFieldDefinitions($entity_type_id, $bundle);
    foreach ($fields as $field) {
      if (!$field->getFieldStorageDefinition()->isBaseField()) {
        switch ($field->getType()) {
          case 'entity_reference':
          case 'link':
            $targets[$field->getName()] = $field->getLabel();
            break;
        }
      }
    }
    return $targets;
  }

  /**
   * Gets the URL object.
   *
   * @param FieldItemListInterface $items
   *   The name formatters FieldItemList.
   *
   * @return \Drupal\Core\Url
   *   Returns a Url object.
   */
  protected function getLinkableTargetUrl(FieldItemListInterface $items) {
    try {
      $parent = $items->getEntity();
      if ($this->settings['link_target'] == '_self') {
        if (!$parent->isNew() && $parent->access('view')) {
          return $parent->toUrl();
        }
      }
      elseif ($parent->hasField($this->settings['link_target'])) {
        $target_items = $parent->get($this->settings['link_target']);
        if (!$target_items->isEmpty()) {
          $field = $target_items->getFieldDefinition();
          switch ($field->getType()) {
            case 'entity_reference':
              foreach ($target_items as $item) {
                if (!empty($item->entity) && !$item->entity->isNew() && $item->entity->access('view')) {
                  return $item->entity->toUrl();
                }
              }
              break;

            case 'link':
              foreach ($target_items as $item) {
                if ($url = $item->getUrl()) {
                  return $url;
                }
              }
              break;

          }
        }
      }
    }
    catch (UndefinedLinkTemplateException $e) {}

    return Url::fromRoute('<none>');
  }

  protected function getAdditionalSources() {
    $entity_type_id = $this->fieldDefinition->getTargetEntityTypeId();
    $entity_type = $this->entityManager
        ->getStorage($entity_type_id)
        ->getEntityType();
    $bundle = $this->fieldDefinition->getTargetBundle();
    $entity_type_label = $entity_type->getBundleLabel($bundle);
    if (!$entity_type_label) {
      $entity_type_label = $entity_type->getLabel();
    }
    $sources = [
      '_self' => $this->t('@label label', ['@label' => $entity_type_label]),
    ];
    if ($entity_type_id == 'user') {
      $sources['_self_property_name'] = $this->t('@label login name', ['@label' => $entity_type_label]);
    }
    $fields = $this->entityManager->getFieldDefinitions($entity_type_id, $bundle);
    foreach ($fields as $field_name => $field) {
      if (!$field->getFieldStorageDefinition()->isBaseField() && $field_name != $this->fieldDefinition->getName()) {
        $sources[$field->getName()] = $field->getLabel();
      }
    }
    return $sources;
  }

  /**
   * Gets any additional linked components.
   *
   * @param FieldItemListInterface $items
   *   The name formatters FieldItemList.
   *
   * @return array
   *   Returns a Url object.
   */
  protected function parseAdditionalComponents(FieldItemListInterface $items) {
    $extra = [];
    $map = [
      'preferred' => 'preferred_field_reference',
      'alternative' => 'alternative_field_reference',
    ];
    $parent = $items->getEntity();
    foreach ($map as $component => $key) {
      if (!empty($this->settings[$key])) {
        if ($this->settings[$key] == '_self') {
          if ($label = $parent->label()) {
            $extra[$component] = $label;
          }
        }
        elseif (strpos($this->settings[$key], '_self_property') === 0) {
          $property = str_replace('_self_property_', '', $this->settings[$key]);
          try {
            if ($item = $parent->get($property)) {
              if (!empty($item->value)) {
                $extra[$component] = $item->value;
              }
            }
          }
          catch (\InvalidArgumentException $e) {}
        }
        elseif ($parent->hasField($this->settings[$key])) {
          $target_items = $parent->get($this->settings[$key]);
          if (!$target_items->isEmpty() && $target_items->access('view')) {
            $field = $target_items->getFieldDefinition();
            $values = [];
            switch ($field->getType()) {
              case 'entity_reference':
                foreach ($target_items as $item) {
                  /* @var \Drupal\Core\Entity\EntityInterface $entity */
                  $entity = $item->entity;
                  if (!empty($item->entity) && $item->entity->access('view') && ($label = $item->entity->label())) {
                    $values[] = $label;
                  }
                }
                break;

              default:
                $viewBuilder = $this->entityManager->getViewBuilder($parent->getEntityTypeId());
                foreach ($target_items as $item) {
                  $renderable = $viewBuilder->viewFieldItem($item, ['label' => 'hidden']);
                  /* @var $value \Drupal\Component\Render\MarkupInterface */
                  if ($value = (string) $this->renderer->render($renderable)) {
                    // Remove any markup, but decode entities as the parser
                    // requires raw unescaped strings.
                    if ($value = trim(strip_tags($value))) {
                      $values[] = HTML::decodeEntities($value);
                    }
                  }
                }
                break;

            }
            if ($values) {
              $extra[$component] = implode(', ', $values);
            }
          }
        }
      }
    }

    return $extra;
  }

}
