<?php

namespace Drupal\name\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\name\NameOptionsProvider;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'name' widget.
 *
 * @FieldWidget(
 *   id = "name_default",
 *   module = "name",
 *   label = @Translation("Name field widget"),
 *   field_types = {
 *     "name"
 *   }
 * )
 */
class NameWidget extends WidgetBase implements ContainerFactoryPluginInterface {

  /**
   * Name options provider service.
   *
   * @var \Drupal\name\NameOptionsProvider
   */
  protected $optionsProvider;

  /**
   * Constructs a NameWidget object.
   *
   * @param string $plugin_id
   *   The plugin_id for the widget.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the widget is associated.
   * @param array $settings
   *   The widget settings.
   * @param array $third_party_settings
   *   Any third party settings.
   * @param \Drupal\name\NameOptionsProvider $options_provider
   *   Name options provider service.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, array $third_party_settings, NameOptionsProvider $options_provider) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
    $this->optionsProvider = $options_provider;
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
      $configuration['third_party_settings'],
      $container->get('name.options_provider')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    module_load_include('inc', 'name', 'includes/name.content');
    $field_settings = $this->getFieldSettings();
    $element += [
      '#type' => 'name',
      '#title' => $this->fieldDefinition->getLabel(),
      '#components' => [],
      '#minimum_components' => array_filter($field_settings['minimum_components']),
      '#allow_family_or_given' => !empty($field_settings['allow_family_or_given']),
      '#default_value' => isset($items[$delta]) ? $items[$delta]->getValue() : NULL,
      '#field' => $this,
      '#credentials_inline' => empty($field_settings['credentials_inline']) ? 0 : 1,
      '#component_css' => empty($field_settings['component_css']) ? '' : $field_settings['component_css'],
      '#component_layout' => empty($field_settings['component_layout']) ? 'default' : $field_settings['component_layout'],
      '#show_component_required_marker' => !empty($field_settings['show_component_required_marker']),
    ];

    $components = array_filter($field_settings['components']);
    foreach (_name_translations() as $key => $title) {
      if (isset($components[$key])) {
        $element['#components'][$key]['type'] = 'textfield';

        $size = !empty($field_settings['size'][$key]) ? $field_settings['size'][$key] : 60;
        $title_display = isset($field_settings['title_display'][$key]) ? $field_settings['title_display'][$key] : 'description';

        $element['#components'][$key]['title'] = Html::escape($field_settings['labels'][$key]);
        $element['#components'][$key]['title_display'] = $title_display;

        $element['#components'][$key]['size'] = $size;
        $element['#components'][$key]['maxlength'] = !empty($field_settings['max_length'][$key]) ? $field_settings['max_length'][$key] : 255;

        // Provides backwards compatibility with Drupal 6 modules.
        $field_type = ($key == 'title' || $key == 'generational') ? 'select' : 'text';
        $field_type = isset($field_settings['field_type'][$key])
            ? $field_settings['field_type'][$key]
            : (isset($field_settings[$key . '_field']) ? $field_settings[$key . '_field'] : $field_type);

        if ($field_type == 'select') {
          $element['#components'][$key]['type'] = 'select';
          $element['#components'][$key]['size'] = 1;
          $element['#components'][$key]['options'] = $this->optionsProvider->getOptions($this->fieldDefinition, $key);
        }
        elseif ($field_type == 'autocomplete') {
          if ($sources = $field_settings['autocomplete_source'][$key]) {
            $sources = array_filter($sources);
            if (!empty($sources)) {
              $element['#components'][$key]['autocomplete'] = [
                '#autocomplete_route_name' => 'name.autocomplete',
                '#autocomplete_route_parameters' => [
                  'field_name' => $this->fieldDefinition->getName(),
                  'entity_type' => $this->fieldDefinition->getTargetEntityTypeId(),
                  'bundle' => $this->fieldDefinition->getTargetBundle(),
                  'component' => $key,
                ],
              ];
            }
          }
        }

        if (isset($field_settings['inline_css'][$key]) && Unicode::strlen($field_settings['inline_css'][$key])) {
          $element['#components'][$key]['attributes'] = [
            'style' => $field_settings['inline_css'][$key],
          ];
        }
      }
      else {
        $element['#components'][$key]['exclude'] = TRUE;
      }
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    $values = parent::massageFormValues($values, $form, $form_state);
    $new_values = [];
    foreach ($values as $item) {
      $value = implode('', array_intersect_key($item, _name_translations()));
      if (strlen($value)) {
        $new_values[] = $item;
      }
    }
    return $new_values;
  }

}
