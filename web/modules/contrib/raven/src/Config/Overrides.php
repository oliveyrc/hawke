<?php

namespace Drupal\raven\Config;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryOverrideInterface;
use Drupal\Core\Config\StorageInterface;

/**
 * Automatically overrides Security Kit configuration.
 */
class Overrides implements ConfigFactoryOverrideInterface {

  /**
   * {@inheritdoc}
   *
   * @param mixed[] $names
   *   The config names.
   *
   * @return mixed[]
   *   The config overrides.
   */
  public function loadOverrides($names) {
    $overrides = [];
    if (!\in_array('raven.settings', $names)) {
      return $overrides;
    }
    foreach ([
      'client_key' => 'SENTRY_DSN',
      'environment' => 'SENTRY_ENVIRONMENT',
      'public_dsn' => 'SENTRY_DSN',
      'release' => 'SENTRY_RELEASE',
    ] as $key => $index) {
      if (!empty($_SERVER[$index])) {
        $overrides['raven.settings'][$key] = $_SERVER[$index];
      }
    }
    return $overrides;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheSuffix() {
    return 'RavenOverrider';
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheableMetadata($name) {
    return new CacheableMetadata();
  }

  /**
   * Creates a configuration object for use during install and synchronization.
   *
   * @param string $name
   *   The configuration object name.
   * @param string $collection
   *   The configuration collection.
   *
   * @return \Drupal\Core\Config\StorableConfigBase|null
   *   The configuration object for the provided name and collection.
   */
  public function createConfigObject($name, $collection = StorageInterface::DEFAULT_COLLECTION) {
    return NULL;
  }

}
