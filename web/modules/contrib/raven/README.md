# Raven: Sentry Integration

Raven module provides integration with [Sentry](https://sentry.io/), an open
source application monitoring and error tracking platform.

Sentry can capture all (or a subset of) Drupal log messages as well as errors
that typically are not logged by Drupal: fatal PHP errors such as memory limit
exceeded, fatal JavaScript errors, and exceptions thrown by Drush commands, and
provides a full stacktrace and customizable metadata for each event. Sentry
structured logs are also supported; these lightweight logs lack stacktraces and
are only sent once at the end of the request (or on demand).

Raven module also integrates with Sentry performance tracing. If enabled on the
module settings page, a transaction event will be created for each
request/response handled by Drupal, and a span (timed operation) will be created
for each request made by Drupal's HTTP client, as well as each log message sent
to Sentry. Additional settings allow spans to be created for each database query
and Twig template. While a performance tracing transaction is active, Drupal's
HTTP client will send the sentry-trace HTTP header with each request to enable
distributed tracing across services.

- For a full description of the module, visit the [project
  page](https://www.drupal.org/project/raven).
- To submit bug reports and feature suggestions, or to track changes, see the
  [issue queue](https://www.drupal.org/project/issues/raven).


## Table of contents

- Requirements
- Installation
- Configuration
- Usage
- Troubleshooting
- Drush integration
- Monolog module integration
- Maintainers


## Requirements

Dependencies are defined in the composer.json file.

Sentry profiling, an optional feature, requires the
[Excimer PHP extension](https://www.mediawiki.org/wiki/Excimer).


## Installation

Run `composer require drupal/raven` to install this module and its dependencies.

You can use the Sentry hosted service or install the Sentry app on your own
infrastructure (e.g. using Docker).


## Configuration

This module logs errors to Sentry in a few ways:

- Register a Drupal logger implementation (for uncaught exceptions, PHP errors,
  and Drupal log messages),
- Record Sentry breadcrumbs for system events,
- Register an error handler for Drush command exceptions,
- Register an error handler for fatal errors, and
- Handle JavaScript exceptions via Sentry JavaScript SDK (if user has the "Send
- JavaScript errors to Sentry" permission).
- Provide handlers to optionally send Content Security Policy (CSP) reports to
  Sentry if [CSP module](https://www.drupal.org/project/csp) or
  [Security Kit module](https://www.drupal.org/project/seckit) is installed.
- Optionally send monitoring sensor status changes to Sentry if
  [Monitoring module](https://www.drupal.org/project/monitoring) is installed.

You can choose which events you want to capture by visiting the Raven
configuration page at admin/config/development/logging and enabling desired
error handlers and selecting error levels.

Additional customizations can be performed by subscribing to the OptionsAlter
event, which allows Sentry client options to be configured, and implementing
Sentry callbacks or a custom event processor:

- `\Drupal\raven\Event\OptionsAlter`: Modify Sentry PHP client configuration.
- `before_send` callback or event processor: Modify or ignore Sentry errors.
- `before_breadcrumb` callback: Modify or ignore Sentry breadcrumbs.
- `before_send_log` callback: Modify or ignore Sentry logs.

To control whether or not an exception is sent to Sentry, customize the client
options by subscribing to `\Drupal\raven\Event\OptionsAlter` and modifying the
options property; use the `ignore_exceptions` option to provide a list of
exception classes that should be ignored. [Read the docs.](https://docs.sentry.io/platforms/php/configuration/filtering/)

To collapse (or display) stack frames from certain file paths, customize the
client options by subscribing to `\Drupal\raven\Event\OptionsAlter` and
modifying the options property; add the path to the `in_app_exclude` (or
`in_app_include`) array; the `DRUPAL_ROOT` constant provides the root path.

To send only a subset of logger channels to Sentry, configure the "ignored
channels" setting at admin/config/development/logging, or to implement more
complex logic, add a `before_send` callback to your client options.

For example, to drop all events when maintenance mode is active and not using
the command-line interface, by returning NULL from the `before_send` callback
(and if this logic somehow throws, catch and ignore the throwable):

```php
use Drupal\raven\Event\OptionsAlter;
use Sentry\Event;
use Sentry\EventHint;
use Symfony\Component\DependencyInjection\Attribute\AutowireServiceClosure;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Apply customizations to Sentry client options.
 */
class MyCustomModuleEventSubscriber implements EventSubscriberInterface {

  public function __construct(
    #[AutowireServiceClosure('maintenance_mode')]
    protected \Closure $maintenanceMode,
    #[AutowireServiceClosure('current_route_match')]
    protected \Closure $routeMatch,
    #[AutowireServiceClosure('current_user')]
    protected \Closure $currentUser,
  ) {
  }

  /**
   * Drop events when in maintenance mode and not using CLI.
   */
  public function onOptionsAlter(OptionsAlter $event): void {
    $event->options['before_send'] = function (
      Event $event,
      ?EventHint $hint,
    ): ?Event {
      try {
        if (
          PHP_SAPI !== 'cli'
          && ($this->maintenanceMode)()->applies(($this->routeMatch)())
          && !($this->maintenanceMode)()->exempt(($this->currentUser)())
        ) {
          return NULL;
        }
      }
      catch (\Throwable) {
      }
      return $event;
    };
  }

  /**
   * Registers the methods in this class that should be listeners.
   *
   * @return mixed[]
   *   An array of event listener definitions.
   */
  public static function getSubscribedEvents(): array {
    return [OptionsAlter::class => 'onOptionsAlter'];
  }

}
```

The logger records a Sentry breadcrumb for each log message. If sensitive debug
data is recorded in a breadcrumb, and a log message is later captured by Sentry,
this could result in sending sensitive data to the Sentry server. To modify or
suppress breadcrumbs, configure a `before_breadcrumb` callback in your client
options.

Sentry logs can be modified via either the
`\Drupal\raven\Event\AttributesAlter` event, which makes log context
available, or the `before_send_log` callback.

The Sentry JavaScript SDK configuration can be modified via the
`js_settings_alter` hook or
`$page['#attached']['drupalSettings']['raven']['options']` array in PHP or the
`drupalSettings.raven.options` object in JavaScript. Sentry callbacks can be
configured via a custom JavaScript library (with a dependency on
`core/drupalSettings`, and using library weight to ensure your custom
configuration is added before Sentry is initialized). For example, to ignore
errors based on partial matches on a string and exact matches on a regular
expression, and also set a custom `beforeSend` callback:

```javascript
drupalSettings.raven.options.ignoreErrors = ['fb_xd_fragment', /^Exact Match$/];
drupalSettings.raven.options.beforeSend = (event) => {
  var isUnsupportedBrowser = navigator.userAgent.match(/Trident.*rv:11\./);
  if (isUnsupportedBrowser) {
    // Do not log the event to Sentry.
    return null;
  }
  else {
    // Do not alter the event.
    return event;
  }
};
```

If desired, the SENTRY_DSN, SENTRY_ENVIRONMENT and SENTRY_RELEASE environment
variables can be used to configure this module, overriding the corresponding
settings at admin/config/development/logging.

Performance monitoring data can be captured and sent to Sentry on both the
browser side and in server-side PHP code. To send performance traces to Sentry,
you will need to set the tracing sample rate to a number greater than
zero and less than or equal to one; the higher the sampling rate, the higher the
percentage of requests for which performance traces will be sent to Sentry. The
browser and server-side tracing sample rate settings can be found on the
settings page at admin/config/development/logging. Because capturing performance
traces can involve a large amount of data, the sampling rate is typically set to
a small number on production.

Cron monitoring can be enabled by creating a new monitor in Sentry and
configuring the cron monitor slug.

If the optional [Excimer PHP extension](https://www.mediawiki.org/wiki/Excimer)
is enabled, a Sentry profiling sample rate can be configured. Profiling gathers
additional data on each function call and allows Sentry to render a detailed,
interactive flame graph.


## Usage

Assuming the applicable PHP log levels have been enabled at
admin/config/development/logging, Drupal's exception and error handlers will
send events to Sentry, and developers can use the normal Drupal, PHP or Sentry
APIs to send events to Sentry:

```php
try {
  throw new \Exception('Oopsie');
}
catch (\Exception $e) {
  // Capture event via Drupal logger:
  \Drupal::logger('oops')->error($e);
  // Capture event via Error::logException():
  \Drupal\Core\Utility\Error::logException(\Drupal::logger('oops'), $e);
  // Capture event via PHP user notice:
  trigger_error($e);
  // Capture event solely via Sentry:
  \Sentry\captureException($e);
  // Capture event via Drupal exception handler (or Drush console error event):
  throw $e;
}
```

Consult the [Sentry documentation](https://docs.sentry.io/platforms/php/) for
more info on how to use Sentry, including client options, callbacks, and custom
event processors.

For example, to add context to all future events:

```php
\Sentry\configureScope(function (\Sentry\State\Scope $scope): void {
  $scope->setUser(['language' => 'es']);
  $scope->setTag('interesting', 'yes');
  $scope->setContext('character', [
    'name' => 'Mighty Fighter',
    'age' => 19,
    'attack_type' => 'melee'
  ]);
});
```

Or, to add context only to events sent to Sentry in the current scope, wrap your
code inside the `\Sentry\withScope()` function.

Breadcrumbs can be added via `\Sentry\addBreadcrumb()`.

You can also create an event processor to enrich each Sentry event with context.
See the various integrations included with Sentry PHP SDK and Raven module for
examples.

To call Sentry JavaScript API functions (e.g. `Sentry.setTag('tag', 'value');`),
your JavaScript library must depend on the `raven/raven` library, which ensures
that the Sentry JavaScript SDK has been loaded and, if possible, initialized. If
the SDK was loaded but not initialized (due to being disabled or lacking
permission), then Sentry methods can be called but will be no-op.


## Troubleshooting

If the client is configured incorrectly (e.g. wrong Sentry DSN) it should fail
silently, but an error message will be available on the status report page.
Sentry JavaScript SDK may log an error or HTTP status code in the browser
console.

To view any debug messages from the Sentry PHP SDK, execute Drush in debug mode:
`drush --debug raven:captureMessage`
Or click the "Send PHP test message to Sentry" button on the Logging and errors
settings page (admin/config/development/logging).

If you have code that generates a large number of log messages - for example,
processing a large set of data, with one notice for each item - you may want to
configure the rate limit setting, which sets an upper bound on the number of
log messages sent to Sentry for each request or execution.


## Drush integration

The `drush raven:captureMessage` command sends a message to Sentry.

If the Drush error handler configuration option is enabled, exceptions thrown by
Drush commands will be sent to Sentry. Note that if an `Error` is thrown, it may
end up being captured by both this error handler and the Drupal logger.

If Drush command performance tracing is enabled, a transaction will be created
at the start of the command and sent to Sentry at termination.


## Monolog module integration

Users of [Monolog module](https://www.drupal.org/project/monolog) can send logs
to Sentry by creating a custom web/sites/default/monolog.services.yml file and
using `drupal.raven` as the target. The `message_placeholder` and
`filter_backtrace` processors should be disabled, as this data needs to be sent
to Sentry for correct stack trace display and event aggregation. For example:

```yaml
parameters:
  monolog.channel_handlers:
    default:
      handlers:
        - name: 'drupal.dblog'
          processors: ['current_user', 'request_uri', 'ip', 'referer']
        - name: 'drupal.raven'
          processors: ['current_user', 'request_uri', 'ip', 'referer']
    custom_channel1: ['rotating_file_custom_channel1']
    custom_channel2: ['rotating_file_custom_channel2']
```


## Maintainers

This module is not affiliated with Sentry; it is developed and maintained by
[mfb](https://www.drupal.org/u/mfb). You can support development by
[sponsoring](https://github.com/sponsors/mfb) or
[contributing](https://www.drupal.org/project/issues/raven). As this project
is currently community-supported rather than commercially-supported, patches or
merge requests providing improvements, new features and bug fixes are welcome
and encouraged.

- [Build status](https://git.drupalcode.org/project/raven/-/pipelines?ref=7.x)
