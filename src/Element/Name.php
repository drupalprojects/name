<?php

namespace Drupal\name\Element;

use Drupal\Core\Render\Element\RenderElement;

/**
 * Provides a name render element.
 *
 * @RenderElement("name")
 */
class Name extends RenderElement {

  /**
   * Returns the element properties for this element.
   *
   * @return array
   *   An array of element properties. See
   *   \Drupal\Core\Render\ElementInfoManagerInterface::getInfo() for
   *   documentation of the standard properties of all elements, and the
   *   return value format.
   */
  public function getInfo() {
    $parts = _name_translations();
    $field_settings = \Drupal::service('plugin.manager.field.field_type')->getDefaultFieldSettings('name');

    return [
      '#input' => TRUE,
      '#process' => ['name_element_expand'],
      '#pre_render' => ['name_element_pre_render'],
      '#element_validate' => ['name_element_validate'],
      '#theme_wrappers' => ['form_element'],
      '#show_component_required_marker' => 0,
      '#default_value' => [
        'title' => '',
        'given' => '',
        'middle' => '',
        'family' => '',
        'generational' => '',
        'credentials' => '',
      ],
      '#minimum_components' => $field_settings['minimum_components'],
      '#allow_family_or_given' => $field_settings['allow_family_or_given'],
      '#components' => [
        'title' => [
          'type' => $field_settings['field_type']['title'],
          'title' => $parts['title'],
          'title_display' => 'description',
          'inline_css' => $field_settings['inline_css']['title'],
          'size' => $field_settings['size']['title'],
          'maxlength' => $field_settings['max_length']['title'],
          'options' => $field_settings['title_options'],
          'autocomplete' => FALSE,
        ],
        'given' => [
          'type' => 'textfield',
          'title' => $parts['given'],
          'title_display' => 'description',
          'inline_css' => $field_settings['inline_css']['given'],
          'size' => $field_settings['size']['given'],
          'maxlength' => $field_settings['max_length']['given'],
          'autocomplete' => FALSE,
        ],
        'middle' => [
          'type' => 'textfield',
          'title' => $parts['middle'],
          'title_display' => 'description',
          'inline_css' => $field_settings['inline_css']['middle'],
          'size' => $field_settings['size']['middle'],
          'maxlength' => $field_settings['max_length']['middle'],
          'autocomplete' => FALSE,
        ],
        'family' => [
          'type' => 'textfield',
          'title' => $parts['family'],
          'title_display' => 'description',
          'inline_css' => $field_settings['inline_css']['family'],
          'size' => $field_settings['size']['family'],
          'maxlength' => $field_settings['max_length']['family'],
          'autocomplete' => FALSE,
        ],
        'generational' => [
          'type' => $field_settings['field_type']['generational'],
          'title' => $parts['generational'],
          'title_display' => 'description',
          'inline_css' => $field_settings['inline_css']['generational'],
          'size' => $field_settings['size']['generational'],
          'maxlength' => $field_settings['max_length']['generational'],
          'options' => $field_settings['generational_options'],
          'autocomplete' => FALSE,
        ],
        'credentials' => [
          'type' => 'textfield',
          'title' => $parts['credentials'],
          'title_display' => 'description',
          'inline_css' => $field_settings['inline_css']['credentials'],
          'size' => $field_settings['size']['credentials'],
          'maxlength' => $field_settings['max_length']['credentials'],
          'autocomplete' => FALSE,
        ],
      ],
    ];
  }

}
