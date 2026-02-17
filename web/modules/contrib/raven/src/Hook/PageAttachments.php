<?php

namespace Drupal\raven\Hook;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Implements hook_page_attachments().
 */
#[Hook('page_attachments')]
class PageAttachments {

  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected AccountInterface $currentUser,
    #[Autowire('%kernel.environment%')]
    protected string $environment,
  ) {
  }

  /**
   * Implements hook_page_attachments().
   *
   * @param array{'#attached'?: array{drupalSettings?: array{raven?: array{options?: mixed[]}}}} $page
   *   The page attachments.
   */
  public function __invoke(array &$page): void {
    $config = $this->configFactory->get('raven.settings');
    if (!$config->get('javascript_error_handler') || !$this->currentUser->hasPermission('send javascript errors to sentry')) {
      return;
    }
    // Other modules can attach Sentry browser client options to the page.
    $page['#attached']['drupalSettings']['raven']['options']['dsn'] = $config->get('public_dsn');
    $page['#attached']['drupalSettings']['raven']['options']['environment'] = $config->get('environment') ?: $this->environment;
    if ($release = $config->get('release')) {
      $page['#attached']['drupalSettings']['raven']['options']['release'] = $release;
    }
    if (!\is_null($traces = $config->get('browser_traces_sample_rate'))) {
      $page['#attached']['drupalSettings']['raven']['options']['tracesSampleRate'] = $traces;
    }
    $page['#attached']['drupalSettings']['raven']['autoSessionTracking'] = $config->get('auto_session_tracking');
    $page['#attached']['drupalSettings']['raven']['options']['sendClientReports'] = $config->get('send_client_reports');
    $page['#attached']['drupalSettings']['raven']['options']['sendDefaultPii'] = (bool) $config->get('capture_user_ip');

    if ($config->get('tunnel')) {
      $url = Url::fromRoute('raven.tunnel');
      $page['#attached']['drupalSettings']['raven']['options']['tunnel'] = $url->toString();
    }
    $page['#attached']['drupalSettings']['raven']['showReportDialog'] = $config->get('show_report_dialog');
    // Other modules can attach browser tracing options to the page.
    $page['#attached']['drupalSettings']['raven']['browserTracingOptions']['enableInp'] = $config->get('send_inp_spans');
    if ($trace_propagation_targets = $config->get('trace_propagation_targets_frontend')) {
      $page['#attached']['drupalSettings']['raven']['tracePropagationTargets'] = $trace_propagation_targets;
    }
    $page['#attached']['library'][] = 'raven/raven';
    // Add meta tag placeholders for attaching traces to errors.
    $placeholders = str_split(Crypt::randomBytesBase64(36), 24);
    $page['#attached']['html_head'][] = [
      [
        '#tag' => 'meta',
        '#attributes' => [
          'name' => 'baggage',
          'content' => $placeholders[0],
        ],
        '#attached' => [
          'placeholders' => [
            $placeholders[0] => [
              '#lazy_builder' => ['raven.request_subscriber:getBaggage', []],
            ],
          ],
        ],
      ],
      'raven_baggage',
    ];
    $page['#attached']['html_head'][] = [
      [
        '#tag' => 'meta',
        '#attributes' => [
          'name' => 'sentry-trace',
          'content' => $placeholders[1],
        ],
        '#attached' => [
          'placeholders' => [
            $placeholders[1] => [
              '#lazy_builder' => ['raven.request_subscriber:getTraceparent', []],
            ],
          ],
        ],
      ],
      'raven_sentry_trace',
    ];
  }

}
