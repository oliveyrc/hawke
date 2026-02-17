<?php

namespace Drupal\raven\Logger;

use Drupal\Component\ClassFinder\ClassFinder;
use Drupal\Component\Utility\Html;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\EventSubscriber\ExceptionLoggingSubscriber;
use Drupal\Core\Logger\LogMessageParserInterface;
use Drupal\Core\Logger\LoggerChannel;
use Drupal\Core\Logger\RfcLoggerTrait;
use Drupal\Core\Mail\MailFormatHelper;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Site\Settings;
use Drupal\raven\Event\AttributesAlter;
use Drupal\raven\Event\OptionsAlter;
use Drupal\raven\Exception\RateLimitException;
use Drupal\raven\Integration\RemoveExceptionFrameVarsIntegration;
use Drupal\raven\Integration\SanitizeIntegration;
use Drupal\raven\LogLevel;
use Drush\Drush;
use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;
use Sentry\Breadcrumb;
use Sentry\ClientInterface;
use Sentry\Event;
use Sentry\EventHint;
use Sentry\ExceptionMechanism;
use Sentry\Integration\EnvironmentIntegration;
use Sentry\Integration\FatalErrorListenerIntegration;
use Sentry\Integration\FrameContextifierIntegration;
use Sentry\Integration\ModulesIntegration;
use Sentry\Integration\RequestFetcherInterface;
use Sentry\Integration\RequestIntegration;
use Sentry\Integration\TransactionIntegration;
use Sentry\SentrySdk;
use Sentry\State\Scope;
use Sentry\Tracing\SpanContext;
use Sentry\UserDataBag;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Logs events to Sentry.
 */
class Raven implements LoggerInterface, RavenInterface {

  use DependencySerializationTrait;
  use RfcLoggerTrait;

  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected LogMessageParserInterface $parser,
    #[Autowire('%kernel.environment%')]
    protected string $environment,
    protected AccountInterface $currentUser,
    protected RequestStack $requestStack,
    protected Settings $settings,
    #[Autowire('@event_dispatcher')]
    protected EventDispatcherInterface $eventDispatcher,
    #[Autowire('@raven.request_fetcher')]
    protected RequestFetcherInterface $requestFetcher,
  ) {
    // We cannot lazily initialize Sentry, because we want the scope to be
    // immediately available for adding context, etc.
    $this->getClient();
  }

  /**
   * {@inheritdoc}
   */
  public function getClient(bool $force_new = FALSE, bool $force_throw = FALSE): ?ClientInterface {
    // Is the client already initialized?
    if (!$force_new && ($client = SentrySdk::getCurrentHub()->getClient())) {
      return $client;
    }
    $config = $this->configFactory->get('raven.settings');
    $options = [
      'default_integrations' => FALSE,
      'dsn' => $config->get('client_key'),
      'environment' => $config->get('environment') ?: $this->environment,
    ];
    if (!\is_null($timeout = $config->get('timeout'))) {
      $options['http_connect_timeout'] = $options['http_timeout'] = $timeout;
    }
    if (!\is_null($http_compression = $config->get('http_compression'))) {
      $options['http_compression'] = $http_compression;
    }
    if ($config->get('stack')) {
      $options['attach_stacktrace'] = TRUE;
    }
    if ($config->get('fatal_error_handler')) {
      $options['integrations'][] = new FatalErrorListenerIntegration();
    }
    $options['integrations'][] = new RequestIntegration($this->requestFetcher);
    $options['integrations'][] = new TransactionIntegration();
    $options['integrations'][] = new FrameContextifierIntegration();
    $options['integrations'][] = new EnvironmentIntegration();
    $options['integrations'][] = new SanitizeIntegration();
    if (!$config->get('trace')) {
      $options['integrations'][] = new RemoveExceptionFrameVarsIntegration();
    }
    if ($config->get('modules')) {
      $options['integrations'][] = new ModulesIntegration();
    }

    if ($release = $config->get('release')) {
      $options['release'] = $release;
    }
    if (!$config->get('send_request_body')) {
      $options['max_request_body_size'] = 'never';
    }
    if (!\is_null($traces = $config->get('traces_sample_rate'))) {
      $options['traces_sample_rate'] = $traces;
    }
    if ($trace_propagation_targets = $config->get('trace_propagation_targets_backend')) {
      $options['trace_propagation_targets'] = $trace_propagation_targets;
    }
    $options['profiles_sample_rate'] = $config->get('profiles_sample_rate');
    if (PHP_SAPI === 'cli') {
      $options['enable_logs'] = $config->get('cli_enable_logs') ?? FALSE;
    }
    else {
      try {
        $options['enable_logs'] = $this->currentUser->hasPermission('send logs to sentry');
      }
      catch (\Throwable) {
        // If the container is broken (e.g. Redis is down), we might not be able
        // to check permissions. In this case, we default to FALSE to avoid
        // crashing the site with a TypeError in EntityTypeManager.
        $options['enable_logs'] = FALSE;
      }
    }

    // Proxy configuration (DSN is null before install).
    $parsed_dsn = parse_url(\is_string($options['dsn']) ? $options['dsn'] : '');
    if (!empty($parsed_dsn['host']) && !empty($parsed_dsn['scheme'])) {
      $http_client_config = $this->settings->get('http_client_config', []);
      if (\is_array($http_client_config) && isset($http_client_config['proxy']) && \is_array($http_client_config['proxy']) && !empty($http_client_config['proxy'][$parsed_dsn['scheme']])) {
        $no_proxy = $http_client_config['proxy']['no'] ?? [];
        // No need to configure proxy if Sentry host is on proxy bypass list.
        if (\is_array($no_proxy) && !\in_array($parsed_dsn['host'], $no_proxy, TRUE)) {
          $options['http_proxy'] = $http_client_config['proxy'][$parsed_dsn['scheme']];
        }
      }
    }

    // If we're in Drush debug mode, attach Drush logger to Sentry client.
    if (\function_exists('drush_main') && Drush::debug()) {
      $options['logger'] = Drush::logger();
    }
    $this->eventDispatcher->dispatch(new OptionsAlter($options), OptionsAlter::class);
    try {
      // @phpstan-ignore argument.type
      \Sentry\init($options);
    }
    catch (\InvalidArgumentException $e) {
      if ($force_throw) {
        throw $e;
      }
      return NULL;
    }
    // Set default user context.
    \Sentry\configureScope(function (Scope $scope): void {
      $user = ['id' => $this->currentUser->id()];
      $config = $this->configFactory->get('raven.settings');
      if ($config->get('capture_user_ip') && ($request = $this->requestStack->getCurrentRequest())) {
        $user['ip_address'] = $request->getClientIp();
      }
      if ($config->get('send_user_data')) {
        $user['email'] = $this->currentUser->getEmail();
        $user['username'] = $this->currentUser->getAccountName();
      }
      $scope->setUser($user);
    });
    // Try to flush logs after a fatal error.
    drupal_register_shutdown_function(static fn () => \Sentry\logger()->flush());
    return SentrySdk::getCurrentHub()->getClient();
  }

  /**
   * {@inheritdoc}
   */
  public function log(mixed $level, string|\Stringable $message, array $context = []): void {
    global $base_root;
    static $counter = 0;
    $client = $this->getClient();
    if (!$client) {
      return;
    }
    $log_level = LogLevel::fromLevel($level);
    $config = $this->configFactory->get('raven.settings');
    $log_levels = $config->get('log_levels');
    if (!\is_array($log_levels)) {
      $log_levels = [];
    }
    $ignored_channels = $config->get('ignored_channels');
    if (!\is_array($ignored_channels)) {
      $ignored_channels = [];
    }
    // Preserve the original $message argument for debugging purposes.
    $unformatted_message = $message;
    // Remove backtrace string from the message, as it is redundant with Sentry
    // stack traces, and could leak function calling arguments to Sentry
    // (depending on the configuration of zend.exception_ignore_args and
    // zend.exception_string_param_max_len).
    if (isset($context['@backtrace_string'])) {
      $unformatted_message = str_replace(' @backtrace_string', '', $unformatted_message);
      unset($context['@backtrace_string']);
    }
    $message_placeholders = $this->parser->parseMessagePlaceholders($unformatted_message, $context);
    $formatted_message = empty($message_placeholders) ? $unformatted_message : strtr($unformatted_message, $message_placeholders);
    $ignored_messages = $config->get('ignored_messages');
    if (!\is_array($ignored_messages)) {
      $ignored_messages = [];
    }
    if ($log_level->isEnabled($log_levels) && !\in_array($context['channel'], $ignored_channels) && !\in_array($unformatted_message, $ignored_messages)) {
      $event = Event::createEvent()
        ->setLevel($log_level->getSeverity())
        ->setMessage($unformatted_message, $message_placeholders, $formatted_message)
        ->setTimestamp($context['timestamp'])
        ->setLogger($context['channel']);
      $extra = ['request_uri' => $context['request_uri']];
      if ($context['referer']) {
        $extra['referer'] = $context['referer'];
      }
      if ($context['link']) {
        $extra['link'] = MailFormatHelper::htmlToText($context['link']);
      }
      $event->setExtra($extra);
      $user = UserDataBag::createFromUserIdentifier($context['uid']);
      if ($config->get('capture_user_ip')) {
        $user->setIpAddress($context['ip'] ?: NULL);
      }
      if ($this->currentUser->id() == $context['uid'] && $config->get('send_user_data')) {
        $user->setEmail($this->currentUser->getEmail())
          ->setUsername($this->currentUser->getAccountName());
      }
      $event->setUser($user);
      if ($client->getOptions()->shouldAttachStacktrace()) {
        if (isset($context['backtrace'])) {
          $backtrace = $context['backtrace'];
          if (!$config->get('trace')) {
            foreach ($backtrace as &$frame) {
              unset($frame['args']);
            }
          }
        }
        else {
          $backtrace = debug_backtrace($config->get('trace') ? 0 : DEBUG_BACKTRACE_IGNORE_ARGS);
          // Remove any logger stack frames.
          $finder = new ClassFinder();
          $class_file = $finder->findFile(LoggerChannel::class);
          if ($class_file && isset($backtrace[0]['file']) && $backtrace[0]['file'] === realpath($class_file)) {
            array_shift($backtrace);
            $class_file = $finder->findFile(LoggerTrait::class);
            if ($class_file && isset($backtrace[0]['file']) && $backtrace[0]['file'] === realpath($class_file)) {
              array_shift($backtrace);
            }
          }
        }
        $stacktrace = $client->getStacktraceBuilder()->buildFromBacktrace($backtrace, '', 0);
        $stacktrace->removeFrame(\count($stacktrace->getFrames()) - 1);
        $event->setStacktrace($stacktrace);
        $eventHint['stacktrace'] = $stacktrace;
      }
      $eventHint['extra'] = [
        'level' => $level,
        'message' => $unformatted_message,
        'context' => $context,
      ];
      if (isset($context['exception']) && $context['exception'] instanceof \Throwable) {
        $eventHint['exception'] = $context['exception'];
        // Capture "critical" uncaught exceptions logged by
        // ExceptionLoggingSubscriber and "fatal" errors logged by
        // _drupal_log_error() as "unhandled" exceptions.
        if (!$context['exception'] instanceof HttpExceptionInterface || $context['exception']->getStatusCode() >= 500) {
          $backtrace = debug_backtrace(0, 3);
          if ((isset($backtrace[2]['class']) && $backtrace[2]['class'] === ExceptionLoggingSubscriber::class && $backtrace[2]['function'] === 'onError') || (!isset($backtrace[2]['class']) && isset($backtrace[2]['function']) && $backtrace[2]['function'] === '_drupal_log_error' && !empty($backtrace[2]['args'][1]))) {
            $eventHint['mechanism'] = new ExceptionMechanism(ExceptionMechanism::TYPE_GENERIC, FALSE);
          }
        }
      }
      $start = microtime(TRUE);
      $rateLimit = $config->get('rate_limit');
      if (!$rateLimit || $counter < $rateLimit) {
        \Sentry\captureEvent($event, EventHint::fromArray($eventHint));
      }
      elseif ($counter == $rateLimit) {
        \Sentry\captureException(new RateLimitException('Log event discarded due to rate limit exceeded; future log events will not be captured by Sentry.'));
      }
      $counter++;
      $parent = SentrySdk::getCurrentHub()->getSpan();
      if ($parent && $parent->getSampled()) {
        $span = SpanContext::make()
          ->setOrigin('auto.app')
          ->setOp('sentry.capture')
          ->setDescription($context['channel'] . ': ' . $formatted_message)
          ->setStartTimestamp($start)
          ->setEndTimestamp(microtime(TRUE));
        $parent->startChild($span);
      }
    }

    if ($client->getOptions()->getEnableLogs()) {
      $logs_log_levels = $config->get('logs_log_levels');
      if (!\is_array($logs_log_levels)) {
        $logs_log_levels = [];
      }
      if ($log_level->isEnabled($logs_log_levels)) {
        $attributes['channel'] = $context['channel'];
        if (!empty($context['link'])) {
          foreach (Html::load($context['link'])->getElementsByTagName('a') as $link) {
            $attributes['link'] = $base_root . $link->getAttribute('href');
          }
        }
        if (!empty($context['referer'])) {
          $attributes['referer'] = $context['referer'];
        }
        $attributes['request_uri'] = $context['request_uri'];
        if ($message_placeholders) {
          $attributes['sentry.message.template'] = $unformatted_message;
          foreach ($message_placeholders as $key => $value) {
            $attributes["sentry.message.parameter.$key"] = $value;
          }
        }
        $attributes['user.id'] = $context['uid'];
        if ($config->get('capture_user_ip')) {
          $attributes['user.ip_address'] = $context['ip'];
        }
        $this->eventDispatcher->dispatch(new AttributesAlter($attributes, $context), AttributesAlter::class);
        \Sentry\logger()->aggregator()->add(
          $log_level->getLogsLogLevel(),
          $formatted_message,
          [],
          $attributes,
        );
      }
    }

    // Record a breadcrumb.
    $breadcrumb = [
      'category' => $context['channel'],
      'message' => (string) $formatted_message,
      'level' => $log_level->getBreadcrumbLevel(),
    ];
    foreach (['%line', '%file', '%type', '%function'] as $key) {
      if (isset($context[$key])) {
        $breadcrumb['data'][substr($key, 1)] = $context[$key];
      }
    }
    \Sentry\addBreadcrumb(Breadcrumb::fromArray($breadcrumb));
  }

  /**
   * Sends all unsent events.
   *
   * Call this method periodically if you have a long-running script or are
   * processing a large set of data which may generate errors.
   */
  public function flush(): void {
    if ($client = $this->getClient()) {
      $client->flush();
      \Sentry\logger()->flush();
    }
  }

}
