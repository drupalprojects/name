<?php

namespace Drupal\name\Tests;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Tests\UnitTestCase;
use Drupal\name\NameFormatParser;

/**
 * Tests the name formatter.
 *
 * @group name
 */
class NameFormatParserTest extends UnitTestCase {

  /**
   * The name format parser.
   *
   * @var \Drupal\name\NameFormatParser
   */
  protected $parser;

  /**
   * {@inheritDoc}
   */
  protected function setUp() {
    parent::setUp();

    $config_factory = $this->getConfigFactoryStub(['name.settings' => ['sep1' => ', ', 'sep2' => ' ', 'sep3' => '']]);
    $container = new ContainerBuilder();
    $container->set('config.factory', $config_factory);
    $container->set('string_translation', $this->getStringTranslationStub());
    \Drupal::setContainer($container);

    $this->parser = new NameFormatParser($config_factory);
  }

  /**
   * {@inheritDoc}
   */
  public static function getInfo() {
    return [
      'name' => 'NameFormatterParser Test',
      'description' => 'Test NameFormatParser',
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

    foreach ($this->names() as $dataSet) {
      foreach ($dataSet['tests'] as $pattern => $expected) {
        $data[] = [
          $dataSet['components'],
          $pattern,
          $expected,
        ];
      }
    }

    return $data;
  }

  /**
   * Block Test NameFormatParser::parse
   *
   * @dataProvider patternDataProvider
   */
  public function testParser($components, $pattern, $expected) {
    if ($this->parser) {
      $settings = [
        'sep1' => ' ',
        'sep2' => ', ',
        'sep3' => '',
      ];

      $formatted = $this->parser->parse($components, $pattern, $settings);
      $this->assertEquals($expected, $formatted);
    }
  }

  /**
   * Helper function to provide data for testParser.
   *
   * @return array
   */
  protected function names() {
    return [
      'given' => [
        'components' => ['given' => 'John'],
        'tests' => [
          // Test that only the given name creates a entry.
          // Title.
          't' => '',
          // Given name.
          'g' => 'John',
          // Escaped letter.
          '\g' => 'g',
          // Middle name(s).
          'm' => '',
          // Family name.
          'f' => '',
          // Credentials.
          'c' => '',
          // Generational suffix.
          's' => '',
          // First letter given.
          'x' => 'J',
          // First letter middle.
          'y' => '',
          // First letter family.
          'z' => '',
          // Either the given or family name. Given name is given preference.
          'e' => 'John',
          // Either the given or family name. Family name is given preference.
          'E' => 'John',
          // Combination tests.
          // Using a single space.
          'g f' => 'John ',
          // Separator 1.
          'gif' => 'John ',
          // Separator 2.
          'gjf' => 'John, ',
          // Separator 3.
          'gkf' => 'John',
          'f g' => ' John',
          'fig' => ' John',
          'fjg' => ', John',
          'fkg' => 'John',
          't g t' => ' John ',
          'tigit' => ' John ',
          'tjgjt' => ', John, ',
          'tkgkt' => 'John',
          // Modifier entries.
          // To lowercase.
          'Lg' => 'john',
          // To uppercase.
          'Ug' => 'JOHN',
          // First letter to uppercase.
          'Fg' => 'John',
          // First letter of all words to uppercase.
          'Gg' => 'John',
          // Lowercase, first letter to uppercase.
          'LF(g)' => 'John',
          // Lowercase, first letter of all words to uppercase.
          'LG(g)' => 'John',
          // Lowercase, first letter to uppercase.
          'LFg' => 'John',
          // Lowercase, first letter of all words to uppercase.
          'LGg' => 'John',
          // Trims whitespace around the next token.
          'Tg' => 'John',
          // @todo: assess the old check_plain run on code test / token.
          'Sg' => 'John',
          // Conditional entries.
          // Brackets.
          '(((g)))' => 'John',
          // Brackets - mismatched.
          '(g))()(' => 'John)(',
          // Insert the token if both the surrounding tokens are not empty.
          'g+ f' => 'John',
          // Insert the token, if and only if the next token after it is not empty.
          'g= f' => 'John',
          // Skip the token, if and only if the next token after it is not empty.
          'g^ f' => 'John ',
          // Uses only the first one.
          's|c|g|m|f|t' => 'John',
          // Uses the previous token unless empty, otherwise it uses this token.
          'g|f' => 'John',
          // Real world examples.
          // Full name with a comma-space before credentials.
          'L(t= g= m= f= s=,(= c))' => ' john',
          // Full name with a comma-space before credentials. ucfirst does not work on a whitespace.
          'TS(LF(t= g= m= f= s)=,(= c))' => 'john',
          // Full name with a comma-space before credentials.
          'L(t+ g+ m+ f+ s+,(= c))' => 'john',
          // Full name with a comma-space before credentials.
          'TS(LF(t+ g+ m+ f+ s)+,(= c))' => 'John',
        ],
      ],
      'full' => [
        'components' => [
          'title' => 'MR.',
          'given' => 'JoHn',
          'middle' => 'pEter',
          'family' => 'dOE',
          'generational' => 'sR',
          'credentials' => 'b.Sc, pHd',
          'preferred' => 'peTe',
        ],
        // Tests "MR. JoHn pEter dOE sR b.Sc, pHd".
        'tests' => [
          // Test that only the given name creates a entry.
          // Title.
          't' => 'MR.',
          // Given name.
          'g' => 'JoHn',
          // Preferred name.
          'p' => 'peTe',
          // Middle name(s).
          'm' => 'pEter',
          // Family name.
          'f' => 'dOE',
          // Credentials.
          'c' => 'b.Sc, pHd',
          // Generational suffix.
          's' => 'sR',
          // First preferred given.
          'x' => 'p',
          // First letter given.
          'x' => 'J',
          // First letter middle.
          'y' => 'p',
          // First letter family.
          'z' => 'd',
          // Either the preferred or family name. Preferred name is given preference.
          'd' => 'peTe',
          // Either the preferred or family name. Family name is given preference.
          'D' => 'dOE',
          // Either the given or family name. Given name is given preference.
          'e' => 'JoHn',
          // Either the given or family name. Family name is given preference.
          'E' => 'dOE',
          // Combination tests.
          // Using a single space.
          'g f' => 'JoHn dOE',
          // Using a single space with preferred.
          'p f' => 'peTe dOE',
          // Separator 1.
          'gif' => 'JoHn dOE',
          // Separator 2.
          'gjf' => 'JoHn, dOE',
          // Separator 3.
          'gkf' => 'JoHndOE',
          'f g' => 'dOE JoHn',
          'fig' => 'dOE JoHn',
          'fjg' => 'dOE, JoHn',
          'fkg' => 'dOEJoHn',
          't g t' => 'MR. JoHn MR.',
          'tigit' => 'MR. JoHn MR.',
          'tjgjt' => 'MR., JoHn, MR.',
          'tkgkt' => 'MR.JoHnMR.',
          // Modifier entries.
          // Lowercase.
          'L(t g m f s c)' => 'mr. john peter doe sr b.sc, phd',
          // Uppercase.
          'U(t g m f s c)' => 'MR. JOHN PETER DOE SR B.SC, PHD',
          // First letter to uppercase.
          'F(t g m f s c)' => 'MR. JoHn pEter dOE sR b.Sc, pHd',
          // First letter of all words to uppercase.
          'G(t g m f s c)' => 'MR. JoHn PEter DOE SR B.Sc, PHd',
          // First letter to uppercase.
          'LF(t g m f s c)' => 'Mr. john peter doe sr b.sc, phd',
          // First letter of all words to uppercase.
          'LG(t g m f s c)' => 'Mr. John Peter Doe Sr B.sc, Phd',
          // Trims whitespace around the next token.
          'T(t g m f s c)' => 'MR. JoHn pEter dOE sR b.Sc, pHd',
          // @todo: Assess the old check_plain run on code test / token.
          'S(t g m f s c)' => 'MR. JoHn pEter dOE sR b.Sc, pHd',
          // Conditional entries
          // Brackets.
          '(((t g m f s c)))' => 'MR. JoHn pEter dOE sR b.Sc, pHd',
          // Brackets - mismatched.
          '(t g m f s c))()(' => 'MR. JoHn pEter dOE sR b.Sc, pHd)(',
          // Insert the token, if and only if the next token after it is not empty.
          't= g= m= f= s= c' => 'MR. JoHn pEter dOE sR b.Sc, pHd',
          // Uses the previous token unless empty, otherwise it uses this token.
          'g|m|f' => 'JoHn',
          // Uses the previous token unless empty, otherwise it uses this token.
          'm|f|g' => 'pEter',
          // Uses only the first one.
          's|c|g|m|f|t' => 'sR',
          // Real world examples.
          // Full name with a comma-space before credentials.
          'L(t= g= m= f= s=,(= c))' => 'mr. john peter doe sr, b.sc, phd',
          // Full name with a comma-space before credentials.
          'TS(LG(t= g= m= f= s)=,LG(= c))' => 'Mr. John Peter Doe Sr, B.sc, Phd',
        ],
      ],
    ];
  }

}
