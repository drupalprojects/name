<?php

/**
 * @file
 * Post update functions for Name.
 */

/**
 * Adds the default list format.
 */
function name_post_update_create_name_list_format() {
  $default_list = \Drupal::entityManager()->getStorage('name_list_format')->load('default');
  if ($default_list) {
    if (!$default_list->locked) {
      $default_list->locked = TRUE;
      $default_list->save();
      drupal_set_message(t('Default name list format was set to locked.'));
    }
    else {
      drupal_set_message(t('Nothing required to action.'));
    }
  }
  else {
    $default_list = entity_create('name_list_format', [
      'id' => 'default',
      'label' => 'Default',
      'locked' => true,
      'status' => true,
      'delimiter' => ', ',
      'and' => 'text',
      'delimiter_precedes_last' => 'never',
      'el_al_min' => 3,
      'el_al_first' => 1,
    ]);
    $default_list->save();
    drupal_set_message(t('Default name list format was added.'));
  }
  // @todo: maybe parse all defined field settings to discover all variations?
}
