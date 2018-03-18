<?php

namespace Drupal\name\Traits;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Component\Utility\Unicode;
use Drupal\name\NameOptionsProvider;

/**
 * Name settings trait.
 *
 * Used for handling the field and webform (hopefully) element settings.
 */
trait NameSettingsTrait {

  /**
   * Gets the default settings for controlling a name element.
   *
   * @return array
   *   Default settings.
   */
  protected static function getDefaultNameSettings() {
    return [
      'components' => [
        'title' => TRUE,
        'given' => TRUE,
        'middle' => TRUE,
        'family' => TRUE,
        'generational' => TRUE,
        'credentials' => TRUE,
      ],
      'minimum_components' => [
        'title' => FALSE,
        'given' => TRUE,
        'middle' => FALSE,
        'family' => TRUE,
        'generational' => FALSE,
        'credentials' => FALSE,
      ],
      'allow_family_or_given' => FALSE,
      'labels' => [
        'title' => t('Title'),
        'given' => t('Given'),
        'middle' => t('Middle name(s)'),
        'family' => t('Family'),
        'generational' => t('Generational'),
        'credentials' => t('Credentials'),
      ],
      'max_length' => [
        'title' => 31,
        'given' => 63,
        'middle' => 127,
        'family' => 63,
        'generational' => 15,
        'credentials' => 255,
      ],
      'field_type' => [
        'title' => 'select',
        'given' => 'text',
        'middle' => 'text',
        'family' => 'text',
        'generational' => 'select',
        'credentials' => 'text',
      ],
      'size' => [
        'title' => 6,
        'given' => 20,
        'middle' => 20,
        'family' => 20,
        'generational' => 5,
        'credentials' => 35,
      ],
      'title_display' => [
        'title' => 'description',
        'given' => 'description',
        'middle' => 'description',
        'family' => 'description',
        'generational' => 'description',
        'credentials' => 'description',
      ],
      'inline_css' => [
        'title' => '',
        'given' => '',
        'middle' => '',
        'family' => '',
        'generational' => '',
        'credentials' => '',
      ],
      'autocomplete_source' => [
        'title' => [
          'title',
        ],
        'given' => [],
        'middle' => [],
        'family' => [],
        'generational' => [
          'generation',
        ],
        'credentials' => [],
      ],
      'autocomplete_separator' => [
        'title' => ' ',
        'given' => ' -',
        'middle' => ' -',
        'family' => ' -',
        'generational' => ' ',
        'credentials' => ', ',
      ],
      'title_options' => [
        t('-- --'),
        t('Mr.'),
        t('Mrs.'),
        t('Miss'),
        t('Ms.'),
        t('Dr.'),
        t('Prof.'),
      ],
      'generational_options' => [
        t('-- --'),
        t('Jr.'),
        t('Sr.'),
        t('I'),
        t('II'),
        t('III'),
        t('IV'),
        t('V'),
        t('VI'),
        t('VII'),
        t('VIII'),
        t('IX'),
        t('X'),
      ],
      'sort_options' => [
        'title' => FALSE,
        'given' => FALSE,
        'middle' => FALSE,
        'family' => FALSE,
        'generational' => FALSE,
        'credentials' => FALSE,
      ],
      'component_css' => '',
      'component_layout' => 'default',
      'show_component_required_marker' => FALSE,
      'credentials_inline' => FALSE,
    ];
  }

  /**
   * Returns a form for the default settings defined above.
   *
   * The following keys are closely tied to the pre-render function to theme
   * the settings into a nicer table.
   * - #indent_row: Adds an empty TD cell and adds an 'elements' child that
   *   contains the children (if given).
   * - #table_group: Used to either position within the table by the element
   *   key, or set to 'none', to append it below the table.
   *
   * Any element within the table should have component keyed children.
   *
   * Other elements are rendered directly.
   *
   * @param array $settings
   *   The settings.
   * @param array $form
   *   The form where the settings form is being included in.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state of the (entire) configuration form.
   *
   * @return array
   *   The form definition for the field settings.
   */
  protected function getDefaultNameSettingsForm(array $settings, array &$form, FormStateInterface $form_state, $has_data = TRUE) {

    $components = _name_translations();
    $field_options = [
      'select' => $this->t('Drop-down'),
      'text' => $this->t('Text field'),
      'autocomplete' => $this->t('Autocomplete'),
    ];
    $title_display_options = [
      'title' => $this->t('above'),
      'description' => $this->t('below'),
      'placeholder' => $this->t('placeholder'),
      'none' => $this->t('hidden'),
    ];
    // @todo: Refactor out for alternative sources.
    $autocomplete_sources_options = [
      'title' => $this->t('Title options'),
      'generational' => $this->t('Generational options'),
    ];

    $element = [];
    $element['components'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Components'),
      '#default_value' => array_keys(array_filter($settings['components'])),
      '#required' => TRUE,
      '#description' => $this->t('Only selected components will be activated on this field. All non-selected components / component settings will be ignored.'),
      '#options' => $components,
    ];
    $element['minimum_components'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Minimum components'),
      '#default_value' => array_keys(array_filter($settings['minimum_components'])),
      '#required' => TRUE,
      '#description' => $this->t('The minimal set of components required before the field is considered completed enough to save.'),
      '#options' => $components,
      '#element_validate' => [[get_class($this), 'validateMinimumComponents']],
    ];
    // Placeholder for additional fields to couple with the components section.
    $element['minimum_components_extra'] = [
      '#indent_row' => TRUE,
    ];
    $element['allow_family_or_given'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow a single valid given or family value to fulfill the minimum component requirements for both given and family components.'),
      '#default_value' => !empty($settings['allow_family_or_given']),
      '#table_group' => 'minimum_components_extra',
    ];
    $element['show_component_required_marker'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show component required marker'),
      '#default_value' => $this->getSetting('show_component_required_marker'),
      '#description' => $this->t('Appends an asterisk after the component title if the component is required as part of a complete name.'),
      '#table_group' => 'minimum_components_extra',
    ];
    $element['labels'] = [
      '#title' => $this->t('Labels'),
      '#description' => $this->t('The labels are used to distinguish the components.'),
    ];
    $element['title_display'] = [
      '#title' => $this->t('Label display'),
      '#description' => $this->t('The title display controls how the label of the name component is displayed in the form:<br>"%above" is the standard title;<br>"%below" is the standard description;<br>"%placeholder" uses the placeholder attribute, select lists do not support this option;<br>"%hidden" removes the label.', [
        '%above' => t('above'),
        '%below' => t('below'),
        '%placeholder' => t('placeholder'),
        '%hidden' => t('hidden'),
      ]),
    ];
    $element['field_type'] = [
      '#title' => $this->t('Field type'),
      '#description' => $this->t('The Field type controls how the field is rendered. Autocomplete is a text field with autocomplete, and the behaviour of this is controlled by the field settings.'),
    ];
    $element['max_length'] = [
      '#title' => $this->t('Maximum length'),
      '#description' => $this->t('The maximum length of the field in characters. This must be between 1 and 255.'),
    ];
    $element['size'] = [
      '#title' => $this->t('HTML size'),
      '#description' => $this->t('The HTML size property tells the browser what the width of the field should be when it is rendered. This gets overriden by the themes CSS properties. This must be between 1 and 255.'),
    ];
    $element['inline_css'] = [
      '#title' => $this->t('Inline styles'),
      '#description' => $this->t('Additional inline styles for the input element. i.e. "width: 45px; background-color: #f3f3f3".'),
    ];
    // Placeholder for additional fields to couple with the styles section.
    $element['inline_css_extra'] = [
      '#indent_row' => TRUE,
    ];
    $element['component_css'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Component separator CSS'),
      '#default_value' => $this->getSetting('component_css'),
      '#description' => $this->t('Use this to override the default CSS used when rendering each component. Use "&lt;none&gt;" to prevent the use of inline CSS.'),
      '#table_group' => 'inline_css_extra',
    ];
    $element['credentials_inline'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show the credentials inline'),
      '#default_value' => $this->getSetting('credentials_inline'),
      '#description' => $this->t('The default position is to show the credentials on a line by themselves. This option overrides this to render the component inline.'),
      '#table_group' => 'inline_css_extra',
    ];

    $sort_options = is_array($settings['sort_options']) ? $settings['sort_options'] : [
      'title' => 'title',
      'generational' => '',
    ];
    $element['sort_options'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Sort options'),
      '#default_value' => $sort_options,
      '#description' => $this->t("This enables sorting on the options after the vocabulary terms are added and duplicate values are removed."),
      '#options' => _name_translations([
        'title' => '',
        'generational' => '',
      ]),
    ];

    $element['autocomplete_source'] = [
      '#title' => $this->t('Autocomplete sources'),
      '#description' => $this->t('At least one value must be selected before you can enable the autocomplete option on the input textfields.'),
    ];

    $element['autocomplete_separator'] = [
      '#title' => $this->t('Autocomplete separator'),
      '#description' => $this->t('This allows you to override the default handling that the autocomplete uses to handle separations between components. If empty, this defaults to a single space.'),
    ];

    foreach ($components as $key => $title) {
      $min_length = 1;
      $element['max_length'][$key] = [
        '#type' => 'number',
        '#min' => $min_length,
        '#max' => 255,
        '#title' => $this->t('Maximum length for @title', ['@title' => $title]),
        '#title_display' => 'invisible',
        '#default_value' => $settings['max_length'][$key],
        '#required' => TRUE,
        '#size' => 5,
      ];
      $element['labels'][$key] = [
        '#type' => 'textfield',
        '#title' => $this->t('Label for @title', ['@title' => $title]),
        '#title_display' => 'invisible',
        '#default_value' => $settings['labels'][$key],
        '#required' => TRUE,
        '#size' => 10,
      ];
      $element['autocomplete_source'][$key] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('Autocomplete options'),
        '#title_display' => 'invisible',
        '#default_value' => $settings['autocomplete_source'][$key],
        '#options' => $autocomplete_sources_options,
      ];
      if ($key != 'title') {
        unset($element['autocomplete_source'][$key]['#options']['title']);
      }
      if ($key != 'generational') {
        unset($element['autocomplete_source'][$key]['#options']['generational']);
      }
      $element['autocomplete_separator'][$key] = [
        '#type' => 'textfield',
        '#title' => $this->t('Autocomplete separator for @title', ['@title' => $title]),
        '#title_display' => 'invisible',
        '#default_value' => $settings['autocomplete_separator'][$key],
        '#size' => 10,
      ];
      $element['field_type'][$key] = [
        '#type' => 'radios',
        '#title' => $this->t('@title field type', ['@title' => $components['title']]),
        '#title_display' => 'invisible',
        '#default_value' => $settings['field_type'][$key],
        '#required' => TRUE,
        '#options' => $field_options,
      ];

      if (!($key == 'title' || $key == 'generational')) {
        unset($element['field_type'][$key]['#options']['select']);
      }

      $element['size'][$key] = [
        '#type' => 'number',
        '#min' => 1,
        '#max' => 255,
        '#title' => $this->t('HTML size property for @title', ['@title' => $title]),
        '#title_display' => 'invisible',
        '#default_value' => $settings['size'][$key],
        '#required' => FALSE,
        '#size' => 10,
      ];

      $element['title_display'][$key] = [
        '#type' => 'radios',
        '#title' => $this->t('Label display for @title', ['@title' => $title]),
        '#title_display' => 'invisible',
        '#default_value' => $settings['title_display'][$key],
        '#options' => $title_display_options,
      ];

      $element['inline_css'][$key] = [
        '#type' => 'textfield',
        '#title' => $this->t('Additional inline styles for @title input element.', ['@title' => $title]),
        '#title_display' => 'invisible',
        '#default_value' => $settings['inline_css'][$key],
        '#size' => 8,
      ];
    }

    // TODO - Grouping & grouping sort
    // TODO - Allow reverse free tagging back into the vocabulary.
    $title_options = implode("\n", array_filter($settings['title_options']));
    $element['title_options'] = [
      '#type' => 'textarea',
      '#title' => $this->t('@title options', ['@title' => $components['title']]),
      '#default_value' => $title_options,
      '#required' => TRUE,
      '#description' => $this->t("Enter one @title per line. Prefix a line using '--' to specify a blank value text. For example: '--Please select a @title'.", [
        '@title' => $components['title'],
      ]),
      '#element_validate' => [[get_class($this), 'validateTitleOptions']],
      '#table_group' => 'none',
    ];
    $generational_options = implode("\n", array_filter($settings['generational_options']));
    $element['generational_options'] = [
      '#type' => 'textarea',
      '#title' => $this->t('@generational options', ['@generational' => $components['generational']]),
      '#default_value' => $generational_options,
      '#required' => TRUE,
      '#description' => $this->t("Enter one @generational suffix option per line. Prefix a line using '--' to specify a blank value text. For example: '----'.", [
        '@generational' => $components['generational'],
      ]),
      '#element_validate' => [[get_class($this), 'validateGenerationalOptions']],
      '#table_group' => 'none',
    ];
    if (\Drupal::moduleHandler()->moduleExists('taxonomy')) {
      // TODO - Make the labels more generic.
      // Generational suffixes may be imported from one or more vocabularies
      // using the tag '[vocabulary:xxx]', where xxx is the vocabulary id.
      // Terms that exceed the maximum length of the generational suffix are
      // not added to the options list.
      $element['title_options']['#description'] .= ' ' . $this->t("%label_plural may be also imported from one or more vocabularies using the tag '[vocabulary:xxx]', where xxx is the vocabulary machine-name or id. Terms that exceed the maximum length of the %label are not added to the options list.", [
          '%label_plural' => $this->t('Titles'),
          '%label' => $this->t('Title'),
        ]);
      $element['generational_options']['#description'] .= ' ' . $this->t("%label_plural may be also imported from one or more vocabularies using the tag '[vocabulary:xxx]', where xxx is the vocabulary machine-name or id. Terms that exceed the maximum length of the %label are not added to the options list.", [
          '%label_plural' => $this->t('Generational suffixes'),
          '%label' => $this->t('Generational suffix'),
        ]);
    }

    $items = [
      $this->t('The order for Asian names is Family Middle Given Title Credentials'),
      $this->t('The order for Eastern names is Title Family Given Middle Credentials'),
      $this->t('The order for German names is Title Credentials Given Middle Surname'),
      $this->t('The order for Western names is Title Given Middle Surname Credentials'),
    ];
    $item_list = [
      '#theme' => 'item_list',
      '#items' => $items,
    ];
    $layout_description = $this->t('<p>This controls the order of the widgets that are displayed in the form.</p>')
      . drupal_render($item_list)
      . $this->t('<p>Note that when you select the Asian and German name formats, the Generational field is hidden and defaults to an empty string.</p>');
    $element['component_layout'] = [
      '#type' => 'radios',
      '#title' => $this->t('Language layout'),
      '#default_value' => $this->getSetting('component_layout'),
      '#options' => [
        'default' => $this->t('Western names'),
        'asian' => $this->t('Asian names'),
        'eastern' => $this->t('Eastern names'),
        'german' => $this->t('German names'),
      ],
      '#description' => $layout_description,
      '#table_group' => 'none',
    ];

    $element['#pre_render'][] = [$this, 'fieldSettingsFormPreRender'];

    return $element;
  }


  /**
   * Themes up the field settings into a table.
   */
  public function fieldSettingsFormPreRender($form) {
    $components = _name_translations();
    // This provdes the base layout for the fields.
    $form = [
        'name_settings' => [
          '#prefix' => '<table>',
          '#suffix' => '</table>',
          '#weight' => -2,
          'thead' => [
            '#prefix' => '<thead><tr><th>' . t('Field') . '</th>',
            '#suffix' => '</tr></thead>',
            '#weight' => -3,
          ],
          'tbody' => [
            '#prefix' => '<tbody>',
            '#suffix' => '</tbody>',
            '#weight' => -2,
          ],
        ],
      ] + $form;
    foreach ($components as $key => $title) {
      $form['name_settings']['thead'][$key] = [
        '#markup' => $title,
        '#prefix' => '<th>',
        '#suffix' => '</th>',
      ];
    }

    $help_footer_notes = [];
    $footer_notes_counter = 0;
    foreach (Element::children($form) as $child) {
      if ($child == 'name_settings') {
        continue;
      }

      if (!empty($form[$child]['#table_group'])) {
        if ($form[$child]['#table_group'] == 'none') {
          continue;
        }
        $target_key = $form[$child]['#table_group'];
        $form['name_settings']['tbody'][$target_key]['elements'][$child] = $form[$child];
        unset($form[$child]);
      }
      elseif (!empty($form[$child]['#indent_row'])) {
        $form['name_settings']['tbody'][$child] = [
          '#prefix' => '<tr><td>&nbsp;</td>',
          '#suffix' => '</tr>',
          'elements' => [
              '#prefix' => '<td colspan="6">',
              '#suffix' => '</td>',
            ] + $form[$child],
        ];
        unset($form[$child]);
      }
      else {
        $footnote_sup = '';
        if (!empty($form[$child]['#description'])) {
          $footnote_sup = $this->t('<sup>@number</sup>', ['@number' => ++$footer_notes_counter]);
          $help_footer_notes[] = $form[$child]['#description'];
          unset($form[$child]['#description']);
        }

        $form['name_settings']['tbody'][$child] = [
          '#prefix' => '<tr><th>' . $form[$child]['#title'] . $footnote_sup . '</th>',
          '#suffix' => '</tr>',
        ];
        foreach (array_keys($components) as $weight => $key) {
          if (isset($form[$child][$key])) {
            $form[$child][$key]['#attributes']['title'] = $form[$child][$key]['#title'];
            if (isset($form[$child][$key]['#type'])) {
              switch ($form[$child][$key]['#type']) {
                case 'checkbox':
                  $form[$child][$key]['#title_display'] = 'invisible';
                  break;
              }
            }
            $form['name_settings']['tbody'][$child][$key] = [
                '#prefix' => '<td>',
                '#suffix' => '</td>',
                '#weight' => $weight,
              ] + $form[$child][$key];
            // Elements with components are dependant on the component checkbox
            // being selected.
            if ($child != 'components') {
              $form['name_settings']['tbody'][$child][$key]['#states'] = [
                'visible' => [
                  ':input[name$="[components][' . $key . ']"]' => array(
                    'checked' => TRUE,
                  ),
                ],
              ];
            }
          }
          else {
            $form['name_settings']['tbody'][$child][$key] = [
              '#prefix' => '<td>',
              '#suffix' => '</td>',
              '#markup' => "&nbsp;",
              '#weight' => $weight,
            ];
          }
        }

        unset($form[$child]);
      }
    }
    if ($help_footer_notes) {
      $form['name_settings_footnotes'] = [
        '#type' => 'details',
        '#title' => t('Footnotes'),
        '#collapsible' => TRUE,
        '#collapsed' => TRUE,
        '#parents' => [],
        '#weight' => -1,
        'help_items' => [
          '#theme' => 'item_list',
          '#list_type' => 'ol',
          '#items' => $help_footer_notes,
        ],
      ];
    }
    $form['#sorted'] = FALSE;

    return $form;
  }


  /**
   * Helper function to validate minimum components.
   *
   * @param array $element
   *   Element being validated.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public static function validateMinimumComponents(array $element, FormStateInterface $form_state) {
    $minimum_components = $form_state->getValue(['settings', 'minimum_components']);
    $diff = array_intersect(array_keys(array_filter($minimum_components)), ['given', 'family']);
    if (count($diff) == 0) {
      $components = array_intersect_key(_name_translations(), array_flip(['given', 'family']));
      $form_state->setError($element, t('%label must have one of the following components: %components', [
        '%label' => t('Minimum components'),
        '%components' => implode(', ', $components),
      ]));
    }

    $components = $form_state->getValue(['settings', 'components']);
    $minimum_components = $form_state->getValue(['settings', 'minimum_components']);
    $diff = array_diff_key(array_filter($minimum_components), array_filter($components));
    if (count($diff)) {
      $components = array_intersect_key(_name_translations(), $diff);
      $form_state->setError($element, t('%components can not be selected for %label when they are not selected for %label2.', [
        '%label' => t('Minimum components'),
        '%label2' => t('Components'),
        '%components' => implode(', ', $components),
      ]));
    }
  }

  /**
   * Helper function to validate minimum components.
   *
   * @param array $element
   *   Element being validated.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public static function validateTitleOptions($element, FormStateInterface $form_state) {
    $values = static::extractAllowedValues($element['#value']);
    $max_length = $form_state->getValue(['settings', 'max_length', 'title']);
    static::validateOptions($element, $form_state, $values, $max_length);
  }

  /**
   * Helper function to validate minimum components.
   *
   * @param array $element
   *   Element being validated.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public static function validateGenerationalOptions($element, FormStateInterface $form_state) {
    $values = static::extractAllowedValues($element['#value']);
    $max_length = $form_state->getValue(['settings', 'max_length', 'generational']);
    static::validateOptions($element, $form_state, $values, $max_length);
  }

  /**
   * Helper function to validate minimum components.
   *
   * @param array $element
   *   Element being validated.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param mixed $values
   *   Values to check.
   * @param int $max_length
   *   The max length.
   */
  protected static function validateOptions($element, FormStateInterface $form_state, $values, $max_length) {
    $label = $element['#title'];

    $long_options = [];
    $valid_options = [];
    $default_options = [];
    foreach ($values as $value) {
      $value = trim($value);
      // Blank option - anything goes!
      if (strpos($value, '--') === 0) {
        $default_options[] = $value;
      }
      // Simple checks on the taxonomy includes.
      elseif (preg_match(NameOptionsProvider::vocabularyRegExp, $value, $matches)) {
        if (!\Drupal::moduleHandler()->moduleExists('taxonomy')) {
          $form_state->setError($element, t("The taxonomy module must be enabled before using the '%tag' tag in %label.", [
            '%tag' => $matches[0],
            '%label' => $label,
          ]));
        }
        elseif ($value !== $matches[0]) {
          $form_state->setError($element, t("The '%tag' tag in %label should be on a line by itself.", [
            '%tag' => $matches[0],
            '%label' => $label,
          ]));
        }
        else {
          $vocabulary = entity_load('taxonomy_vocabulary', $matches[1]);
          if ($vocabulary) {
            $valid_options[] = $value;
          }
          else {
            $form_state->setError($element, t("The vocabulary '%tag' in %label could not be found.", [
              '%tag' => $matches[1],
              '%label' => $label,
            ]));
          }
        }
      }
      elseif (Unicode::strlen($value) > $max_length) {
        $long_options[] = $value;
      }
      elseif (!empty($value)) {
        $valid_options[] = $value;
      }
    }
    if (count($long_options)) {
      $form_state->setError($element, t('The following options exceed the maximum allowed %label length: %options', [
        '%options' => implode(', ', $long_options),
        '%label' => $label,
      ]));
    }
    elseif (empty($valid_options)) {
      $form_state->setError($element, t('%label are required.', [
        '%label' => $label,
      ]));
    }
    elseif (count($default_options) > 1) {
      $form_state->setError($element, t('%label can only have one blank value assigned to it.', [
        '%label' => $label,
      ]));
    }

    $form_state->setValueForElement($element, array_merge($default_options, $valid_options));
  }

  /**
   * Helper function to get the allowed values.
   *
   * @param string $string
   *   The string to parse.
   *
   * @return array
   *   The parsed values.
   */
  protected static function extractAllowedValues($string) {
    return array_filter(array_map('trim', explode("\n", $string)));
  }

}
