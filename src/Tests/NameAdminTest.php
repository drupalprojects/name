<?php

namespace Drupal\name\Tests;

use Drupal\Component\Utility\Html;
use Drupal\Core\Url;
use Drupal\name\Entity\NameFormat;
use Drupal\name\NameFormatInterface;

/**
 * Tests for the admin settings and custom format page.
 *
 * @group name
 */
class NameAdminTest extends NameTestBase {

  /**
   * The most basic test.
   */
  public function testAdminSettings() {
    // Default settings and system settings.
    $this->drupalLogin($this->admin_user);

    // The default installed formats.
    $this->drupalGet('admin/config/regional/name');

    $row_template = [
      'title'       => '//tbody/tr[{row}]/td[1]',
      'machine'     => '//tbody/tr[{row}]/td[2]',
      'pattern'     => '//tbody/tr[{row}]/td[3]',
      'formatted'   => '//tbody/tr[{row}]/td[4]',
      'edit'        => '//tbody/tr[{row}]/td[5]//li[contains(@class, "edit")]/a',
      'edit link'   => '//tbody/tr[{row}]/td[5]//li[contains(@class, "edit")]/a/@href',
      'delete'      => '//tbody/tr[{row}]/td[5]//li[contains(@class, "delete")]/a',
      'delete link' => '//tbody/tr[{row}]/td[5]//li[contains(@class, "delete")]/a/@href',
    ];
    $all_values = [
      1 => [
        'title href' => Url::fromRoute('name.settings')->toString(),
        'title' => t('Default'),
        'machine' => 'default',
        'pattern' => '((((t+ig)+im)+if)+is)+jc',
        'formatted' => 'Mr Joe John Peter Mark Doe Jnr., B.Sc., Ph.D. JOAN SUE DOE Prince',
      ],
      2 => [
        'title href' => Url::fromRoute('entity.name_format.edit_form', ['name_format' => 'family'])->toString(),
        'title' => t('Family'),
        'machine' => 'family',
        'pattern' => 'f',
        'formatted' => 'Doe DOE',
        'edit link' => Url::fromRoute('entity.name_format.edit_form', ['name_format' => 'family'])->toString(),
        'delete link' => Url::fromRoute('entity.name_format.delete_form', ['name_format' => 'family'])->toString(),
      ],
      3 => [
        'title href' => Url::fromRoute('entity.name_format.edit_form', ['name_format' => 'full'])->toString(),
        'title' => t('Full'),
        'machine' => 'full',
        'pattern' => '((((t+ig)+im)+if)+is)+jc',
        'formatted' => 'Mr Joe John Peter Mark Doe Jnr., B.Sc., Ph.D. JOAN SUE DOE Prince',
        'edit' => t('Edit'),
        'edit link' => Url::fromRoute('entity.name_format.edit_form', ['name_format' => 'full'])->toString(),
        'delete' => t('Delete'),
        'delete link' => Url::fromRoute('entity.name_format.delete_form', ['name_format' => 'full'])->toString(),
      ],
      4 => [
        'title href' => Url::fromRoute('entity.name_format.edit_form', ['name_format' => 'given'])->toString(),
        'title' => t('Given'),
        'machine' => 'given',
        'pattern' => 'g',
        'formatted' => 'Joe JOAN Prince',
        'edit' => t('Edit'),
        'edit link' => Url::fromRoute('entity.name_format.edit_form', ['name_format' => 'given'])->toString(),
        'delete' => t('Delete'),
        'delete link' => Url::fromRoute('entity.name_format.delete_form', ['name_format' => 'given'])->toString(),
      ],
      5 => [
        'title href' => Url::fromRoute('entity.name_format.edit_form', ['name_format' => 'short_full'])->toString(),
        'title' => t('Given Family'),
        'machine' => 'short_full',
        'pattern' => 'g+if',
        'formatted' => 'Joe Doe JOAN DOE Prince',
        'edit link' => Url::fromRoute('entity.name_format.edit_form', ['name_format' => 'short_full'])->toString(),
        'delete link' => Url::fromRoute('entity.name_format.delete_form', ['name_format' => 'short_full'])->toString(),
      ],
      6 => [
        'title href' => Url::fromRoute('entity.name_format.edit_form', ['name_format' => 'formal'])->toString(),
        'title' => t('Title Family'),
        'machine' => 'formal',
        'pattern' => 't+if',
        'formatted' => 'Mr Doe DOE',
        'edit link' => Url::fromRoute('entity.name_format.edit_form', ['name_format' => 'formal'])->toString(),
        'delete link' => Url::fromRoute('entity.name_format.delete_form', ['name_format' => 'formal'])->toString(),
      ],
    ];

    foreach ($all_values as $id => $row) {
      $this->assertRow($row, $row_template, $id);
    }

    // Load the name settings form.
    $this->drupalGet('admin/config/regional/name/settings');

    // Fieldset rendering check.
    $this->assertRaw('Format string help', 'Testing the help fieldgroup');

    $default_values = [
      'name_settings[default_format]' => 't+ig+im+if+is+kc',
      'name_settings[sep1]' => ' ',
      'name_settings[sep2]' => ', ',
      'name_settings[sep3]' => '',
    ];
    foreach ($default_values as $name => $value) {
      $this->assertField($name, $value);
    }
    // ID example.
    $this->assertFieldById('edit-name-settings-sep1', ' ', t('Sep 1 default value.'));
    $post_values = $default_values;
    $post_values['name_settings[default_format]'] = '';

    $this->drupalPostForm('admin/config/regional/name/settings', $post_values, t('Save configuration'));
    $this->assertText(t('Default format field is required.'));
    $post_values['name_settings[default_format]'] = '     ';
    $this->drupalPostForm('admin/config/regional/name/settings', $post_values, t('Save configuration'));
    $this->assertText(t('Default format field is required.'));

    $test_values = [
      'name_settings[default_format]' => 'c+ks+if+im+ig+t',
      'name_settings[sep1]' => '~',
      'name_settings[sep2]' => '^',
      'name_settings[sep3]' => '-',
    ];
    $this->drupalPostForm('admin/config/regional/name/settings', $test_values, t('Save configuration'));
    $this->assertText(t('The configuration options have been saved.'));

    foreach ($test_values as $name => $value) {
      $this->assertField($name, $value);
    }

    // Delete all existing formats.
    $formats = NameFormat::loadMultiple();
    array_walk($formats, function (NameFormatInterface $format) {
      if (!$format->isLocked()) {
        $format->delete();
      }
    });

    $this->drupalGet('admin/config/regional/name/add');
    $this->assertRaw('Format string help', 'Testing the help fieldgroup');
    $values = ['label' => '', 'id' => '', 'pattern' => ''];
    $this->drupalPostForm('admin/config/regional/name/add', $values, t('Save format'));
    foreach ([t('Name'), t('Machine-readable name'), t('Format')] as $title) {
      $this->assertText(t('@field field is required', ['@field' => $title]));
    }
    $values = [
      'label' => 'given',
      'id' => '1234567890abcdefghijklmnopqrstuvwxyz_',
      'pattern' => 'a',
    ];
    $this->drupalPostForm('admin/config/regional/name/add', $values, t('Save format'));
    $this->assertNoText(t('@field field is required', ['@field' => t('Format')]));
    $this->assertNoText(t('@field field is required', ['@field' => t('Machine-readable name')]));

    $values = ['label' => 'given', 'id' => '%&*(', 'pattern' => 'a'];
    $this->drupalPostForm('admin/config/regional/name/add', $values, t('Save format'));
    $this->assertText(t('The machine-readable name must contain only lowercase letters, numbers, and underscores.'));

    $values = ['label' => 'default', 'id' => 'default', 'pattern' => 'a'];
    $this->drupalPostForm('admin/config/regional/name/add', $values, t('Save format'));
    $this->assertText(t('The machine-readable name is already in use. It must be unique.'));

    $values = ['label' => 'Test', 'id' => 'test', 'pattern' => 'abc'];
    $this->drupalPostForm('admin/config/regional/name/add', $values, t('Save format'));
    $this->assertText(t('Name format Test added.'));

    $row = [
      'title href' => Url::fromRoute('entity.name_format.edit_form', ['name_format' => 'test'])->toString(),
      'title' => 'Test',
      'machine' => 'test',
      'pattern' => 'abc',
      'formatted' => 'abB.Sc., Ph.D. ab ab',
      'edit link' => Url::fromRoute('entity.name_format.edit_form', ['name_format' => 'test'])->toString(),
      'delete link' => Url::fromRoute('entity.name_format.delete_form', ['name_format' => 'test'])->toString(),
    ];
    $this->assertRow($row, $row_template, 3);

    $values = ['label' => 'new name', 'pattern' => 'f+g'];
    $this->drupalPostForm('admin/config/regional/name/manage/test', $values, t('Save format'));
    $this->assertText(t('Name format new name has been updated.'));

    $row = [
      'label' => $values['label'],
      'id' => 'test',
      'pattern' => $values['pattern'],
    ];
    $this->assertRow($row, $row_template, 3);

    $this->drupalGet('admin/config/regional/name/manage/60');
    $this->assertResponse(404);

    $this->drupalGet('admin/config/regional/name/manage/60/delete');
    $this->assertResponse(404);

    $this->drupalGet('admin/config/regional/name/manage/test/delete');
    $this->assertText(t('Are you sure you want to delete the custom format @title?', ['@title' => $values['label']]));

    $this->drupalPostForm(NULL, ['confirm' => 1], t('Delete'));
    $this->assertText(t('The name format @title has been deleted.', ['@title' => $values['label']]));
  }

  /**
   * Helper function to test a table cell via it's expected value.
   *
   * @param array $row
   *   Table rows to test.
   * @param array $row_template
   *   The parameters used for each row test.
   * @param int $id
   *   The row ID.
   */
  public function assertRow(array $row, array $row_template, $id) {
    foreach ($row as $cell_code => $value) {
      if (isset($row_template[$cell_code])) {
        $xpath = str_replace('{row}', $id, $row_template[$cell_code]);
        $raw_xpath = $this->xpath($xpath);
        if (!is_array($raw_xpath)) {
          $results = '__MISSING__';
        }
        else {
          $results = (string) current($raw_xpath);
        }
        $results = trim($results);
        // Check URLs with or without the ?destination= query parameter.
        $message = "Testing {$cell_code} on row {$id} using '{$xpath}' and expecting '" . Html::escape($value) . "', got '" . Html::escape($results) . "'.";
        if (strpos($row_template[$cell_code], '/a/@href')) {
          if ($results == $value || strpos($results, $value . '?destination=') === 0) {
            $this->pass($message);
          }
          else {
            $this->fail($message);
          }
        }
        else {
          $this->assertEqual($results, $value, $message);
        }
      }
    }
  }

}
