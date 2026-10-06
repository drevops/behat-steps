<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\Mink\Adapter;

use Behat\Mink\Driver\DriverInterface;
use DMore\ChromeDriver\ChromeDriver;
use DrevOps\BehatSteps\Behat\Mink\BrowserAdapterBase;
use DrevOps\BehatSteps\Behat\Mink\Capability\CookieCapabilityInterface;
use DrevOps\BehatSteps\Behat\Mink\Capability\JavascriptCapabilityInterface;
use DrevOps\BehatSteps\Behat\Mink\Capability\KeyboardCapabilityInterface;
use DrevOps\BehatSteps\Behat\Mink\Capability\RequestHeaderCapabilityInterface;

/**
 * Capabilities of the CDP-based Chrome browser driver.
 */
class ChromeAdapter extends BrowserAdapterBase implements CookieCapabilityInterface, JavascriptCapabilityInterface, KeyboardCapabilityInterface, RequestHeaderCapabilityInterface {

  /**
   * Keycodes for the keys whose default action must fire.
   *
   * A special key is sent as a down and up pair so the browser performs the
   * action bound to it; a printable character is sent as a single press so its
   * text is inserted.
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

}
