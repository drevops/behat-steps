<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Steps\Web;

use Behat\Mink\Driver\DriverInterface;
use Behat\Mink\Exception\UnsupportedDriverActionException;
use Behat\Mink\Mink;
use Behat\Mink\Session;
use DrevOps\BehatSteps\Extension\Browser\Capability\ClockCapabilityInterface;
use DrevOps\BehatSteps\Steps\Web\BrowserTimeTrait;
use DrevOps\BehatSteps\Tests\Unit\Extension\Browser\Fixtures\AnyDriverAdapter;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use DrevOps\BehatSteps\WebRawContext;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests setting and restoring the browser time and timezone.
 */
#[CoversTrait(BrowserTimeTrait::class)]
class BrowserTimeTraitTest extends UnitTestCase {

  /**
   * A test implementation of BrowserTimeTrait.
   */
  protected BrowserTimeTraitTestImplementation $testObject;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $session = $this->createStub(Session::class);
    $session->method('getDriver')->willReturn($this->createStub(DriverInterface::class));

    $mink = new Mink(['default' => $session]);
    $mink->setDefaultSessionName('default');

    $this->testObject = new BrowserTimeTraitTestImplementation();
    $this->testObject->setMink($mink);
  }

  /**
   * Tests that a time value parses to the timestamp it names.
   *
   * @param string $value
   *   The step's time value.
   * @param string|null $timezone
   *   The timezone the value is read in.
   * @param int $expected
   *   The expected timestamp.
   */
  #[DataProvider('dataProviderParseValue')]
  public function testParseValue(string $value, ?string $timezone, int $expected): void {
    $this->assertSame($expected, $this->testObject->callParseValue($value, $timezone));
  }

  public static function dataProviderParseValue(): \Iterator {
    yield 'timestamp' => ['1798156740', NULL, 1798156740];
    yield 'negative timestamp' => ['-60', NULL, -60];
    yield 'timestamp ignores the timezone' => ['1798156740', 'Australia/Melbourne', 1798156740];
    yield 'date and time with an offset' => ['2026-12-24T23:59:00+00:00', NULL, 1798156740];
    yield 'date and time in a timezone' => ['2026-12-24 23:59:00', 'Australia/Melbourne', 1798117140];
    yield 'offset wins over the timezone' => ['2026-12-24T23:59:00+00:00', 'Australia/Melbourne', 1798156740];
    yield 'timestamp notation' => ['@1798156740', NULL, 1798156740];
  }

  /**
   * Tests that a value naming no time fails with the argument named.
   *
   * @param string $value
   *   The step's time value.
   */
  #[DataProvider('dataProviderParseValueRejectsValue')]
  public function testParseValueRejectsValue(string $value): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage(sprintf('The value must be a Unix timestamp or a date and time, but "%s" was given.', $value));

    $this->testObject->callParseValue($value);
  }

  public static function dataProviderParseValueRejectsValue(): \Iterator {
    yield 'empty' => [''];
    yield 'whitespace' => ['   '];
    yield 'words' => ['not a time'];
  }

  /**
   * Tests that a name is accepted only when it is a timezone name.
   *
   * @param string $name
   *   The timezone name.
   * @param bool $is_valid
   *   Whether the name is a timezone name.
   */
  #[DataProvider('dataProviderParseTimezone')]
  public function testParseTimezone(string $name, bool $is_valid): void {
    if (!$is_valid) {
      $this->expectException(\RuntimeException::class);
      $this->expectExceptionMessage(sprintf('The timezone must be a timezone name, such as "Europe/London", but "%s" was given.', $name));
    }

    $this->assertSame($name, $this->testObject->callParseTimezone($name)->getName());
  }

  public static function dataProviderParseTimezone(): \Iterator {
    yield 'region' => ['Australia/Melbourne', TRUE];
    yield 'UTC' => ['UTC', TRUE];
    yield 'backward compatible name' => ['US/Eastern', TRUE];
    yield 'offset' => ['+10:00', FALSE];
    yield 'abbreviation' => ['AEDT', FALSE];
    yield 'lowercase' => ['australia/melbourne', FALSE];
    yield 'unknown' => ['Mars/Olympus_Mons', FALSE];
    yield 'empty' => ['', FALSE];
  }

  public function testStepsPassTheParsedValuesToTheClock(): void {
    $adapter = $this->installAdapter();

    $this->testObject->browserTimeSet('1798156740');
    $this->testObject->browserTimeSetInTimezone('2026-12-24 23:59:00', 'Australia/Melbourne');
    $this->testObject->browserTimeSetTimezone('America/New_York');
    $this->testObject->browserTimeReset();

    $this->assertSame([
      ['clockSetTime', 1798156740],
      ['clockSetTimezone', 'Australia/Melbourne'],
      ['clockSetTime', 1798117140],
      ['clockSetTimezone', 'America/New_York'],
      ['clockReset', NULL],
    ], $adapter->clockCalls);
  }

  public function testInvalidTimezoneReachesNoClock(): void {
    $adapter = $this->installAdapter();

    try {
      $this->testObject->browserTimeApplyTimezone('+10:00');
      $this->fail('An offset was accepted as a timezone name.');
    }
    catch (\RuntimeException) {
      $this->assertSame([], $adapter->clockCalls);
    }

    $this->testObject->browserTimeAfterScenario($this->createAfterScenarioScope());

    $this->assertSame([], $adapter->clockCalls, 'A rejected timezone leaves nothing to restore.');
  }

  public function testStepWithoutTheCapabilityFailsBeforeParsing(): void {
    $this->expectException(UnsupportedDriverActionException::class);
    $this->expectExceptionMessage(sprintf('No browser capability "%s" is available', ClockCapabilityInterface::class));

    $this->testObject->browserTimeSet('not a time');
  }

  public function testTeardownOfScenarioThatSetNothingTouchesNoBrowser(): void {
    $this->expectNotToPerformAssertions();

    // No adapter supports the stub driver, so resolving the clock would
    // throw.
    $this->testObject->browserTimeAfterScenario($this->createAfterScenarioScope());
  }

  public function testTeardownRestoresTheClockOnce(): void {
    $adapter = $this->installAdapter();

    $this->testObject->browserTimeApplyTime(1798156740);
    $this->testObject->browserTimeAfterScenario($this->createAfterScenarioScope());
    $this->testObject->browserTimeAfterScenario($this->createAfterScenarioScope());

    $this->assertSame([['clockSetTime', 1798156740], ['clockReset', NULL]], $adapter->clockCalls);
  }

  public function testTeardownRestoresAnOverriddenTimezone(): void {
    $adapter = $this->installAdapter();

    $this->testObject->browserTimeApplyTimezone('Australia/Melbourne');
    $this->testObject->browserTimeAfterScenario($this->createAfterScenarioScope());

    $this->assertSame([['clockSetTimezone', 'Australia/Melbourne'], ['clockReset', NULL]], $adapter->clockCalls);
  }

  public function testSkippedTeardownKeepsTheClock(): void {
    $adapter = $this->installAdapter();

    $this->testObject->browserTimeApplyTime(1798156740);
    $this->testObject->browserTimeAfterScenario($this->createAfterScenarioScope(['behat-steps-skip:BrowserTimeTrait']));
    $this->testObject->browserTimeAfterScenario($this->createAfterScenarioScope());

    $this->assertSame([['clockSetTime', 1798156740]], $adapter->clockCalls, 'A skipped teardown also forgets the override, so a later scenario restores nothing.');
  }

  /**
   * Installs the recording adapter and returns the instance steps resolve.
   *
   * @return \DrevOps\BehatSteps\Tests\Unit\Extension\Browser\Fixtures\AnyDriverAdapter
   *   The adapter.
   */
  protected function installAdapter(): AnyDriverAdapter {
    $this->testObject->getBrowserCapabilityResolver()->registerAdapter(AnyDriverAdapter::class);

    $adapter = $this->testObject->browserDriverFor(ClockCapabilityInterface::class);

    if (!$adapter instanceof AnyDriverAdapter) {
      $this->fail('The registered adapter was not resolved.');
    }

    return $adapter;
  }

}

/**
 * Test implementation of BrowserTimeTrait.
 *
 * Runs the protected parsers.
 */
class BrowserTimeTraitTestImplementation extends WebRawContext {

  use BrowserTimeTrait;

  public function callParseValue(string $value, ?string $timezone = NULL): int {
    return $this->browserTimeParseValue($value, $timezone);
  }

  public function callParseTimezone(string $name): \DateTimeZone {
    return $this->browserTimeParseTimezone($name);
  }

}
