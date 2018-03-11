<?php

namespace Drupal\name;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Main class that formats a name from an array of components.
 *
 * @param array $name_components
 *   A keyed array of name components.
 *   These are: title, given, middle, family, generational and credentials.
 * @param string $format
 *   The string specifying what format to use.
 * @param array $settings
 *   A keyed array of additional parameters to pass into the function.
 *   Includes:
 *   - 'object' An object or array.
 *     This entity is used for Token module substitutions.
 *     Currently not used.
 *   - 'type' - A string.
 *     The entity identifier: node, user, etc
 */
class NameFormatParser {

  use StringTranslationTrait;

  /**
   * Flag to add markup around name components.
   *
   * @var bool
   */
  protected $markup = FALSE;

  /**
   * First separator.
   *
   * @var string
   */
  protected $sep1 = ' ';

  /**
   * Second separator.
   *
   * @var string
   */
  protected $sep2 = ', ';

  /**
   * Third separator.
   *
   * @var string
   */
  protected $sep3 = '';

  /**
   * Used to seperate words using the "b" and "B" modifiers.
   *
   * @var string
   */
  protected $boundaryRegExp = '/[\b,\s]/';

  /**
   * Parses a name component array into the given format.
   */
  public function parse($name_components, $format = '', array $settings = [], $tokens = NULL) {
    foreach (['sep1', 'sep2', 'sep3'] as $sep_key) {
      if (isset($settings[$sep_key])) {
        $this->{$sep_key} = (string) $settings[$sep_key];
      }
    }
    $this->markup = !empty($settings['markup']);
    if (isset($settings['boundary']) && strlen($settings['boundary'])) {
      $this->boundary = $settings['boundary'];
    }

    return $this->format($name_components, $format, $tokens);
  }

  /**
   * Formats an array of name components into the supplied format.
   */
  protected function format($name_components, $format = '', $tokens = NULL) {
    if (empty($format)) {
      return '';
    }

    if (!isset($tokens)) {
      $tokens = $this->generateTokens($name_components);
    }

    // Neutralise any escaped backslashes.
    $format = str_replace('\\\\', "\t", $format);

    $pieces = [];
    $modifiers = '';
    $conditions = '';
    for ($i = 0; $i < strlen($format); $i++) {
      $char = $format{$i};
      $last_char = ($i > 0) ? $format{$i - 1} : FALSE;

      // Handle escaped letters.
      if ($char == '\\') {
        continue;
      }
      if ($last_char == '\\') {
        $pieces[] = $this->addComponent($char, $modifiers, $conditions);
        continue;
      }

      switch ($char) {
        case 'L':
        case 'U':
        case 'F':
        case 'T':
        case 'S':
        case 'G':
        case 'B':
        case 'b':
          $modifiers .= $char;
          break;

        case '=':
        case '^':
        case '|':
        case '+':
        case '-':
        case '~':
          $conditions .= $char;
          break;

        case '(':
        case ')':
          $remaining_string = substr($format, $i);
          if ($char == '(' && $closing_bracket = $this->closingBracketPosition($remaining_string)) {
            $sub_string = $this->format($tokens, substr($format, $i + 1, $closing_bracket - 1), $tokens);

            // Increment the counter past the closing bracket.
            $i += $closing_bracket;
            $pieces[] = $this->addComponent($sub_string, $modifiers, $conditions);
          }
          else {
            // Unmatched, add it.
            $pieces[] = $this->addComponent($char, $modifiers, $conditions);
          }
          break;

        default:
          if (array_key_exists($char, $tokens)) {
            $char = $tokens[$char];
          }
          $pieces[] = $this->addComponent($char, $modifiers, $conditions);
          break;
      }
    }

    $parsed_pieces = [];
    for ($i = 0; $i < count($pieces); $i++) {
      $component = $pieces[$i]['value'];
      $conditions = $pieces[$i]['conditions'];

      $last_component = ($i > 0) ? $pieces[$i - 1]['value'] : FALSE;
      $next_component = ($i < count($pieces) - 1) ? $pieces[$i + 1]['value'] : FALSE;

      if (empty($conditions)) {
        $parsed_pieces[$i] = $component;
      }
      else {
        // Modifier: Conditional insertion. Insert if both the surrounding
        // tokens are not empty.
        if (strpos($conditions, '+') !== FALSE && !empty($last_component) && !empty($next_component)) {
          $parsed_pieces[$i] = $component;
        }

        // Modifier: Conditional insertion. Insert if the previous token is
        // not empty.
        if (strpos($conditions, '-') !== FALSE && !empty($last_component)) {
          $parsed_pieces[$i] = $component;
        }

        // Modifier: Conditional insertion. Insert if the previous token is
        // empty.
        if (strpos($conditions, '~') !== FALSE && empty($last_component)) {
          $parsed_pieces[$i] = $component;
        }

        // Modifier: Insert the token if the next token is empty.
        if (strpos($conditions, '^') !== FALSE && empty($next_component)) {
          $parsed_pieces[$i] = $component;
        }

        // Modifier: Insert the token if the next token is not empty.
        // This overrides the above two settings.
        if (strpos($conditions, '=') !== FALSE && !empty($next_component)) {
          $parsed_pieces[$i] = $component;
        }

        // Modifier: Conditional insertion. Uses the previous token unless
        // empty, otherwise insert this token.
        if (strpos($conditions, '|') !== FALSE) {
          if (empty($last_component)) {
            $parsed_pieces[$i] = $component;
          }
          else {
            unset($parsed_pieces[$i]);
          }
        }

      }
    }
    return str_replace('\\\\', "\t", implode('', $parsed_pieces));
  }

  /**
   * Adds a component.
   */
  protected function addComponent($string, &$modifiers = '', &$conditions = '') {
    $string = $this->applyModifiers($string, $modifiers);
    $piece = [
      'value' => $string,
      'conditions' => $conditions,
    ];
    $conditions = '';
    $modifiers = '';
    return $piece;
  }

  /**
   * Applies the specified modifiers to the string.
   */
  protected function applyModifiers($string, $modifiers) {
    if (!is_null($string) || strlen($string)) {
      if ($modifiers) {
        $prefix = '';
        $suffix = '';
        if (preg_match('/^(<span[^>]*>)(.*)(<\/span>)$/i', $string, $matches)) {
          $prefix = $matches[1];
          $string = $matches[2];
          $suffix = $matches[3];
        }

        for ($j = 0; $j < strlen($modifiers); $j++) {
          switch ($modifiers{$j}) {
            case 'L':
              $string = Unicode::strtolower($string);
              break;

            case 'U':
              $string = Unicode::strtoupper($string);
              break;

            case 'F':
              $string = Unicode::ucfirst($string);
              break;

            case 'G':
              $string = UniCode::ucwords($string);
              break;

            case 'T':
              $string = trim($string);
              break;

            case 'S':
              $string = Html::escape($string);
              break;

            case 'B':
              $parts = preg_split($this->boundaryRegExp, $string);
              $string = (string) array_shift($parts);
              break;

            case 'b':
              $parts = preg_split($this->boundaryRegExp, $string);
              $string = (string) array_pop($parts);
              break;

          }
        }
        $string = $prefix . $string . $suffix;
      }
    }
    return $string;
  }

  /**
   * Helper function to put out the first matched bracket position.
   *
   * Accepts strings in the format, ^ marks the matched bracket.
   *   '(xxx^)xxx(xxxx)xxxx' or '(xxx(xxx(xxxx))xxx^)'
   */
  protected function closingBracketPosition($string) {
    // Simplify the string by removing escaped brackets.
    $depth = 0;
    $string = str_replace(['\(', '\)'], ['__', '__'], $string);
    for ($i = 0; $i < strlen($string); $i++) {
      $char = $string{$i};
      if ($char == '(') {
        $depth++;
      }
      elseif ($char == ')') {
        $depth--;
        if ($depth == 0) {
          return $i;
        }
      }
    }
    return FALSE;
  }

  /**
   * Generates the tokens from the name item.
   */
  protected function generateTokens($name_components) {
    $name_components = (array) $name_components;
    $name_components += [
      'title' => '',
      'given' => '',
      'middle' => '',
      'family' => '',
      'credentials' => '',
      'generational' => '',
      'preferred' => '',
      'alternative' => '',
    ];
    $tokens = [
      't' => $this->renderComponent($name_components['title'], 'title'),
      'g' => $this->renderComponent($name_components['given'], 'given'),
      'p' => $this->renderFirstComponent([$name_components['preferred'], $name_components['given']], 'given'),
      'q' => $this->renderComponent($name_components['preferred'], 'preferred'),
      'm' => $this->renderComponent($name_components['middle'], 'middle'),
      'f' => $this->renderComponent($name_components['family'], 'family'),
      'c' => $this->renderComponent($name_components['credentials'], 'credentials'),
      'a' => $this->renderComponent($name_components['alternative'], 'alternative'),
      's' => $this->renderComponent($name_components['generational'], 'generational'),
      'v' => $this->renderComponent($name_components['preferred'], 'preferred', 'initial'),
      'w' => $this->renderFirstComponent([$name_components['preferred'], $name_components['given']], 'given', 'initial'),
      'x' => $this->renderComponent($name_components['given'], 'given', 'initial'),
      'y' => $this->renderComponent($name_components['middle'], 'middle', 'initial'),
      'z' => $this->renderComponent($name_components['family'], 'family', 'initial'),
      'A' => $this->renderComponent($name_components['alternative'], 'alternative', 'initial'),
      'I' => $this->renderComponent($name_components['given'] . ' ' . $name_components['family'], 'initials', 'initials'),
      'J' => $this->renderComponent($name_components['given'] . ' ' . $name_components['middle'] . ' ' . $name_components['family'], 'initials', 'initials'),
      'K' => $this->renderComponent($name_components['given'], 'initials', 'initials'),
      'M' => $this->renderComponent($name_components['given'] . ' ' . $name_components['middle'], 'initials', 'initials'),
      'i' => $this->sep1,
      'j' => $this->sep2,
      'k' => $this->sep3,
    ];
    $preferred = $tokens['p'];
    $given = $tokens['g'];
    $family = $tokens['f'];
    if ($preferred || $family) {
      $tokens += [
        'd' => $preferred ? $preferred : $family,
        'D' => $family ? $family : $preferred,
      ];
    }
    if ($given || $family) {
      $tokens += [
        'e' => $given ? $given : $family,
        'E' => $family ? $family : $given,
      ];
    }
    $tokens += [
      'd' => NULL,
      'D' => NULL,
      'e' => NULL,
      'E' => NULL,
    ];
    return $tokens;
  }

  /**
   * Finds and renders the first renderable name component value.
   *
   * This function does not by default sanitize the output unless the markup
   * flag is set. If this is set, it runs the component through check_plain() and
   * wraps the component in a span with the component name set as the class.
   */
  protected function renderFirstComponent(array $values, $component_key, $modifier = NULL) {
    foreach ($values as $value) {
      $output = $this->renderComponent($value, $component_key, $modifier);
      if (isset($output) && strlen($output)) {
        return $output;
      }
    }

    return NULL;
  }

  /**
   * Renders a name component value.
   *
   * This function does not by default sanitize the output unless the markup
   * flag is set. If set, it runs the component through Html::escape() and
   * wraps the component in a span with the component name set as the class.
   */
  protected function renderComponent($value, $component_key, $modifier = NULL) {
    if (empty($value) || !Unicode::strlen($value)) {
      return NULL;
    }
    switch ($modifier) {
      // First letter first word.
      case 'initial':
        $value = Unicode::substr($value, 0, 1);
        break;

      // First letter all words.
      case 'initials':
        // First letter all words.
        $value = NameUnicodeExtras::initials($value);
        break;

    }
    if ($this->markup) {
      return '<span class="' . Html::escape($component_key) . '">' . Html::escape($value) . '</span>';
    }
    return $value;
  }

  /**
   * Supported tokens.
   *
   * @param bool $describe
   *   Appends the description of the letter to the description.
   *
   * @return string[]
   *   An array of strings keyed by the token.
   */
  public function tokenHelp($describe = TRUE) {
    $tokens = [
      't' => $this->t('Title.'),
      'p' => $this->t('Preferred name, use given name if not set.'),
      'q' => $this->t('Preferred name.'),
      'g' => $this->t('Given name.'),
      'm' => $this->t('Middle name(s).'),
      'f' => $this->t('Family name.'),
      'c' => $this->t('Credentials.'),
      's' => $this->t('Generational suffix.'),
      'a' => $this->t('Alternative value.'),
      'v' => $this->t('First letter preferred name.'),
      'w' => $this->t('First letter preferred or given name.'),
      'x' => $this->t('First letter given.'),
      'y' => $this->t('First letter middle.'),
      'z' => $this->t('First letter family.'),
      'A' => $this->t('First letter of alternative value.'),
      'I' => $this->t('Initials (all) from given and family.'),
      'J' => $this->t('Initials (all) from given, middle and family.'),
      'K' => $this->t('Initials (all) from given.'),
      'M' => $this->t('Initials (all) from given and middle.'),
      'd' => $this->t('Conditional: Either the preferred given or family name. Preferred name is given preference over given or family names.'),
      'D' => $this->t('Conditional: Either the preferred given or family name. Family name is given preference over preferred or given names.'),
      'e' => $this->t('Conditional: Either the given or family name. Given name is given preference.'),
      'E' => $this->t('Conditional: Either the given or family name. Family name is given preference.'),
      'i' => $this->t('Separator 1.'),
      'j' => $this->t('Separator 2.'),
      'k' => $this->t('Separator 3.'),
      '\\' => $this->t('You can prevent a character in the format string from being expanded by escaping it with a preceding backslash.'),
      'L' => $this->t('Modifier: Converts the next token to all lowercase.'),
      'U' => $this->t('Modifier: Converts the next token to all uppercase.'),
      'F' => $this->t('Modifier: Converts the first letter to uppercase.'),
      'G' => $this->t('Modifier: Converts the first letter of ALL words to uppercase.'),
      'T' => $this->t('Modifier: Trims whitespace around the next token.'),
      'S' => $this->t('Modifier: Ensures that the next token is safe for the display.'),
      'B' => $this->t('Modifier: Use the first word of the next token.'),
      'b' => $this->t('Modifier: Use the last word of the next token.'),
      '+' => $this->t('Conditional: Insert the token if both the surrounding tokens are not empty.'),
      '-' => $this->t('Conditional: Insert the token if the previous token is not empty.'),
      '~' => $this->t('Conditional: Insert the token if the previous token is empty.'),
      '=' => $this->t('Conditional: Insert the token if the next token is not empty.'),
      '^' => $this->t('Conditional: Insert the token if the next token is empty.'),
      '|' => $this->t('Conditional: Uses the previous token unless empty, otherwise it uses this token.'),
      '(' => $this->t('Group: Start of token grouping.'),
      ')' => $this->t('Group: End of token grouping.'),
    ];

    if ($describe) {
      foreach ($tokens as $letter => $description) {
        if (preg_match('/^[a-z]+$/', $letter)) {
          $tokens[$letter] = $this->t('@description<br><small>(lowercase @letter)</small>', [
            '@description' => $description,
            '@letter' => Unicode::strtoupper($letter),
          ]);
        }
        elseif (preg_match('/^[A-Z]+$/', $letter)) {
          $tokens[$letter] = $this->t('@description<br><small>(uppercase @letter)</small>', [
            '@description' => $description,
            '@letter' => Unicode::strtoupper($letter),
          ]);
        }
      }
    }

    // Placeholders for token support insertion on the [object / key | entity / bundle].
    $unsupported_tokens = [
      '1' => $this->t('Token placeholder 1.'),
      '2' => $this->t('Token placeholder 2.'),
      '3' => $this->t('Token placeholder 3.'),
      '4' => $this->t('Token placeholder 4.'),
      '5' => $this->t('Token placeholder 5.'),
      '6' => $this->t('Token placeholder 6.'),
      '7' => $this->t('Token placeholder 7.'),
      '8' => $this->t('Token placeholder 8.'),
      '9' => $this->t('Token placeholder 9.'),
    ];

    return $tokens;
  }

  /**
   * Helper function to provide name format token help.
   *
   * @return array
   *   A renderable array of tokens in a details element..
   */
  public function renderableTokenHelp() {
    return [
      '#type' => 'details',
      '#title' => t('Format string help'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
      '#parents' => [],
      'format_parameters' => [
        '#theme' => 'name_format_parameter_help',
        '#tokens' => $this->tokenHelp(),
      ],
    ];
  }

}
