<?php

namespace Drupal\raven\Hook;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Sentry\CheckIn;
use Sentry\CheckInStatus;
use Sentry\Event;
use Sentry\SentrySdk;

/**
 * Implements hook_cron().
 */
#[Hook('cron')]
class Cron {

  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected TimeInterface $time,
  ) {
  }

  /**
   * Implements hook_cron().
   */
  public function __invoke(): void {
    if ((!$slug = $this->configFactory->get('raven.settings')->get('cron_monitor_id')) || !\is_string($slug)) {
      return;
    }
    $hub = SentrySdk::getCurrentHub();
    if (!$client = $hub->getClient()) {
      return;
    }
    $options = $client->getOptions();
    $checkIn = new CheckIn($slug, CheckInStatus::inProgress(), NULL, $options->getRelease(), $options->getEnvironment(), $this->time->getCurrentMicroTime() - $this->time->getRequestMicroTime());
    $event = Event::createCheckIn();
    $event->setCheckIn($checkIn);
    $hub->captureEvent($event);
    drupal_register_shutdown_function(function () use ($hub, $checkIn) {
      $checkIn->setStatus(CheckInStatus::ok());
      $checkIn->setDuration($this->time->getCurrentMicroTime() - $this->time->getRequestMicroTime());
      $event = Event::createCheckIn();
      $event->setCheckIn($checkIn);
      $hub->captureEvent($event);
    });
  }

}
