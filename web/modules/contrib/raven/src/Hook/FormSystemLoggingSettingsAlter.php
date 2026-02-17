<?php

namespace Drupal\raven\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\raven\Form\RavenConfigForm;

/**
 * Implements hook_form_system_logging_settings_alter().
 */
#[Hook('form_system_logging_settings_alter')]
class FormSystemLoggingSettingsAlter {

  /**
   * Implements hook_form_system_logging_settings_alter().
   *
   * @param mixed[] $form
   *   The system logging settings form.
   */
  public function __invoke(array &$form): void {
    RavenConfigForm::buildForm($form);
  }

}
