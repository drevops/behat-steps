<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Extension\Browser\Adapter;

use Behat\Mink\Driver\DriverInterface;
use DMore\ChromeDriver\ChromeDriver;
use DMore\ChromeDriver\ChromePage;
use DrevOps\BehatSteps\Extension\Browser\BrowserAdapterBase;
use DrevOps\BehatSteps\Extension\Browser\Capability\ClockCapabilityInterface;
use DrevOps\BehatSteps\Extension\Browser\Capability\CookieCapabilityInterface;
use DrevOps\BehatSteps\Extension\Browser\Capability\JavascriptCapabilityInterface;
use DrevOps\BehatSteps\Extension\Browser\Capability\KeyboardCapabilityInterface;
use DrevOps\BehatSteps\Extension\Browser\Capability\RequestHeaderCapabilityInterface;

/**
 * Capabilities of the CDP-based Chrome browser driver.
 */
class ChromeAdapter extends BrowserAdapterBase implements ClockCapabilityInterface, CookieCapabilityInterface, JavascriptCapabilityInterface, KeyboardCapabilityInterface, RequestHeaderCapabilityInterface {

  use DevToolsClockTrait;

  /**
   * Browser driver property this adapter reaches through reflection.
   *
   * The browser driver keeps its CDP connection to the page private.
   */
  public const string PAGE_PROPERTY = 'page';

  /**
   * Keycodes for the keys whose default action must fire.
   *
   * A special key is sent as a down and up pair so the browser performs the
   * action bound to it. A printable character is sent as a single press so
   * its text is inserted.
   *
   * @var array<string, int>
   */
  protected const array KEYCODES = [
    "\b" => 8,
    "\t" => 9,
    "\r" => 13,
    'shift' => 16,
    'ctrl' => 17,
    'alt' => 18,
    'pause' => 19,
    'break' => 19,
    'caps' => 20,
    'escape' => 27,
    'page-up' => 33,
    'page-down' => 34,
    'end' => 35,
    'home' => 36,
    'left' => 37,
    'up' => 38,
    'right' => 39,
    'down' => 40,
    'insert' => 45,
    'delete' => 46,
  ];

  /**
   * {@inheritdoc}
   */
  public static function supports(DriverInterface $driver): bool {
    return $driver instanceof ChromeDriver;
  }

  /**
   * {@inheritdoc}
   */
  public function cookieGetAll(): array {
    /** @var \DMore\ChromeDriver\ChromeDriver $driver */
    $driver = $this->driver;
    $cookies = [];

    foreach ($driver->getCookies() as $cookie) {
      $cookies[] = [
        'name' => (string) $cookie['name'],
        'value' => (string) $cookie['value'],
      ];
    }

    return $cookies;
  }

  /**
   * {@inheritdoc}
   */
  public function keyboardTriggerKey(string $xpath, string $key): void {
    if (isset(self::KEYCODES[$key])) {
      $this->driver->keyDown($xpath, self::KEYCODES[$key]);
      $this->driver->keyUp($xpath, self::KEYCODES[$key]);

      return;
    }

    $this->driver->keyPress($xpath, $key);
  }

  /**
   * {@inheritdoc}
   */
  public function requestHeaderSet(string $name, string $value): void {
    /** @var \DMore\ChromeDriver\ChromeDriver $driver */
    $driver = $this->driver;
    $driver->setRequestHeader($name, $value);
  }

  /**
   * {@inheritdoc}
   *
   * The native return type stays 'object': a narrower one would load the
   * browser driver's classes with this adapter, which must load without them.
   *
   * @return \DMore\ChromeDriver\ChromePage
   *   The page connection.
   */
  protected function devToolsConnection(): object {
    $page = (new \ReflectionProperty(ChromeDriver::class, self::PAGE_PROPERTY))->getValue($this->driver);

    if (!$page instanceof ChromePage) {
      throw new \RuntimeException('The Chrome browser driver has no page connection.');
    }

    return $page;
  }

  /**
   * {@inheritdoc}
   */
  protected function devToolsSend(string $method, array $params): array {
    return $this->devToolsConnection()->send($method, $params);
  }

}
