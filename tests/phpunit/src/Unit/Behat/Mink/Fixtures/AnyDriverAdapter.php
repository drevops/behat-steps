<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Behat\Mink\Fixtures;

use Behat\Mink\Driver\DriverInterface;
use DrevOps\BehatSteps\Behat\Mink\BrowserAdapterBase;
use DrevOps\BehatSteps\Behat\Mink\Capability\CookieCapabilityInterface;
use DrevOps\BehatSteps\Behat\Mink\Capability\HttpClientCapabilityInterface;
use DrevOps\BehatSteps\Behat\Mink\Capability\JavascriptCapabilityInterface;
use DrevOps\BehatSteps\Behat\Mink\Capability\KeyboardCapabilityInterface;
use DrevOps\BehatSteps\Behat\Mink\Capability\RequestHeaderCapabilityInterface;
use Symfony\Component\BrowserKit\AbstractBrowser;
use Symfony\Component\BrowserKit\HttpBrowser;
use Symfony\Component\HttpClient\MockHttpClient;

/**
 * Declares every capability for any driver, including a mocked one.
 */
class AnyDriverAdapter extends BrowserAdapterBase implements CookieCapabilityInterface, HttpClientCapabilityInterface, JavascriptCapabilityInterface, KeyboardCapabilityInterface, RequestHeaderCapabilityInterface {

  /**
   * Cookies this adapter reports.
   *
   * @var array<int, array{name: string, value: string}>
   */
  public static array $cookies = [];

  /**
   * Keys this adapter was asked to trigger, as XPath and key pairs.
   *
   * @var array<int, array{0: string, 1: string}>
   */
  public array $triggered = [];

  /**
   * Request headers this adapter was asked to set, keyed by name.
   *
   * @var array<string, string>
   */
  public array $headers = [];

  /**
   * {@inheritdoc}
   */
  public static function supports(DriverInterface $driver): bool {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function cookieGetAll(): array {
    return static::$cookies;
  }

  /**
   * {@inheritdoc}
   */
  public function httpClient(): AbstractBrowser {
    return new HttpBrowser(new MockHttpClient());
  }

  /**
   * {@inheritdoc}
   */
  public function keyboardTriggerKey(string $xpath, string $key): void {
    $this->triggered[] = [$xpath, $key];
  }

  /**
   * {@inheritdoc}
   */
  public function requestHeaderSet(string $name, string $value): void {
    $this->headers[$name] = $value;
  }

}
