<?php

namespace Drupal\raven\Hook;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\monitoring\Result\SensorResultInterface;
use Sentry\Event;
use Sentry\Severity;

/**
 * Implements hook_monitoring_run_sensors().
 */
#[Hook('monitoring_run_sensors')]
class MonitoringRunSensors {

  public function __construct(protected ConfigFactoryInterface $configFactory) {
  }

  /**
   * Implements hook_monitoring_run_sensors().
   *
   * @param \Drupal\monitoring\Result\SensorResultInterface[] $results
   *   The sensor results.
   */
  public function __invoke(array $results): void {
    if (!$this->configFactory->get('raven.settings')->get('send_monitoring_sensor_status_changes')) {
      return;
    }
    $levels = [
      SensorResultInterface::STATUS_OK => Severity::DEBUG,
      SensorResultInterface::STATUS_INFO => Severity::INFO,
      SensorResultInterface::STATUS_WARNING => Severity::WARNING,
      SensorResultInterface::STATUS_CRITICAL => Severity::FATAL,
      SensorResultInterface::STATUS_UNKNOWN => Severity::ERROR,
    ];
    foreach ($results as $result) {
      if ($result->isCached()) {
        continue;
      }
      if (($previous = $result->getPreviousResult()) && $previous->getStatus() === $result->getStatus()) {
        continue;
      }
      $event = Event::createEvent();
      $event->setLevel(new Severity($levels[$result->getStatus()]));
      $message = '[category] label: message';
      $message_placeholders = [
        'category' => $result->getSensorConfig()->getCategory(),
        'label' => $result->getSensorConfig()->getLabel(),
        'message' => $result->getMessage(),
      ];
      $formatted_message = strtr($message, $message_placeholders);
      $event->setMessage($message, $message_placeholders, $formatted_message);
      \Sentry\captureEvent($event);
    }
  }

}
