<?php

namespace Drupal\name\Tests;

use Drupal\Tests\UnitTestCase;
use Drupal\name\NameUnicodeExtras;

/**
 * Tests the unicode additional functions.
 *
 * @group name
 */
class NameUnicodeExtrasTest extends UnitTestCase {

  /**
   * {@inheritDoc}
   */
  public static function getInfo() {
    return [
      'name' => 'NameUnicodeExtras Test',
      'description' => 'Test NameUnicodeExtras additional functions',
      'group' => 'Name',
    ];
  }

  /**
   * Convert names() to PHPUnit compatible format.
   *
   * @return array
   *   An array of names.
   */
  public function patternDataProvider() {
    $data = [];

    foreach ($this->languageStrings() as $language => $info) {
      $data[] = [
        $language,
        $info['string'],
        $info['count'],
        $info['initials'],
      ];
    }

    return $data;
  }

  /**
   * Test NameUnicodeExtras functions.
   *
   * @dataProvider patternDataProvider
   */
  public function testNameUnicodeExtras($language, $text, $count, $expected_initials) {
    $parts = NameUnicodeExtras::explode($text);
    $initials = NameUnicodeExtras::initials($text);
    $this->assertEquals(count($parts), $count, 'Count parts for ' . $language . ' explode match.');
    $this->assertEquals($initials, $expected_initials, 'Initials for ' . $language . ' explode match. ' . $initials . ' ');
  }

  /**
   * Helper function to provide data for the tests.
   *
   * @return array
   *   A keyed array of test data in misc languages.
   */
  protected function languageStrings() {
    return [
      'english' => [
        'string' => 'A fat cat sat on the mat',
        'count' => 7,
        'initials' => 'AFCSOTM',
      ],
      'greek' => [
        'string' => 'Σὲ γνωρίζω ἀπὸ τὴν κόψη',
        'count' => 5,
        'initials' => 'Σγἀτκ',
      ],
      'georgian' => [
        'string' => 'გთხოვთ ახლავე გაიაროთ რეგისტრაცია',
        'count' => 4,
        'initials' => 'გაგრ',
      ],
      'russian' => [
        'string' => 'Зарегистрируйтесь сейчас на Десятую Международную Конференцию',
        'count' => 6,
        'initials' => 'ЗснДМК',
      ],
      'ethiopian' => [
        'string' => 'ሰማይ አይታረስ ንጉሥ አይከሰስ',
        'count' => 4,
        'initials' => 'ሰአንአ',
      ],
    ];
  }

}
