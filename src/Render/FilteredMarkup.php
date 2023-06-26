<?php

namespace Drupal\memcache_status\Render;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Component\Render\MarkupTrait;

/**
 * Defines an object that passes safe strings.
 *
 * This object should only be constructed with a known safe string. If there is
 * any risk that the string contains user-entered data that has not been
 * filtered first, it must not be used.
 *
 * @see \Drupal\Core\Render\Markup
 */
final class FilteredMarkup implements MarkupInterface, \Countable {
  use MarkupTrait;

}
