<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\Mink\Adapter;

use Behat\Mink\Driver\BrowserKitDriver;
use Behat\Mink\Driver\DriverInterface;
use DrevOps\BehatSteps\Behat\Mink\BrowserAdapterBase;
use DrevOps\BehatSteps\Behat\Mink\Capability\CookieCapabilityInterface;
use DrevOps\BehatSteps\Behat\Mink\Capability\HttpClientCapabilityInterface;
use DrevOps\BehatSteps\Behat\Mink\Capability\RequestHeaderCapabilityInterface;
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

    // The value list is filtered for the current URL but holds only names and
    // values; the cookie objects supply the remaining properties.
    $relevant = $jar->allValues($driver->getCurrentUrl(), TRUE);
    $cookies = [];

    foreach ($jar->all() as $cookie) {
      if (!$cookie instanceof Cookie || !array_key_exists($cookie->getName(), $relevant)) {
        // @codeCoverageIgnoreStart
        continue;
        // @codeCoverageIgnoreEnd
      }

      $cookies[] = [
        'name' => $cookie->getName(),
        'value' => $cookie->getRawValue(),
        'secure' => $cookie->isSecure(),
      ];
    }

    return $cookies;
  }

  /**
   * {@inheritdoc}
   */
  public function httpClient(): object {
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
