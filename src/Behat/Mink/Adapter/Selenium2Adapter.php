<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\Mink\Adapter;

use Behat\Mink\Driver\DriverInterface;
use Behat\Mink\Driver\Selenium2Driver;
use DrevOps\BehatSteps\Behat\Mink\BrowserAdapterBase;
use DrevOps\BehatSteps\Behat\Mink\Capability\CookieCapabilityInterface;
use DrevOps\BehatSteps\Behat\Mink\Capability\JavascriptCapabilityInterface;
use DrevOps\BehatSteps\Behat\Mink\Capability\KeyboardCapabilityInterface;

/**
 * Capabilities of the Selenium2 browser driver.
 *
 * A WebDriver session cannot add request headers, so this adapter declares no
 * request-header capability.
 */
class Selenium2Adapter extends BrowserAdapterBase implements CookieCapabilityInterface, JavascriptCapabilityInterface, KeyboardCapabilityInterface {

  /**
   * Browser driver methods this adapter reaches through reflection.
   *
   * The browser driver exposes no public equivalent.
   */
  public const array SYN_METHODS = ['withSyn', 'executeJsOnXpath'];

  /**
   * {@inheritdoc}
   */
  public static function supports(DriverInterface $driver): bool {
    return $driver instanceof Selenium2Driver;
  }

  /**
   * {@inheritdoc}
   */
  public function cookieGetAll(): array {
    /** @var \Behat\Mink\Driver\Selenium2Driver $driver */
    $driver = $this->driver;
    $cookies = [];

    foreach ($driver->getWebDriverSession()->getAllCookies() as $cookie) {
      $cookies[] = [
        'name' => (string) $cookie['name'],
        'value' => (string) $cookie['value'],
      ];
    }

    return $cookies;
  }

  /**
   * {@inheritdoc}
   *
   * Selenium dispatches synthetic events through the bundled Syn library,
   * which the browser driver loads through methods it does not expose
   * publicly.
   */
  public function keyboardTriggerKey(string $xpath, string $key): void {
    $reflection = new \ReflectionClass($this->driver);
    [$with_syn, $execute_js_on_xpath] = self::SYN_METHODS;

    $driver_with_syn = $reflection->getMethod($with_syn)->invoke($this->driver);

    // The key goes into the script as an encoded literal, so a quote, a
    // backslash or a control character reaches Syn as itself.
    $reflection->getMethod($execute_js_on_xpath)->invokeArgs($driver_with_syn, [
      $xpath,
      sprintf('syn.key({{ELEMENT}}, %s);', json_encode($key)),
    ]);
  }

}
