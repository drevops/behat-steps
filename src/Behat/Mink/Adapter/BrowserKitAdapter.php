<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\Mink\Adapter;

use Behat\Mink\Driver\BrowserKitDriver;
use Behat\Mink\Driver\DriverInterface;
use DrevOps\BehatSteps\Behat\Mink\BrowserAdapterBase;
use DrevOps\BehatSteps\Behat\Mink\Capability\CookieCapabilityInterface;
use DrevOps\BehatSteps\Behat\Mink\Capability\HttpClientCapabilityInterface;
use DrevOps\BehatSteps\Behat\Mink\Capability\RequestHeaderCapabilityInterface;
use Symfony\Component\BrowserKit\AbstractBrowser;
use Symfony\Component\BrowserKit\Cookie;

/**
 * Capabilities of a BrowserKit-based driver.
 *
 * The driver is itself an HTTP client rather than a browser, so it reads
 * cookies, sets request headers and lends out its client, but runs no
 * JavaScript and dispatches no key events.
 */
class BrowserKitAdapter extends BrowserAdapterBase implements CookieCapabilityInterface, HttpClientCapabilityInterface, RequestHeaderCapabilityInterface {

  /**
   * {@inheritdoc}
   */
  public static function supports(DriverInterface $driver): bool {
    return $driver instanceof BrowserKitDriver;
  }

  /**
   * {@inheritdoc}
   */
  public function cookieGetAll(): array {
    /** @var \Behat\Mink\Driver\BrowserKitDriver<object, object> $driver */
    $driver = $this->driver;
    $jar = $driver->getClient()->getCookieJar();

    // The value list holds 1 entry per name, already resolved for the current
    // URL by domain, path and secure flag. The cookie objects supply the
    // remaining properties, and 'all()' flattens every domain and path
    // together, so several objects can share a name: the one carrying the
    // resolved value is the one that belongs to this URL.
    $resolved = $jar->allValues($driver->getCurrentUrl(), TRUE);
    $cookies = [];

    foreach ($jar->all() as $cookie) {
      if (!$cookie instanceof Cookie) {
        // @codeCoverageIgnoreStart
        continue;
        // @codeCoverageIgnoreEnd
      }

      $name = $cookie->getName();

      if (isset($cookies[$name]) || ($resolved[$name] ?? NULL) !== $cookie->getRawValue()) {
        continue;
      }

      $cookies[$name] = [
        'name' => $name,
        'value' => $cookie->getRawValue(),
      ];
    }

    return array_values($cookies);
  }

  /**
   * {@inheritdoc}
   */
  public function httpClient(): AbstractBrowser {
    /** @var \Behat\Mink\Driver\BrowserKitDriver<object, object> $driver */
    $driver = $this->driver;

    return $driver->getClient();
  }

  /**
   * {@inheritdoc}
   */
  public function requestHeaderSet(string $name, string $value): void {
    /** @var \Behat\Mink\Driver\BrowserKitDriver<object, object> $driver */
    $driver = $this->driver;
    $driver->setRequestHeader($name, $value);
  }

}
