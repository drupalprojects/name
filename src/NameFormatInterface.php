<?php

namespace Drupal\name;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Provides an interface defining a name format.
 */
interface NameFormatInterface extends ConfigEntityInterface {

  /**
   * Getter for the pattern.
   *
   * @return string
   *   The pattern or an empty string.
   */
  public function getPattern($type);

  /**
   * Setter for the pattern.
   *
   * @param string $pattern
   *   The pattern.
   * @param string $type
   *   The format type.
   */
  public function setPattern($pattern, $type);

  /**
   * Determines if this name format is locked.
   *
   * @return bool
   *   TRUE if the name format is locked, FALSE otherwise.
   */
  public function isLocked();
}
