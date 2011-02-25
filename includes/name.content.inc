<?php

/**
 * @file
 * Provides the CCK functionality for the name module.
 */
function name_additional_content_theme(&$theme) {
  // The default CCK formatter. The other theme callbacks
  // are defined dynamically from the database.
  $theme['name_formatter_default'] = array(
    'arguments' => array('element' => NULL),
  );

  // Additional CCK formatter callbacks.
  $base_info = array(
    'arguments' => array('element' => NULL),
    'function' => 'theme_name_formatter_default',
  );
  foreach (_name_formatter_output_types() as $type => $title) {
    if ($type != 'default') {
      $theme['name_formatter_' . $type] = $base_info;
    }
    foreach (name_get_custom_format_options() as $machine_name => $name_title) {
      $theme['name_formatter_' . $type . '_' . $machine_name] = $base_info;
    }
  }
}

/**
 * Implementation of hook_field_info().
 */
function name_field_info() {
  return array(
    'name' => array(
      'label' => t('Name'),
      'description' => t('This field stores a users title, given, middle, family name, generational suffix and credentials in the database.'),
      'field_settings' => array(
        // Components used. At least given or family name is required.
        'components' => array(
          'title',
          'given',
          'middle',
          'family',
          'generational',
          'credentials',
        ),
        // Minimal set of components before considered incomplete
        'minimum_components' => array(
          'given',
          'family',
        ),
        'labels' => _name_translations(),
        'max_length' => array(
          'title' => 31,
          'given' => 63,
          'middle' => 127,
          'family' => 63,
          'generational' => 15,
          'credentials' => 255,
        ),
        'title_options' => implode("\n", array(t('-- --'), t('Mr.'), t('Mrs.'), t('Miss'), t('Ms.'), t('Dr.'), t('Prof.'))),
        'generational_options' => implode("\n", array(t('-- --'), t('Jr.'), t('Sr.'), t('I'), t('II'), t('III'), t('IV'), t('V'), t('VI'), t('VII'), t('VIII'), t('IX'), t('X'))),
        'sort_options' => array(
          'title' => 'title',
          'generational' => 0,
        ),
      ),
      'instance_settings' => array(
        // Possible elements for either (free) text, autocomplete, select.
        'title_field' => 'select',
        'generational_field' => 'select',
        'inline_css' => array(
          'multiplier' => '0.9',
          'unit' => 'em',
        ),
        'size' => array(
          'title' => 6,
          'given' => 20,
          'middle' => 20,
          'family' => 20,
          'generational' => 5,
          'credentials' => 35,
        ),
        'inline_css_enabled' => array(
          'title' => 0,
          'given' => 0,
          'middle' => 0,
          'family' => 0,
          'generational' => 0,
          'credentials' => 0,
        ),
        'title_display' => array(
          'title' => 'description',
          'given' => 'description',
          'middle' => 'description',
          'family' => 'description',
          'generational' => 'description',
          'credentials' => 'description',
        ),
      ),
    ),
  );
}

/**
 * Implementation of hook_field_settings().
 */
function name_field_settings($op, $field) {
  _name_defaults($field, 'field_settings');
  switch ($op) {
    case 'form':
      $form = array(
        'field_settings' => array('#tree' => TRUE)
      );
      $components = _name_translations();
      $form['field_settings']['components'] = array(
        '#type' => 'checkboxes',
        '#title' => t('Components'),
        '#default_value' => $field['field_settings']['components'],
        '#required' => TRUE,
        '#description' => t('Only selected components will be activated on this field. All non-selected components / component settings will be ignored.'),
        '#options' => $components,
        '#element_validate' => array('_name_field_minimal_component_requirements'),
      );

      $form['field_settings']['minimum_components'] = array(
        '#type' => 'checkboxes',
        '#title' => t('Minimum components'),
        '#default_value' => $field['field_settings']['minimum_components'],
        '#required' => TRUE,
        '#element_validate' => array('_name_field_minimal_component_requirements'),
        '#description' => t('The minimal set of components required before considered the name field to be incomplete.'),
        '#options' => $components,
      );
      $form['field_settings']['labels'] = array();
      $form['field_settings']['max_length'] = array();
      foreach ($components as $key => $title) {
        $form['field_settings']['max_length'][$key] = array(
          '#type' => 'textfield',
          '#title' => t('Maximum length for !title', array('!title' => $title)),
          '#default_value' => $field['field_settings']['max_length'][$key],
          '#required' => FALSE,
          '#size' => 10,
          '#description' => t('The maximum length of the field in characters. This must be between 1 and 255.'),
          '#element_validate' => array('_name_validate_integer_positive'),
        );
        $form['field_settings']['labels'][$key] = array(
          '#type' => 'textfield',
          '#title' => t('Label for !title', array('!title' => $title)),
          '#default_value' => $field['field_settings']['labels'][$key],
          '#required' => TRUE,
        );
      }

      // TODO - Grouping & grouping sort
      // TODO - Allow reverse free tagging back into the vocabulary.
      $title_options = implode("\n", array_filter(explode("\n", $field['field_settings']['title_options'])));
      $form['field_settings']['title_options'] = array(
        '#type' => 'textarea',
        '#title' => t('Title options'),
        '#default_value' => $title_options,
        '#required' => TRUE,
        '#description' => t("Enter one title per line. Prefix a line using '--' to specify a blank value text. For example: '--Please select a title'.")
      );
      $generational_options = implode("\n", array_filter(explode("\n", $field['field_settings']['generational_options'])));
      $form['field_settings']['generational_options'] = array(
        '#type' => 'textarea',
        '#title' => t('Generational options'),
        '#default_value' => $generational_options,
        '#required' => TRUE,
        '#description' => t("Enter one generational suffix option per line. Prefix a line using '--' to specify a blank value text. For example: '----'."),
      );
      if (module_exists('taxonomy')) {
        // Generational suffixes may be also imported from one or more vocabularies using the tag '[vocabulary:xxx]', where xxx is the vocabulary id. Terms that exceed the maximum length of the generational suffix are not added to the options list.
        $form['field_settings']['title_options']['#description'] .= ' '. t("%label_plural may be also imported from one or more vocabularies using the tag '[vocabulary:xxx]', where xxx is the vocabulary id. Terms that exceed the maximum length of the %label are not added to the options list.",
            array('%label_plural' => t('Titles'), '%label' => t('Title')));
        $form['field_settings']['generational_options']['#description'] .= ' '. t("%label_plural may be also imported from one or more vocabularies using the tag '[vocabulary:xxx]', where xxx is the vocabulary id. Terms that exceed the maximum length of the %label are not added to the options list.",
            array('%label_plural' => t('Generational suffixes'), '%label' => t('Generational suffix')));
      }
      $sort_options = is_array($field['field_settings']['sort_options']) ? $field['field_settings']['sort_options'] : array('title' => 'title', 'generational' => '');
      $form['field_settings']['sort_options'] = array(
        '#type' => 'checkboxes',
        '#title' => t('Select field sort options'),
        '#default_value' => $sort_options,
        '#description' => t("This enables sorting on the options after the vocabulary terms are added and duplicate values are removed."),
        '#options' => _name_translations(array('title' => '', 'generational' => '')),
      );
      return $form;

    case 'validate':
      // Validates options against the title / generational sizes.
      _element_validate_options_size($field['field_settings']['title_options'],
          $field['field_settings']['max_length']['title'], t('Title options'));
      _element_validate_options_size($field['field_settings']['generational_options'],
          $field['field_settings']['max_length']['generational'], t('Generational options'));
      _name_field_settings_validate($field);
      break;

    case 'save':
      return array('field_settings');

    case 'database columns':
      $components = array_filter($field['field_settings']['components']);
      $columns = array();
      foreach (_name_translations($components) as $key => $title) {
        $max = $field['field_settings']['max_length'][$key];
        $columns[$key] = array(
          'type' => 'varchar',
          'length' => is_numeric($max) ? $max : 255,
          'not null' => FALSE,
          'sortable' => TRUE,
          'views' => TRUE,
        );
      }
      return $columns;

    case 'views data':
      $data = content_views_field_views_data($field);
      $db_info = content_database_info($field);
      $table_alias = content_views_tablename($field);
      // Make changes to $data as needed here.
      return $data;

  }
}

/**
 * Drupal does not validate checkboxes by default. With checkbox validate, the
 * user may get two error messages.
 */
function _name_field_minimal_component_requirements($element, &$form_state) {
  $required_field_set = array_flip(array('given', 'middle', 'family'));
  $value = array_intersect_key($required_field_set, array_filter((array)$element['#value']));
  if (empty($value)) {
    $required_fields = array_intersect_key(_name_translations(), $required_field_set);
    form_set_error(implode('][', $element['#parents']) .'][given',
        t('%label must have one of the following components: %components',
        array('%label' => $element['#title'], '%components' => implode(', ', $required_fields))));
  }

}

/**
 * Custom validation of settings values.
 */
function _name_validate_integer_positive($element, &$form_state) {
  $value = $element['#value'];
  if ($value && !is_numeric($value)|| $value < 1 || $value > 255) {
    form_set_error(implode('][', $element['#array_parents']), t('%label must be a number between 1 and 255.', array('%label' => $element['#title'])));
  }
}

function _name_field_settings_validate($field) {
  $settings = $field['field_settings'];
  $diff = array_diff_key(array_filter($settings['minimum_components']), array_filter($settings['components']));
  if (count($diff)) {
    $components = array_intersect_key(_name_translations(), $diff);
    form_set_error('field_settings][minimum_components]['. key($diff),
        t('The following components for %label are not selected for this name field: %components',
        array('%label' => t('Minimum components'), '%components' => implode(', ', $components))));

  }
}

/**
 * Implementation of hook_field().
 */
function name_field($op, &$node, $field, &$items, $teaser, $page) {
  switch ($op) {
    case 'validate':
      // Validation on required is done here.
      // The max. length is done via Drupal.
      // Min. components is done via name_element validation callback.
      if ($field['required']) {
        if (is_array($items)) {
          foreach ($items as $delta => $item) {
            if (!name_content_is_empty($item, $field)) {
              return $items;
            }
          }
        }
        $error_element = empty($items[0]['_error_element']) ? '' : $items[0]['_error_element'] .'][';
        $error_element .= $field['field_name'] .'[0]'; // TODO
        form_set_error($error_element, t('%label is required.', array('%label' => $field['widget']['label'])));
      }
      return $items;

    case 'sanitize':
      foreach ($items as $delta => $item) {
        foreach (_name_translations() as $key => $title) {
          $items[$delta]['safe'][$key] = check_plain($item[$key]);
        }
      }
  }
}

/**
 * Implementation of hook_content_is_empty().
 */
function name_content_is_empty($item, $field) {
  foreach (_name_translations() as $key => $title) {
    // Title has no meaning by itself.
    if ($key == 'title' || $key == 'generational') {
      continue;
    }
    if (!empty($item[$key])) {
      return FALSE;
    }
  }
  return TRUE;
}

/**
 * Implementation of hook_field_formatter_info().
 */
function name_field_formatter_info() {
  $formatters = array();

  // Three formatter types are returned here: default, raw & plain.
  foreach (_name_formatter_output_types() as $type => $title) {
    $type_info = $type == 'default' ? '' : ' ('. $title .')';

    $formatters[$type] = array(
      'label' => t('Default') . $type_info,
      'field types' => array('name'),
      'multiple values' => CONTENT_HANDLE_CORE,
    );

    foreach (name_get_custom_format_options() as $machine_name => $name_title) {
      $formatters[$type . '_' . $machine_name] = array(
        'label' => $name_title . $type_info,
        'field types' => array('name'),
        'multiple values' => CONTENT_HANDLE_CORE,
      );
    }
  }

  return $formatters;
}

/**
 * Theme function all name field formatters.
 */
function theme_name_formatter_default($element) {
  // Prevents warnings.
  if (!isset($element['#formatter']) || $element['#formatter'] == 'default') {
    $element['#formatter'] = 'default_default';
  }
  list($type, $format) = explode('_', $element['#formatter'], 2);
  $format = name_get_format_by_machine_name($format);

  $parts = array();
  foreach (_name_translations() as $key => $title) {
    if (!empty($element['#item']['safe'][$key])) {
      $parts[$key] = $element['#item']['safe'][$key];
    }
    else {
      $parts[$key] = NULL;
    }
  }

  $output = name_format($parts, $format, array('object' => $element['#node'], 'type' => 'node'));
  switch ($type) {
    case 'plain':
      return strip_tags($output);
    case 'raw':
      return $output;
    case 'default':
    default:
      return check_plain($output);
  }
}

/**
 * Implementation of hook_widget_info().
 */
function name_widget_info() {
  return array(
    'name_widget' => array(
      'label' => t('Name'),
      'field types' => array('name'),
      'multiple values' => CONTENT_HANDLE_CORE,
      'callbacks' => array(
        'default value' => CONTENT_CALLBACK_DEFAULT,
      ),
    ),
  );
}

/**
 * Implementation of hook_widget_settings().
 */
function name_widget_settings($op, $widget) {
  switch ($op) {
    case 'form':
      $form = array();
      $form['instance_settings'] = array(
        '#tree' => TRUE,
      );
      _name_defaults($widget, 'instance_settings');
      $components = _name_translations();
      $form['instance_settings']['size'] = array();
      $form['instance_settings']['title_display'] = array();
      foreach ($components as $key => $title) {
        $form['instance_settings']['size'][$key] = array(
          '#type' => 'textfield',
          '#title' => t('HTML size property for !title', array('!title' => $title)),
          '#default_value' => $widget['instance_settings']['size'][$key],
          '#required' => FALSE,
          '#size' => 10,
          '#description' => t('The maximum length of the field in characters. This must be between 1 and 255.'),
          '#element_validate' => array('_name_validate_integer_positive'),
        );
        $form['instance_settings']['title_display'][$key] = array(
          '#type' => 'radios',
          '#title' => t('Label display for !title', array('!title' => $title)),
          '#default_value' => $widget['instance_settings']['title_display'][$key],
          '#options' => array(
            'title' => t('above'),
            'description' => t('below'),
            'none' => t('hidden'),
          ),
          '#description' => t('This controls how the label of the name component is displayed in the form.'),
        );
        $form['instance_settings']['inline_css_enabled'][$key] = array(
          '#type' => 'checkbox',
          '#title' => t('Use inline styles for !title', array('!title' => $title)),
          '#default_value' => $widget['instance_settings']['inline_css_enabled'][$key],
        );


      }
      $form['instance_settings']['title_field'] = array(
        '#type' => 'radios',
        '#title' => t('Title field type'),
        '#default_value' => $widget['instance_settings']['title_field'],
        '#required' => TRUE,
        '#options' => array('select' => t('Drop-down')),
      );
      $form['instance_settings']['generational_field'] = array(
        '#type' => 'radios',
        '#title' => t('Generational field type'),
        '#default_value' => $widget['instance_settings']['generational_field'],
        '#required' => TRUE,
        '#options' => array('select' => t('Drop-down')),
      );
      $form['instance_settings']['inline_css'] = array(
        '#type' => 'fieldset',
        '#title' => t('Inline CSS styles for name components'),
        '#description' => t('This gives you the option of specifying CSS rules to control the width of the name components. The HTML size for the component is used with the multiplier and the resulting width is rendered as an inline style such as: <em>style="width: 345px;"</em>'),
      );
      $form['instance_settings']['inline_css']['multiplier'] = array(
        '#type' => 'textfield',
        '#title' => t('Multiplier'),
        '#default_value' => $widget['instance_settings']['inline_css']['multiplier'],
        '#required' => TRUE,
        '#description' => t('The width is set by multipling the HTML size with this.'),
      );
      $form['instance_settings']['inline_css']['unit'] = array(
        '#type' => 'radios',
        '#title' => t('CSS Unit'),
        '#default_value' => $widget['instance_settings']['inline_css']['unit'],
        '#required' => TRUE,
        '#options' => array('em' => 'em', 'px' => 'px'),
      );
      return $form;

    case 'save':
      return array('instance_settings');
  }
}

/**
 * Implementation of hook_widget().
 */
function name_widget(&$form, &$form_state, $field, $items, $delta = 0) {
  $widget = $field['widget'];
  _name_defaults($widget, 'instance_settings');
  _name_defaults($field, 'field_settings');
  $fs = $field['field_settings'];
  $ws = $widget['instance_settings'];

  $element = array(
    '#type' => 'name_element',
    '#title' => $widget['label'],
    '#label' => $widget['label'],
    '#components' => array(),
    '#minimum_components' => array_filter($fs['minimum_components']),
    '#default_value' => isset($items[$delta]) ? $items[$delta] : NULL,
  );

  // Field only property. Do the calculations here.
  $inline_css = $ws['inline_css'];

  $components = array_filter($fs['components']);
  foreach (_name_translations() as $key => $title) {
    if (in_array($key, $components)) {
      $element['#components'][$key]['type'] = 'textfield';

      $size = !empty($ws['size'][$key]) ? $ws['size'][$key] : 60;
      $title_display = isset($ws['title_display'][$key]) ? $ws['title_display'][$key] : 'description';

      $element['#components'][$key]['title'] = check_plain($fs['labels'][$key]);
      $element['#components'][$key]['title_display'] = $title_display;

      $element['#components'][$key]['size'] = $size;
      $element['#components'][$key]['maxlength'] = !empty($fs['max_length'][$key]) ? $fs['max_length'][$key] : 255;
      if (isset($ws[$key .'_field']) && $ws[$key .'_field'] == 'select') {
        $element['#components'][$key]['type'] = 'select';
        $element['#components'][$key]['size'] = 1;

        $options = array_filter(explode("\n", $fs[$key .'_options']));
        foreach ($options as $index => $opt) {
          if (preg_match('/^\[vocabulary:([0-9]{1,})\]/', trim($opt), $matches)) {
            unset($options[$index]);
            $max_length = isset($fs['max_length'][$key]) ? $fs['max_length'][$key] : 255;
            foreach (taxonomy_get_tree($matches[1]) as $term) {
              if (drupal_strlen($term->name) <= $max_length) {
                $options[] = $term->name;
              }
            }
          }
        }
        // Options could come from multiple sources, filter duplicates.
        $options = array_unique($options);

        if ($fs && isset($fs['sort_options']) && !empty($fs['sort_options'][$key])) {
          natcasesort($options);
        }
        $default = FALSE;
        foreach ($options as $index => $opt) {
          if (strpos($opt, '--') === 0) {
            unset($options[$index]);
            $default = substr($opt, 2);
          }
        }
        $options = drupal_map_assoc(array_map('trim', $options));
        if ($default !== FALSE) {
          $options = array('' => $default) + $options;
        }
        $element['#components'][$key]['options'] = $options;
      }
      elseif (isset($ws[$key .'_field']) && $ws[$key .'_field'] == 'autocomplete') {
        // TODO $element['#components'][$key]['autocomplete'] = '';
      }


      if (isset($ws['inline_css_enabled'][$key]) && $ws['inline_css_enabled'][$key]) {
        $width = $size * $inline_css['multiplier'];
        switch ($inline_css['unit']) {
          case 'px':
             $width = round($width) . 'px';
             break;
          case 'em':
          default:
             $width = round($width, 1) . 'em';
             break;
        }
        $element['#components'][$key]['attributes'] = array();
        $element['#components'][$key]['attributes']['style'] = 'width:'. $width .';';
      }
    }
    else {
      $element['#components'][$key]['exclude'] = TRUE;
    }

  }

  // Used so that hook_field('validate') knows where to
  // flag an error in deeply nested forms.
  if (empty($form['#parents'])) {
    $form['#parents'] = array();
  }
  $element['_error_element'] = array(
    '#type' => 'value',
    '#value' => implode('][', $form['#parents']),
  );
  return $element;
}

/**
 * Helper form element validator.
 */
function _element_validate_options_size($field_options, $max_length, $label) {
  $values = array_filter(explode("\n", $field_options));
  $long_options = array();
  $options = array();
  foreach ($values as $value) {
    $value = trim($value);
    // Blank option - anything goes!
    if (strpos($value, '--') === 0) {
      $options [] = $value;
    }
    elseif (drupal_strlen($value) > $max_length) {
      $long_options [] = $value;
    }
    elseif (!empty($value)) {
      $options [] = $value;
    }
  }
  if (count($long_options)) {
    form_set_error('field_settings][title_options', t('The following options exceed the maximun allowed %label length: %options', array('%options' => implode(', ', $long_options), '%label' => $label)));
  }
  elseif (empty($options)) {
    form_set_error('field_settings][title_options', t('%label are required.', array('%label' => $label)));
  }
}

/**
 * Helper function to set the defaults for a name field / widget.
 */
function _name_defaults(&$field, $key) {
  $name_info = name_field_info();
  $field[$key] = isset($field[$key]) ? (array) $field[$key] : array();
  foreach ($name_info['name'][$key] as $index => $defaults) {
    if (!isset($field[$key][$index])) {
      if (is_array($defaults)) {
        if (!array_key_exists($index, $field[$key])) {
          $field[$key][$index] = array();
        }
        $field[$key][$index] += $defaults;
      }
      else {
        $field[$key][$index] = $defaults;
      }
    }
  }
}
