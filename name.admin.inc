<?php
// $Id$

/**
 * @file
 * General administration functions.
 */

/**
 * Form builder function for module settings.
 */
function name_admin_settings_form() {
  $form['name_settings'] = array('#tree' => TRUE);
  $settings = name_settings();

  $form['name_settings']['default_format'] = array(
    '#type' => 'textfield',
    '#title' => t('Default name format'),
    '#default_value' => $settings['default_format'],
    '#description' => t('See help on drupal.org for more info.'),
  );
  $form['name_settings']['sep1'] = array(
    '#type' => 'textfield',
    '#title' => t('Separator 1 replacement token'),
    '#default_value' => $settings['sep1'],
  );
  $form['name_settings']['sep2'] = array(
    '#type' => 'textfield',
    '#title' => t('Separator 2 replacement token'),
    '#default_value' => $settings['sep2'],
  );
  $form['name_settings']['sep3'] = array(
    '#type' => 'textfield',
    '#title' => t('Separator 3 replacement token'),
    '#default_value' => $settings['sep3'],
  );
  $form['name_format_help'] = array(
    '#type' => 'fieldset',
    '#title' => t('Format string help'),
    '#collapsible' => TRUE,
    '#collapsed' => TRUE,
    '#suffix' => '<div><br/>&nbsp;</div>' // Too close to the save button
  );
  $form['name_format_help']['help'] = array(
    '#value' => theme('name_format_parameter_help'),
  );
  return system_settings_form($form);
}

/**
 * Lists the known custom formats.
 */
function name_list_custom_formats() {
  $header = array(t('Name'), t('System code'), t('Format'), t('Examples'), t('Actions'));
  $rows = array();

  $example_names = array(
    array(
      'title' => 'Mr',
      'given' => 'Joe',
      'middle' => 'John Peter Mark',
      'family' => 'Doe',
      'generational' => 'Jnr.',
      'credentials' => 'B.Sc., Ph.D.',
    ),
    array(
      'title' => '',
      'given' => 'JOAN',
      'middle' => 'SUE',
      'family' => 'DOE',
      'generational' => '',
      'credentials' => '',
    ),
    array(
      'title' => '',
      'given' => 'Prince',
      'middle' => '',
      'family' => '',
      'generational' => '',
      'credentials' => '',
    ),

  );
  $schema['name_custom_format'] = array(
    'fields' => array(
      'ncfid' => array(
        'description' => t('The primary identifier for a custom format.'),
        'type' => 'serial',
        'unsigned' => TRUE,
        'not null' => TRUE),
      'name' => array(
        'description' => t('The name to identify the custom format to a user.'),
        'type' => 'varchar',
        'length' => 255,
        'not null' => TRUE),
      'machine_name' => array(
        'description' => t('The machine name to identify the custom format to the system.'),
        'type' => 'varchar',
        'length' => 255,
        'not null' => TRUE),
      'format' => array(
        'description' => t('The format string to apply to names.'),
        'type' => 'varchar',
        'length' => 255,
        'not null' => TRUE),
    ),
    'primary key' => array('ncfid'),
  );
  $default_format = array(
    'ncfid' => 0,
    'name' => t('Default'),
    'machine_name' => 'default',
    'format' => name_settings('default_format'),
  );
  $custom_formats = array('0' => $default_format) + name_get_custom_formats();

  foreach ($custom_formats as $ncfid => $tag) {
    $row = array();
    $row[] = l($tag['name'], 'admin/settings/name/'. ($ncfid ? $ncfid : 'settings'));
    $row[] = $tag['machine_name'];
    $row[] = check_plain($tag['format']);

    $examples = array();
    foreach ($example_names as $example_name) {
      $examples []= check_plain(name_format($example_name, $tag['format']));
    }
    $row[] = implode('<br/>', $examples);

    if ($ncfid) {
      $links = array();
      $links[] = l(t('Edit'), 'admin/settings/name/'. $ncfid);
      $links[] = l(t('Delete'), 'admin/settings/name/'. $ncfid .'/delete');
      $row[] = implode('&nbsp;&nbsp;&nbsp;&nbsp;', $links);
    }
    else {
      $row[] = '';
    }
    $rows[] = $row;
  }

  $help = '<p><strong>'. t('The three examples are for the following users:') .'</strong><p>';
  $help_items = array();
  foreach ($example_names as $example_name) {
    $help_items[] = t('The example %user has the following components; title - %title, given - %given, middle - %middle, family - %family, generational - %generational, credentials - %credentials',
    array(
      '%user' => name_format($example_name, 't+ g+ m+ f+ g+ c'),
      '%title' => $example_name['title'] ? $example_name['title'] : '<none>',
      '%given' => $example_name['given'] ? $example_name['given'] : '<none>',
      '%middle' => $example_name['middle'] ? $example_name['middle'] : '<none>',
      '%family' => $example_name['family'] ? $example_name['family'] : '<none>',
      '%generational' => $example_name['generational'] ? $example_name['generational'] : '<none>',
      '%credentials' => $example_name['credentials'] ? $example_name['credentials'] : '<none>',
    ));
  }

  $fieldset = array(
    '#type' => 'fieldset',
    '#title' => t('Format string help'),
    '#collapsible' => TRUE,
    '#collapsed' => TRUE,
  );
  $fieldset['help'] = array(
    '#value' => theme('name_format_parameter_help'),
  );
  $output = theme('table', $header, $rows) . $help . theme('item_list', $help_items) . drupal_render($fieldset);
  return $output;
}

function name_custom_formats_form($form_state, $edit = array()) {
  $edit += array(
    'ncfid' => NULL,
    'name' => '',
    'machine_name' => '',
    'format' => '',
  );

  $form = array();
  $form['ncfid'] = array(
    '#type' => 'value',
    '#value' => $edit['ncfid'],
  );
  $form['name'] = array(
    '#type' => 'textfield',
    '#title' => t('Name'),
    '#default_value' => $edit['name'],
    '#maxlength' => 255,
    '#required' => TRUE,
  );
  $form['machine_name'] = array(
    '#title' => t('Machine readable name'),
    '#description' => t('The unique machine readable name for this format. This can only contain lowercase letters, numbers and underscores. The keyword %default is reserved for internal usage.', array('%default' => 'default' /* do not translate */)),
    '#type' => 'textfield',
    '#required' => TRUE,
    '#default_value' => $edit['machine_name'],
  );
  $form['format'] = array(
    '#type' => 'textfield',
    '#title' => t('Format'),
    '#default_value' => $edit['format'],
    '#maxlength' => 255,
    '#required' => TRUE,
  );

  $form['format_help'] = array(
    '#type' => 'fieldset',
    '#title' => t('Format string help'),
    '#collapsible' => TRUE,
    '#collapsed' => TRUE,
  );
  $form['format_help']['help'] = array(
    '#value' => theme('name_format_parameter_help'),
  );

  $form['submit'] = array(
    '#type' => 'submit',
    '#value' => t('Save'),
  );
  if (!empty($edit['ncfid'])) {
    $form['delete'] = array(
      '#type' => 'submit',
      '#value' => t('Delete'));
  }
  return $form;
}


function name_custom_formats_form_validate($form, &$form_state) {
  $values = $form_state['values'];
  // Ensure that the name is unique
  if (empty($values['ncfid'])) {
    $count = db_result(db_query("SELECT count(*) FROM {name_custom_format} WHERE name = '%s'", $values['name']));
    $mcount = db_result(db_query("SELECT count(*) FROM {name_custom_format} WHERE machine_name = '%s'", $values['machine_name']));
  }
  else {
    $count = db_result(db_query("SELECT count(*) FROM {name_custom_format} WHERE name = '%s' AND ncfid <> %d", $values['name'], $values['ncfid']));
    $mcount = db_result(db_query("SELECT count(*) FROM {name_custom_format} WHERE machine_name = '%s' AND ncfid <> %d", $values['machine_name'], $values['ncfid']));
  }
  if ($count) {
    form_set_error('name', t('The name you have chosen is already in use.'));
  }
  if ($mcount) {
    form_set_error('machine_name', t('The machine readable name you have chosen is already in use.'));
  }
  elseif ($values['machine_name'] == 'default') {
    form_set_error('machine_name', t('The machine readable name you have chosen is reserved.'));
  }

  // Parse the string for un-matched backets.
  // TODO
//  if ($format = $values['format']) {
//    $format = _name_custom_formats_form_validate_format(str_replace('\\\\', "\t", $format));
//    $format = str_replace(array('\\(', '\\)'), array('', ''), $format);
//    if (strpos($format, '(') !== FALSE || strpos($format, ')')) {
//      // Just a warning.
//      drupal_set_message(t('There was one or more un-matched and un-escaped brackets in the format string %format.', array('%format' => $values['format'])), 'warning');
//    }
//  }
}
function name_custom_formats_form_submit($form, &$form_state) {
  $values = $form_state['values'];
  if (empty($values['ncfid'])) {
    drupal_write_record('name_custom_format', $values);
    $message = 'Custom format %name has been created.';
  }
  else {
    drupal_write_record('name_custom_format', $values, 'ncfid');
    $message = 'Custom format %name has been updated.';
  }
  drupal_set_message(t($message, array('%name' => $values['name'])));

  $form_state['redirect'] = 'admin/settings/name';
}

/**
 * Page to edit a custom format.
 */
function name_custom_format_edit($ncfid) {
  if ((isset($_POST['op']) && $_POST['op'] == t('Delete')) || isset($_POST['confirm'])) {
    return drupal_get_form('name_custom_format_delete_form', $ncfid);
  }

  if ($name = db_fetch_array(db_query("SELECT * FROM {name_custom_format} WHERE ncfid = %d", $ncfid))) {
    return drupal_get_form('name_custom_formats_form', $name);
  }
  drupal_not_found();
}

function name_custom_format_delete_form($form_state, $ncfid) {
  $name = db_fetch_array(db_query("SELECT * FROM {name_custom_format} WHERE ncfid = %d", $ncfid));

  if (!$name) {
    drupal_set_message(t('The custom format could not be found.'), 'error');
    drupal_goto('admin/settings/name');
  }
  $form = array();
  $form['ncfid'] = array('#type' => 'value', '#value' => $name['ncfid']);
  $form['#name'] = $name;
  return confirm_form(
    $form,
    t('Are you sure you want to delete the custom format %name ("%format")?',
      array('%name' => $name['name'], '%format' => $name['format'])
    ),
    'admin/settings/name',
    t('This action cannot be undone.'),
    t('Delete'),  t('Cancel')
  );
}

function name_custom_format_delete_form_submit($form, &$form_state) {
  $ncfid = (int) $form_state['values']['ncfid'];
  db_query("DELETE FROM {name_custom_format} WHERE ncfid = %d", $ncfid);
  drupal_set_message(t('The custom name format %name was deleted.', array('%name' => $form['#name']['name'])));
  $form_state['redirect'] = 'admin/settings/name';
}
