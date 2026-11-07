<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Extension\Browser\Adapter;

use Behat\Mink\Driver\Selenium2Driver;
use Behat\Mink\Exception\UnsupportedDriverActionException;
use DrevOps\BehatSteps\Extension\Browser\Adapter\Selenium2Adapter;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use WebDriver\Exception\UnknownCommand;
use WebDriver\Session;

/**
 * Tests the CDP transport of the Selenium2 browser driver adapter.
 */
#[CoversClass(Selenium2Adapter::class)]
class Selenium2AdapterTest extends UnitTestCase {

  public function testClockCommandsGoThroughTheChromedriverEndpoint(): void {
    $session = new RecordingWebDriverSession(['value' => NULL]);

    (new Selenium2Adapter($this->createSeleniumDriver($session)))->clockSetTimezone('Australia/Melbourne');

    $this->assertSame([['POST', '/goog/cdp/execute', ['cmd' => 'Emulation.setTimezoneOverride', 'params' => ['timezoneId' => 'Australia/Melbourne']]]], $session->requests);
  }

  /**
   * Tests that the CDP result is read from the WebDriver response value.
   *
   * @param mixed $response
   *   The response the session returns.
   * @param bool $is_accepted
   *   Whether the response carries the script identifier.
   */
  #[DataProvider('dataProviderClockScriptIdentifierIsReadFromTheResponseValue')]
  public function testClockScriptIdentifierIsReadFromTheResponseValue(mixed $response, bool $is_accepted): void {
    $adapter = new Selenium2Adapter($this->createSeleniumDriver(new RecordingWebDriverSession($response)));

    if (!$is_accepted) {
      $this->expectException(\RuntimeException::class);
      $this->expectExceptionMessage('The browser did not return an identifier for the clock script.');
    }

    $adapter->clockSetTime(1798156740);
    $this->addToAssertionCount(1);
  }

  public static function dataProviderClockScriptIdentifierIsReadFromTheResponseValue(): \Iterator {
    yield 'identifier' => [['value' => ['identifier' => '1']], TRUE];
    yield 'value without an identifier' => [['value' => []], FALSE];
    yield 'scalar value' => [['value' => 'unexpected'], FALSE];
    yield 'no value' => [[], FALSE];
    yield 'not an array' => [NULL, FALSE];
  }

  public function testBrowserWithoutDevToolsIsUnsupported(): void {
    $session = new RecordingWebDriverSession(new UnknownCommand('unknown command'));

    $this->expectException(UnsupportedDriverActionException::class);
    $this->expectExceptionMessage('does not accept the Chrome DevTools Protocol command "Emulation.setTimezoneOverride".');

    (new Selenium2Adapter($this->createSeleniumDriver($session)))->clockSetTimezone('Australia/Melbourne');
  }

  /**
   * Create a Selenium2 browser driver over a WebDriver session.
   *
   * @param \WebDriver\Session $session
   *   The WebDriver session.
   *
   * @return \Behat\Mink\Driver\Selenium2Driver
   *   The driver.
   */
  protected function createSeleniumDriver(Session $session): Selenium2Driver {
    $driver = $this->createStub(Selenium2Driver::class);
    $driver->method('getWebDriverSession')->willReturn($session);

    return $driver;
  }

}

/**
 * WebDriver session that records each request and answers with 1 response.
 */
class RecordingWebDriverSession extends Session {

  /**
   * Requests sent, as HTTP method, command and parameters.
   *
   * @var array<int, array{0: string, 1: string, 2: mixed}>
   */
  public array $requests = [];

  /**
   * Constructs a session answering every request with the given response.
   *
   * @param mixed $response
   *   The response to return, or an exception to throw.
   */
  public function __construct(protected mixed $response) {
    parent::__construct('http://chrome:4444/wd/hub/session/1', []);
  }

  /**
   * {@inheritdoc}
   */
  protected function curl($request_method, $command, $parameters = NULL, $extra_options = []) {
    $this->requests[] = [$request_method, $command, $parameters];

    if ($this->response instanceof \Throwable) {
      throw $this->response;
    }

    return $this->response;
  }

}
