<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\Mink\ServiceContainer\Driver;

use Behat\MinkExtension\ServiceContainer\Driver\BrowserKitFactory as UpstreamBrowserKitFactory;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Definition;

/**
 * Builds the 'browserkit_http' driver and records its connection options.
 *
 * Each session is built exactly as Mink builds it, on a client of its own that
 * applies the session's 'http_client_parameters' to every host. The options
 * are also recorded, because the detached and bare browsers send with them.
 *
 * @see \DrevOps\BehatSteps\Behat\Http\HttpClientFactory
 */
final class BrowserKitFactory extends UpstreamBrowserKitFactory {

  /**
   * The 'http_client_parameters' of each session built, in build order.
   *
   * @var array<int, array<array-key, mixed>>
   */
  protected array $sessionOptions = [];

  /**
   * {@inheritdoc}
   *
   * @param array<array-key, mixed> $config
   *   Driver configuration.
   */
  public function buildDriver(array $config): Definition {
    $options = $config['http_client_parameters'] ?? [];
    $this->sessionOptions[] = is_array($options) ? $options : [];

    return parent::buildDriver($config);
  }

  /**
   * Returns the connection options the 'browserkit_http' sessions declare.
   *
   * @return array<array-key, mixed>
   *   The options, or an empty array when no session was built.
   *
   * @throws \Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
   *   When 2 sessions declare different options.
   */
  public function getClientOptions(): array {
    $options = self::normalizeOptions($this->sessionOptions[0] ?? []);

    foreach ($this->sessionOptions as $session_options) {
      if (self::normalizeOptions($session_options) !== $options) {
        throw new InvalidConfigurationException(sprintf('The %d "browserkit_http" sessions declare different "http_client_parameters". Behat Steps sends its own requests with 1 set of connection options, so give every "browserkit_http" session the same options.', count($this->sessionOptions)));
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
