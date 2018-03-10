<?php

namespace Drupal\name\Plugin\Field\FieldType;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\Link;
use Drupal\Core\Url;

/**
 * Plugin implementation of the 'name' field type.
 *
 * @FieldType(
 *   id = "name",
 *   label = @Translation("Name"),
 *   description = @Translation("Stores real name."),
 *   default_widget = "name_default",
 *   default_formatter = "name_default"
 * )
 */
class NameItem extends FieldItemBase {

  /**
   * Definition of name field components.
   *
   * @var array
   */
  protected static $components = [
    'title',
    'given',
    'middle',
    'family',
    'generational',
    'credentials',
  ];

  /**
   * {@inheritdoc}
   */
  public static function defaultStorageSettings() {
    $settings = [
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
      ],
    ];

    return $settings + parent::defaultStorageSettings();
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultFieldSettings() {
    $settings = [
      'component_css' => '',
      'component_layout' => 'default',
      'show_component_required_marker' => FALSE,
      'credentials_inline' => FALSE,
      'override_format' => 'default',
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
    ];

    return $settings + parent::defaultFieldSettings();
  }

  /**
   * {@inheritDoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties = [];
    $properties['title'] = DataDefinition::create('string')
      ->setLabel(t('Title'));

    $properties['given'] = DataDefinition::create('string')
      ->setLabel(t('Given'));

    $properties['middle'] = DataDefinition::create('string')
      ->setLabel(t('Middle name(s)'));

    $properties['family'] = DataDefinition::create('string')
      ->setLabel(t('Family'));

    $properties['generational'] = DataDefinition::create('string')
      ->setLabel(t('Generational'));

    $properties['credentials'] = DataDefinition::create('string')
      ->setLabel(t('Credentials'));

    return $properties;
  }

  /**
   * {@inheritDoc}
   */
  public function storageSettingsForm(array &$form, FormStateInterface $form_state, $has_data) {
    $field = $this->getFieldDefinition();
    $settings = $field->getSettings();

    $element = [
      '#tree' => TRUE,
    ];

    $components = _name_translations();
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
    $element['labels'] = [];
    $element['max_length'] = [];
    $element['autocomplete_sources'] = [];
    $autocomplete_sources_options = [];
    if (\Drupal::moduleHandler()->moduleExists('namedb')) {
      $autocomplete_sources_options['namedb'] = $this->t('Names DB');
    }
    // @todo: Optionally add existing data as an autocomplete source.
    $autocomplete_sources_options['title'] = $this->t('Title options');
    $autocomplete_sources_options['generational'] = $this->t('Generational options');

    foreach ($components as $key => $title) {
      $min_length = 1;
      if ($has_data) {
        $min_length = $settings['max_length'][$key];
        // @todo: Port this feature to Drupal 8
        /*
        if ($field['storage']['type'] == 'field_sql_storage') {
          try {
            $table = 'field_data_' . $field['field_name'];
            $column = $field['storage']['details']['sql'][FIELD_LOAD_CURRENT]
            [$table][$key];
            $min_length = db_query("SELECT MAX(CHAR_LENGTH({$column})) AS len FROM {$table}")->fetchField();
            if ($min_length < 1) {
              $min_length = 1;
            }
          }
          catch (Exception $e) {
          }
        }
        */
      }
      $element['max_length'][$key] = [
        '#type' => 'number',
        '#min' => $min_length,
        '#max' => 255,
        '#title' => $this->t('Maximum length for @title', ['@title' => $title]),
        '#default_value' => $settings['max_length'][$key],
        '#required' => TRUE,
        '#size' => 10,
        '#description' => $this->t('The maximum length of the field in characters. This must be between @min and 255.', ['@min' => $min_length]),
      ];
      $element['labels'][$key] = [
        '#type' => 'textfield',
        '#title' => $this->t('Label for @title', ['@title' => $title]),
        '#default_value' => $settings['labels'][$key],
        '#required' => TRUE,
      ];
      $element['autocomplete_source'][$key] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('Autocomplete options'),
        '#default_value' => $settings['autocomplete_source'][$key],
        '#description' => $this->t("This defines what autocomplete sources are available to the field."),
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
        '#default_value' => $settings['autocomplete_separator'][$key],
        '#size' => 10,
      ];
    }

    $element['allow_family_or_given'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow a single valid given or family value to fulfill the minimum component requirements for both given and family components.'),
      '#default_value' => !empty($settings['allow_family_or_given']),
    ];

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
    $sort_options = is_array($settings['sort_options']) ? $settings['sort_options'] : [
      'title' => 'title',
      'generational' => '',
    ];
    $element['sort_options'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Select field sort options'),
      '#default_value' => $sort_options,
      '#description' => $this->t("This enables sorting on the options after the vocabulary terms are added and duplicate values are removed."),
      '#options' => _name_translations([
        'title' => '',
        'generational' => '',
      ]),
    ];

    $element['#pre_render'][] = 'name_field_storage_settings_pre_render';
    return $element;
  }

  /**
   * {@inheritDoc}
   */
  public function isEmpty() {
    foreach ($this->properties as $property) {
      $definition = $property->getDataDefinition();
      if (!$definition->isComputed() && $property->getValue() !== NULL) {
        return FALSE;
      }
    }
    if (isset($this->values)) {
      foreach ($this->values as $name => $value) {
        // Title & generational have no meaning by themselves.
        if ($name == 'title' || $name == 'generational') {
          continue;
        }
        if (isset($value) && strlen($value) && !isset($this->properties[$name])) {
          return FALSE;
        }
      }
    }
    return TRUE;
  }

  /**
   * {@inheritDoc}
   */
  public function fieldSettingsForm(array $form, FormStateInterface $form_state) {
    $settings = $this->getSettings();
    $components = _name_translations();

    $element = [
      'size' => [],
      'title_display' => [],
    ];

    $field_options = [
      'select' => $this->t('Drop-down'),
      'text' => $this->t('Text field'),
      'autocomplete' => $this->t('Autocomplete'),
    ];

    foreach ($components as $key => $title) {
      $element['field_type'][$key] = [
        '#type' => 'radios',
        '#title' => $this->t('@title field type', ['@title' => $components['title']]),
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
        '#default_value' => $settings['size'][$key],
        '#required' => FALSE,
        '#size' => 10,
        '#description' => $this->t('The maximum length of the field in characters. This must be between 1 and 255.'),
      ];

      $element['title_display'][$key] = [
        '#type' => 'radios',
        '#title' => $this->t('Label display for @title', ['@title' => $title]),
        '#default_value' => $settings['title_display'][$key],
        '#options' => [
          'title' => $this->t('above'),
          'description' => $this->t('below'),
          'none' => $this->t('hidden'),
        ],
        '#description' => $this->t('This controls how the label of the component is displayed in the form.'),
      ];

      $element['inline_css'][$key] = [
        '#type' => 'textfield',
        '#title' => $this->t('Additional inline styles for @title input element.', ['@title' => $title]),
        '#default_value' => $settings['inline_css'][$key],
        '#size' => 8,
      ];
    }

    $element['component_css'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Component separator CSS'),
      '#default_value' => $this->getSetting('component_css'),
      '#description' => $this->t('Use this to override the default CSS used when rendering each component. Use "&lt;none&gt;" to prevent the use of inline CSS.'),
    ];

    $items = [
      $this->t('The order for Asian names is Family Middle Given Title'),
      $this->t('The order for Eastern names is Title Family Given Middle'),
      $this->t('The order for Western names is Title First Middle Surname'),
    ];
    $item_list = [
      '#theme' => 'item_list',
      '#items' => $items,
    ];
    $layout_description = $this->t('<p>This controls the order of the widgets that are displayed in the form.</p>')
      . drupal_render($item_list)
      . $this->t('<p>Note that when you select the Asian names format, the Generational field is hidden and defaults to an empty string.</p>');
    $element['component_layout'] = [
      '#type' => 'radios',
      '#title' => $this->t('Language layout'),
      '#default_value' => $this->getSetting('component_layout'),
      '#options' => [
        'default' => $this->t('Western names'),
        'asian' => $this->t('Asian names'),
        'eastern' => $this->t('Eastern names'),
      ],
      '#description' => $layout_description,
    ];
    $element['show_component_required_marker'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show component required marker'),
      '#default_value' => $this->getSetting('show_component_required_marker'),
      '#description' => $this->t('Appends an asterisk after the component title if the component is required as part of a complete name.'),
    ];
    $element['credentials_inline'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show the credentials inline'),
      '#default_value' => $this->getSetting('credentials_inline'),
      '#description' => $this->t('The default position is to show the credentials on a line by themselves. This option overrides this to render the component inline.'),
    ];

    // Add the overwrite user name option.
    if ($this->getFieldDefinition()->getTargetEntityTypeId() == 'user') {

      $preferred_field = \Drupal::config('name.settings')
        ->get('user_preferred');

      $element['name_user_preferred'] = [
        '#type' => 'checkbox',
        '#title' => $this->t("Use this field to override the user's login name?"),
        '#description' => $this->t('You may need to clear the @cache_link before this change is seen everywhere.', [
          '@cache_link' => Link::fromTextAndUrl('Performance cache', Url::fromRoute('system.performance_settings'))->toString(),
        ]),
        '#default_value' => (($preferred_field == $this->getFieldDefinition()->getName()) ? 1 : 0),
      ];

      // Store the machine name of the Name field.
      $element['name_user_preferred_fieldname'] = [
        '#type' => 'hidden',
        '#default_value' => $this->getFieldDefinition()->getName(),
      ];

      $element['override_format'] = [
        '#type' => 'select',
        '#title' => $this->t('User name override format to use'),
        '#default_value' => $this->getSetting('override_format'),
        '#options' => name_get_custom_format_options(),
      ];

      $element['#element_validate'] = [[get_class($this), 'validateUserPreferred']];

    }
    else {
      // We may extend this feature to Profile2 latter.
      $element['override_format'] = [
        '#type' => 'value',
        '#value' => $this->getSetting('override_format'),
      ];
    }

    $element['#pre_render'][] = 'name_field_settings_pre_render';

    return $element;
  }

  /**
   * {@inheritDoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    $columns = [];
    foreach (static::$components as $key) {
      $columns[$key] = [
        'type' => 'varchar',
        'length' => 255,
        'not null' => FALSE,
      ];
    }
    return [
      'columns' => $columns,
      'indexes' => [
        'given' => ['given'],
        'family' => ['family'],
      ],
    ];
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
      elseif (preg_match('/^\[vocabulary:([0-9a-z\_]{1,})\]/', $value, $matches)) {
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
   * Manage whether the name field should override a user's login name.
   */
  public static function validateUserPreferred(&$element, FormStateInterface $form_state, &$complete_form) {

    $value = NULL;
    $config = \Drupal::configFactory()->getEditable('name.settings');

    // Ensure the name field value should override a user's login name.
    if ((!empty($element['name_user_preferred'])) && ($element['name_user_preferred']['#value'] == 1)) {
      // Retrieve the name field's machine name.
      $value = $element['name_user_preferred_fieldname']['#default_value'];
    }

    // Ensure that the login-name-override configuration has changed.
    if ($config->get('user_preferred') != $value) {

      // Update the configuration with the new value.
      $config->set('user_preferred', $value)->save();

      // Retrieve the ID of all existing users.
      $query = \Drupal::entityQuery('user');
      $uids = $query->execute();

      foreach ($uids as $uid) {
        // Invalidate the cache for each user so that
        // the appropriate login name will be displayed.
        Cache::invalidateTags(['user:' . $uid]);
      }

      \Drupal::logger('name')->notice('Cache cleared for data tagged as %tag.', ['%tag' => 'user:{$uid}']);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function generateSampleValue(FieldDefinitionInterface $field_definition) {
    $names = &drupal_static(__FUNCTION__, []);

    // Generate 50 random names based off the field settings. These are stored
    // for future use to prevent the need to regenerate these.
    if (empty($names)) {
      // Only use the enabled components.
      $settings = $field_definition->getSettings();
      $components_used = array_filter($settings['components']);

      // Parse the settings to find the field title and generational options.
      $titles = \Drupal::service('name.options_provider')->getOptions($field_definition, 'title');
      unset($titles['']);
      $generational = \Drupal::service('name.options_provider')->getOptions($field_definition, 'generational');
      unset($generational['']);

      $given = ['John', 'William', 'James', 'George', 'Charles', 'Frank', 'Joseph', 'Henry', 'Robert', 'Thomas', 'Edward', 'Harry', 'Walter', 'Arthur', 'Fred', 'Albert', 'Samuel', 'Clarence', 'Louis', 'David', 'Joe', 'Charlie', 'Richard', 'Ernest', 'Roy', 'Will', 'Andrew', 'Jesse', 'Oscar', 'Willie', 'Daniel', 'Benjamin', 'Carl', 'Sam', 'Alfred', 'Earl', 'Peter', 'Elmer', 'Frederick', 'Howard', 'Lewis', 'Ralph', 'Herbert', 'Paul', 'Lee', 'Tom', 'Herman', 'Martin', 'Jacob', 'Michael', 'Mary', 'Anna', 'Emma', 'Elizabeth', 'Margaret', 'Minnie', 'Ida', 'Bertha', 'Clara', 'Alice', 'Annie', 'Florence', 'Bessie', 'Grace', 'Ethel', 'Sarah', 'Ella', 'Martha', 'Nellie', 'Mabel', 'Laura', 'Carrie', 'Cora', 'Helen', 'Maude', 'Lillian', 'Gertrude', 'Rose', 'Edna', 'Pearl', 'Edith', 'Jennie', 'Hattie', 'Mattie', 'Eva', 'Julia', 'Myrtle', 'Louise', 'Lillie', 'Jessie', 'Frances', 'Catherine', 'Lula', 'Lena', 'Marie', 'Ada', 'Josephine', 'Fanny', 'Lucy', 'Dora'];
      $middle = ['Aaron', 'Bailey', 'Carson', 'Damon', 'Edwin', 'Francis', 'Garrett', 'Holden', 'Ivan', 'Jace', 'Keaton', 'Layne', 'Malcolm', 'Noah', 'Owen', 'Payton', 'Quinn', 'Randall', 'Sawyer', 'Tilton', 'Tanner', 'Vernon', 'Wade', 'Zachariah', 'Aiden', 'Bennett', 'Chance', 'Dante', 'Ellis', 'Glenn', 'Houston', 'Jackson', 'Kelton', 'Layton', 'Marshall', 'Noel', 'Peyton', 'Quintin', 'Reese', 'Sean', 'Stewart', 'Taylor', 'Warren', 'Anton', 'Blair', 'Charles', 'Denver', 'Emmett', 'Grant', 'Jade', 'Adele', 'Bailee', 'Camden', 'Dawn', 'Elein', 'Fawn', 'Haiden', 'Jacklyn', 'Kae', 'Lane', 'Madisen', 'Nadeen', 'Ocean', 'Payten', 'Raine', 'Selene', 'Taye', 'Zion', 'Alice', 'Berlynn', 'Candice', 'Debree', 'Ellen', 'Faye', 'Hollyn', 'Jae', 'Kaitlin', 'Lashon', 'Mae', 'Naveen', 'Raven', 'Sharon', 'Taylore', 'Zoe', 'Anise', 'Bernice', 'Carelyn', 'Debree', 'Erin', 'Faye', 'Hollyn', 'Jane', 'Kalan', 'Lee', 'Merle', 'Olive', 'Reagan', 'Sue', 'Ann', 'Bree'];
      $family = ['Smith', 'Johnson', 'Williams', 'James', 'Brown', 'Davis', 'Miller', 'Wilson', 'Moore', 'Taylor', 'Anderson', 'Thomas', 'Jackson', 'White', 'Harris', 'Martin', 'Tompson', 'Garcia', 'Martinez', 'Robinson', 'Clark', 'Rodrigez', 'Lewis', 'Lee', 'Walker', 'Hall', 'Allen', 'Young', 'Hernandez', 'King', 'Wright', 'Lopez', 'Hill', 'Scott', 'Green', 'Adams', 'Baker', 'Gonzales', 'Nelson', 'Carter', 'Mitchell', 'Perez', 'Roberts', 'Turner', 'Phillips', 'Campbell', 'Parker', 'Evans', 'Edwards', 'Collins', 'Stewart', 'Sanches', 'Morris', 'Rogers', 'Reed', 'Cook', 'Morgan', 'Bell', 'Murphy', 'Bailey', 'Rivera', 'Cooper', 'Richardson', 'Cox', 'Howard', 'Ward', 'Torez', 'Peterson', 'Gray', 'Ramirez', 'James', 'Watson', 'Brooks', 'Kelly', 'Sanders', 'Price', 'Bennett', 'Wood', 'Barness', 'Ross', 'Henderson', 'Coleman', 'Jenkins', 'Perry', 'Powel', 'Long', 'Patterson', 'Hughes', 'Flores', 'Washington', 'Butler', 'Simpson', 'Foster', 'Gonzales', 'Bryant', 'Alexander', 'Russel', 'Griffin', 'Diaz', 'Hayes'];
      $credentials = ['BA', 'EdD', 'MA', 'BAppSc', 'KBE', 'CCISO', 'J.P.', 'MD', 'CEH'];
      // Random use the components to create truly random names.
      for ($i = 0; $i < 50; $i++) {
        $name = [
          'title' => '',
          'given' => $given[array_rand($given)],
          'middle' => '',
          'family' => $family[array_rand($family)],
          'generational' => '',
          'credentials' => '',
        ];
        // Mix up the titles, middle, creds & generational.
        if (rand(1, 2) == 1) {
          $name['title'] = $titles[array_rand($titles)];
        }
        if (rand(1, 2) == 1) {
          $name['middle'] = $middle[array_rand($middle)];
        }
        if (rand(1, 2) == 1) {
          $creds = [];
          for ($j = 0, $limit = rand(1, 4); $j <= $limit; $j++) {
            $creds[] = $credentials[array_rand($credentials)];
          }
          $name['credentials'] = implode(', ', $creds);
        }
        if (rand(1, 3) == 1) {
          $name['generational'] = $generational[array_rand($generational)];
        }
        $names[] = array_intersect_key($name, $components_used);
      }
    }
    return $names[array_rand($names)];
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
