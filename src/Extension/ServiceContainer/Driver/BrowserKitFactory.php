<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Extension\ServiceContainer\Driver;

use Behat\MinkExtension\ServiceContainer\Driver\BrowserKitFactory as UpstreamBrowserKitFactory;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Definition;

/**
 * Builds the 'browserkit_http' browser driver and records its connection options.
 *
 * Each session is built exactly as Mink builds it, on a client of its own that
 * applies the session's 'http_client_parameters' to every host. The options
 * are also recorded, because the detached and bare browsers send with them.
 *
 * @see \DrevOps\BehatSteps\Extension\Http\HttpClientFactory
 */
final class BrowserKitFactory extends UpstreamBrowserKitFactory {

  /**
   * The driver and 'http_client_parameters' of each session, in build order.
   *
   * @var array<int, array{driver: \Symfony\Component\DependencyInjection\Definition, options: array<array-key, mixed>}>
   */
  protected array $sessionOptions = [];

  /**
   * {@inheritdoc}
   *
   * @param array<array-key, mixed> $config
   *   Browser driver configuration.
   */
  public function buildDriver(array $config): Definition {
    $options = $config['http_client_parameters'] ?? [];
    $driver = parent::buildDriver($config);

    $this->sessionOptions[] = ['driver' => $driver, 'options' => is_array($options) ? $options : []];

    return $driver;
  }

  /**
   * Returns the connection options the 'browserkit_http' sessions declare.
   *
   * @param \Symfony\Component\DependencyInjection\Definition|null $excluded
   *   The driver definition of a session whose options are its own, or NULL.
   *
   * @return array<array-key, mixed>
   *   The options, or an empty array when no other session was built.
   *
   * @throws \Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
   *   When 2 sessions declare different options.
   */
  public function getClientOptions(?Definition $excluded = NULL): array {
    $sessions = array_values(array_filter($this->sessionOptions, static fn(array $session): bool => $session['driver'] !== $excluded));
    $options = self::normalizeOptions($sessions[0]['options'] ?? []);

    foreach ($sessions as $session) {
      if (self::normalizeOptions($session['options']) !== $options) {
        throw new InvalidConfigurationException(sprintf('The %d "browserkit_http" sessions declare different "http_client_parameters". Behat Steps sends its own requests with 1 set of connection options, so give every "browserkit_http" session the same options, apart from a session named "rest".', count($sessions)));
      }
    }

    return $options;
  }

  /**
   * Sorts options by key at every level, so comparisons ignore key order.
   *
   * @param array<array-key, mixed> $options
   *   The options to sort.
   *
   * @return array<array-key, mixed>
   *   The sorted options.
   */
  protected static function normalizeOptions(array $options): array {
    ksort($options);

    foreach ($options as $key => $value) {
      if (is_array($value)) {
        $options[$key] = self::normalizeOptions($value);
      }
    }

    return $options;
  }

}
