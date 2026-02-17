<?php

namespace Drupal\raven\Event;

use Drupal\Component\EventDispatcher\Event;

/**
 * Event for altering Sentry logs attributes.
 */
class AttributesAlter extends Event {

  /**
   * Create a new AttributesAlter event.
   *
   * @param mixed[] $attributes
   *   Sentry log attributes.
   * @param mixed[] $context
   *   Log context.
   */
  public function __construct(
    public array &$attributes,
    public array $context,
  ) {
  }

}
