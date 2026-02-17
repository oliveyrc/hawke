<?php

namespace Drupal\raven\Plugin\CspReportingHandler;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
use Drupal\csp\Csp;
use Drupal\csp\Plugin\ReportingHandlerBase;
use Sentry\Dsn;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * CSP Reporting Plugin for a Sentry endpoint.
 *
 * @CspReportingHandler(
 *   id = "raven",
 *   label = "Sentry",
 *   description = @Translation("Reports will be sent to Sentry."),
 * )
 */
class Raven extends ReportingHandlerBase implements ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   *
   * @phpstan-ignore missingType.iterableValue
   */
  final public function __construct(array $configuration, $plugin_id, $plugin_definition, protected ConfigFactoryInterface $configFactory, protected string $environment) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-ignore missingType.iterableValue
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $environment = $container->getParameter('kernel.environment');
    if (!\is_string($environment)) {
      throw new \UnexpectedValueException('The kernel.environment parameter should be a string.');
    }
    return new static(
      $configuration,
      $plugin_id,
      // @phpstan-ignore argument.type
      $plugin_definition,
      $container->get('config.factory'),
      $environment,
    );
  }

  /**
   * {@inheritdoc}
   */
  public function alterPolicy(Csp $policy): void {
    $config = $this->configFactory->get('raven.settings');
    $dsn = $config->get('public_dsn');
    if (!\is_string($dsn)) {
      return;
    }
    try {
      $dsn = Dsn::createFromString($dsn);
    }
    catch (\InvalidArgumentException $e) {
      // Raven is incorrectly configured.
      return;
    }
    $query['sentry_environment'] = $config->get('environment') ?: $this->environment;
    if ($release = $config->get('release')) {
      $query['sentry_release'] = $release;
    }
    $policy->setDirective('report-uri', Url::fromUri($dsn->getCspReportEndpointUrl(), ['query' => $query])->toString());
  }

}
