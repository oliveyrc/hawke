<?php

namespace Drupal\raven\Hook;

use Drupal\Core\Access\AccessManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;

/**
 * Implements hook_help().
 */
#[Hook('help')]
class Help {

  use StringTranslationTrait;

  public function __construct(protected AccessManagerInterface $accessManager) {
  }

  /**
   * Implements hook_help().
   *
   * @return mixed[]
   *   Renderable array.
   */
  public function __invoke(?string $route_name): array {
    $output = [];
    if ($route_name === 'help.page.raven') {
      $output[] = $this->t('Raven module integrates with <a href="https://sentry.io/" rel="noreferrer">Sentry</a>, an open-source application monitoring and error tracking platform.');
      if ($this->accessManager->checkNamedRoute('system.logging_settings')) {
        $output[] = $this->t('Configuration');
        $output[] = $this->t('Configure your Sentry settings at the <a href=":url">logging and errors configuration page</a>.', [
          ':url' => Url::fromRoute('system.logging_settings', [], ['fragment' => 'edit-raven'])->toString(),
        ]);
      }
      $output[] = $this->t('Documentation');
      $output[] = $this->t('Raven module documentation is available in the <a href="https://git.drupalcode.org/project/raven/-/blob/7.x/README.md" rel="noreferrer">README</a>. See also documentation for the <a href="https://docs.sentry.io/platforms/javascript/" rel="noreferrer">Sentry JavaScript SDK</a> and the <a href="https://docs.sentry.io/platforms/php/" rel="noreferrer">Sentry PHP SDK</a>.');
      $output[] = $this->t('Support');
      $output[] = $this->t('Raven module is not affiliated with Sentry; it\'s supported by the community (that means you :). Visit the <a href="https://www.drupal.org/project/issues/raven" rel="noreferrer">issue queue</a> to file bug reports, feature requests and support requests.');
      array_walk($output, function (TranslatableMarkup &$value, int $key): void {
        $value = [
          '#type' => 'html_tag',
          '#tag' => $key & 1 ? 'h3' : 'p',
          '#value' => $value,
        ];
      });
    }
    return $output;
  }

}
