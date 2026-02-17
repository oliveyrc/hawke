<?php

namespace Drupal\raven\Drush\Commands;

use Consolidation\AnnotatedCommand\CommandData;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\raven\Logger\RavenInterface;
use Drupal\raven\Tracing\TracingTrait;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Drush\Drush;
use Psr\Container\ContainerInterface as DrushContainer;
use Sentry\Logs\LogLevel;
use Sentry\SentrySdk;
use Sentry\Severity;
use Sentry\Tracing\SpanContext;
use Sentry\Tracing\SpanStatus;
use Sentry\Tracing\TransactionContext;
use Sentry\Tracing\TransactionSource;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Provides Drush commands for Raven module.
 */
class RavenCommands extends DrushCommands {

  use TracingTrait;

  final public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected EventDispatcherInterface $drushEventDispatcher,
    protected EventDispatcherInterface $eventDispatcher,
    protected RavenInterface $ravenLogger,
    protected TimeInterface $time,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, ?DrushContainer $drush = NULL): static {
    return new static(
      $container->get('config.factory'),
      $drush ? $drush->get('eventDispatcher') : Drush::service('eventDispatcher'),
      $container->get('event_dispatcher'),
      $container->get('logger.raven'),
      $container->get('datetime.time'),
    );
  }

  /**
   * Sets up Drush error handling and performance tracing.
   *
   * @hook pre-command *
   */
  public function preCommand(CommandData $commandData): void {
    if (!$this->ravenLogger->getClient()) {
      return;
    }
    $config = $this->configFactory->get('raven.settings');
    // Add Drush console error event listener.
    if ($config->get('drush_error_handler')) {
      $this->drushEventDispatcher->addListener(ConsoleEvents::ERROR, [
        $this,
        'onConsoleError',
      ]);
    }
    if (!$config->get('drush_tracing')) {
      return;
    }
    $this->drushEventDispatcher->addListener(ConsoleEvents::TERMINATE, [
      $this,
      'onConsoleTerminate',
    ], -222);
    $transactionContext = TransactionContext::make()
      ->setName('drush ' . $commandData->input()->getArgument('command'))
      ->setSource(TransactionSource::task())
      ->setOrigin('auto.console')
      ->setOp('console.command');
    $this->startTransaction($transactionContext);
  }

  /**
   * Console error event listener.
   */
  public function onConsoleError(ConsoleErrorEvent $event): void {
    \Sentry\captureException($event->getError());
  }

  /**
   * Console terminate event listener.
   */
  public function onConsoleTerminate(ConsoleTerminateEvent $event): void {
    \Sentry\logger()->flush();
    if (!$this->transaction) {
      return;
    }
    $this->transaction->setStatus($event->getExitCode() ? SpanStatus::internalError() : SpanStatus::ok())
      ->setTags(['drush.command.exit_code' => (string) $event->getExitCode()])
      ->finish();
  }

  /**
   * Send a test message to Sentry.
   *
   * @param string $message
   *   The message text.
   * @param mixed[] $options
   *   An associative array of options.
   */
  #[CLI\Argument(name: 'message', description: 'The message text.')]
  #[CLI\Command(name: 'raven:captureMessage')]
  #[CLI\Option(name: 'level', description: 'The message level (debug, info, warning, error, fatal).')]
  #[CLI\Usage(name: 'drush raven:captureMessage', description: 'Send test message to Sentry.')]
  #[CLI\Usage(name: 'drush raven:captureMessage --level=error', description: 'Send error message to Sentry.')]
  #[CLI\Usage(name: "drush raven:captureMessage 'Mic check.'", description: 'Send "Mic check" message to Sentry.')]
  public function captureMessage(
    string $message = 'Test message from Drush.',
    array $options = [
      'level' => 'info',
    ],
  ): void {
    $logger = $this->logger();
    // Force invalid configuration to throw an exception.
    $client = $this->ravenLogger->getClient(FALSE, TRUE);
    if (!$client) {
      throw new \Exception('Sentry client not available.');
    }
    elseif (!$client->getOptions()->getDsn() && $logger) {
      $logger->warning(dt('Sentry client key is not configured. No events will be sent to Sentry.'));
    }

    if (!\is_string($options['level'])) {
      throw new \InvalidArgumentException('Level must be a string.');
    }
    $severity = new Severity($options['level']);

    $start = microtime(TRUE);

    $id = \Sentry\captureMessage($message, $severity);

    $parent = SentrySdk::getCurrentHub()->getSpan();
    if ($parent && $parent->getSampled()) {
      $span = SpanContext::make()
        ->setOrigin('auto.console')
        ->setOp('sentry.capture')
        ->setDescription("$severity: $message")
        ->setStartTimestamp($start)
        ->setEndTimestamp(microtime(TRUE));
      $parent->startChild($span);
    }

    if (!$id) {
      throw new \Exception('Send failed.');
    }
    if ($logger) {
      $logger->success(dt('Message sent as event %id.', ['%id' => $id]));
    }
  }

  /**
   * Send a structured logs item to Sentry.
   *
   * @param string $message
   *   The log message text.
   * @param mixed[] $options
   *   An associative array of options.
   */
  #[CLI\Argument(name: 'message', description: 'The log message text.')]
  #[CLI\Command(name: 'raven:captureLog')]
  #[CLI\Option(name: 'level', description: 'The logs item level (trace, debug, info, warn, error, fatal).')]
  #[CLI\Usage(name: 'drush raven:captureLog', description: 'Send an info logs item to Sentry.')]
  #[CLI\Usage(name: 'drush raven:captureLog --level=error', description: 'Send en error logs item to Sentry.')]
  #[CLI\Usage(name: "drush raven:captureLog 'Mic check.'", description: 'Send "Mic check" logs item to Sentry.')]
  public function captureLog(
    string $message = 'Test log from Drush.',
    array $options = [
      'level' => 'info',
    ],
  ): void {
    $logger = $this->logger();
    // Force invalid configuration to throw an exception.
    $client = $this->ravenLogger->getClient(FALSE, TRUE);
    if (!$client) {
      throw new \Exception('Sentry client not available.');
    }
    elseif (!$client->getOptions()->getDsn() && $logger) {
      $logger->warning(dt('Sentry client key is not configured. No events will be sent to Sentry.'));
    }
    if (!\is_string($options['level']) || !\is_callable([LogLevel::class, $options['level']])) {
      throw new \InvalidArgumentException('Level must be a Sentry LogLevel.');
    }
    if (!$client->getOptions()->getEnableLogs() && $logger) {
      $logger->warning(dt('Structured logs are disabled. No logs will be sent to Sentry.'));
    }
    $start = microtime(TRUE);
    \Sentry\logger()->aggregator()->add(LogLevel::{$options['level']}(), $message);
    $id = \Sentry\logger()->flush();
    $parent = SentrySdk::getCurrentHub()->getSpan();
    if ($parent && $parent->getSampled()) {
      $span = SpanContext::make()
        ->setOrigin('auto.console')
        ->setOp('sentry.log')
        ->setDescription("{$options['level']}: $message")
        ->setStartTimestamp($start)
        ->setEndTimestamp(microtime(TRUE));
      $parent->startChild($span);
    }
    if (!$id) {
      throw new \Exception('Send failed.');
    }
    if ($logger) {
      $logger->success(dt('Log sent as event %id.', ['%id' => $id]));
    }
  }

}
