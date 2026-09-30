<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\Mink\ServiceContainer\Driver;

use Behat\Mink\Driver\BrowserKitDriver;
use Behat\MinkExtension\ServiceContainer\Driver\BrowserKitFactory as UpstreamBrowserKitFactory;
use Symfony\Component\BrowserKit\HttpBrowser;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Builds the 'browserkit_http' driver on the transport Behat Steps shares.
 *
 * Mink's own factory creates a transport for each session out of its
 * 'http_client_parameters'. This factory keeps Mink's options and Mink's
 * 'HttpBrowser', records the options each session declares, and points every
 * session at 1 transport service. The detached and bare browsers send through
 * the same service, so every request to the site uses 1 set of options.
 *
 * @see \DrevOps\BehatSteps\Behat\Http\HttpClientFactory
 */
class BrowserKitFactory extends UpstreamBrowserKitFactory {

  /**
   * Service ID of the transport every browser sends through.
   */
  public const TRANSPORT_SERVICE = 'behat_steps.http_client';

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

    $browser = new Definition(HttpBrowser::class, [new Reference(self::TRANSPORT_SERVICE)]);

    return new Definition(BrowserKitDriver::class, [$browser, '%mink.base_url%']);
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
    $options = static::normalizeOptions($this->sessionOptions[0] ?? []);

    foreach ($this->sessionOptions as $session_options) {
      if (static::normalizeOptions($session_options) !== $options) {
        throw new InvalidConfigurationException(sprintf('The %d "browserkit_http" sessions declare different "http_client_parameters". Behat Steps sends its own requests with 1 set of connection options, so give every "browserkit_http" session the same options.', count($this->sessionOptions)));
      }
    }

    return $options;
  }

  /**
   * Sorts options by key at every level, so key order never tells 2 apart.
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
        $options[$key] = static::normalizeOptions($value);
      }
    }

    return $options;
  }

}
