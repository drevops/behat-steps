<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Steps\Web;

use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Hook\AfterScenario;
use Behat\Step\Given;
use Behat\Step\When;
use DrevOps\BehatSteps\Extension\Browser\Capability\ClockCapabilityInterface;
use DrevOps\BehatSteps\Extension\Config\Option;

/**
 * Set the time and the timezone a page's JavaScript reads in the browser.
 *
 * - Set the time 'Date' reads in each page loaded after the step.
 * - Set the timezone the browser formats and reports dates in.
 * - Reset both to the real time and the browser's own timezone.
 *
 * The time applies from the next page load, so set it before visiting the
 * page under test. It ticks on from the given value and stays continuous
 * across page loads. The timezone applies at once.
 *
 * The steps send Chrome DevTools Protocol commands, so they run on a Chromium
 * browser through the Selenium2 or the Chrome browser driver.
 *
 * Skip processing with tag: `@behat-steps-skip:BrowserTimeTrait`.
 *
 * @title Browser time
 *
 * @phpstan-require-extends \DrevOps\BehatSteps\WebRawContext
 */
trait BrowserTimeTrait {

  /**
   * Whether a step in this scenario overrode the browser time or timezone.
   */
  protected bool $browserTimeWasSet = FALSE;

  /**
   * Restores the real browser time and timezone after each scenario.
   */
  #[AfterScenario]
  public function browserTimeAfterScenario(AfterScenarioScope $scope): void {
    // A scenario that never set the time has nothing to restore. Resolving
    // the capability would fail a scenario on a browser driver without it.
    if ($this->skipTag(__TRAIT__, $scope) || !$this->browserTimeWasSet) {
      $this->browserTimeWasSet = FALSE;

      return;
    }

    $this->browserTimeWasSet = FALSE;

    $this->browserDriverFor(ClockCapabilityInterface::class)->clockReset();
  }

  /**
   * Set the browser time for the pages loaded after this step.
   *
   * The value is a Unix timestamp or a date and time in PHP's default
   * timezone.
   *
   * @code
   * Given the browser time is set to "2026-12-24 23:59:00"
   * Given the browser time is set to "1798156740"
   * @endcode
   *
   * @actor:system
   */
  #[Given('the browser time is set to :value')]
  public function browserTimeSet(string $value): void {
    $this->browserDriverFor(ClockCapabilityInterface::class);

    $this->browserTimeApplyTime($this->browserTimeParseValue($value));
  }

  /**
   * Set the browser time and timezone for the pages loaded after this step.
   *
   * A date and time without an offset is read in the given timezone.
   *
   * @code
   * Given the browser time is set to "2026-12-24 23:59:00" in the timezone "Australia/Melbourne"
   * @endcode
   *
   * @actor:system
   */
  #[Given('the browser time is set to :value in the timezone :name')]
  public function browserTimeSetInTimezone(string $value, string $name): void {
    $this->browserDriverFor(ClockCapabilityInterface::class);

    $timestamp = $this->browserTimeParseValue($value, $name);
    $this->browserTimeApplyTimezone($name);
    $this->browserTimeApplyTime($timestamp);
  }

  /**
   * Set the browser timezone, keeping the real time.
   *
   * @code
   * Given the browser timezone is set to "America/New_York"
   * @endcode
   *
   * @actor:system
   */
  #[Given('the browser timezone is set to :name')]
  public function browserTimeSetTimezone(string $name): void {
    $this->browserDriverFor(ClockCapabilityInterface::class);

    $this->browserTimeApplyTimezone($name);
  }

  /**
   * Reset the browser time and timezone to the real ones.
   *
   * @code
   * When the browser time is reset
   * @endcode
   *
   * @actor:system
   */
  #[When('the browser time is reset')]
  public function browserTimeReset(): void {
    $this->browserDriverFor(ClockCapabilityInterface::class)->clockReset();
  }

  /**
   * Applies a time to the browser clock of the pages loaded from now on.
   *
   * @param int $timestamp
   *   The Unix timestamp, in seconds.
   *
   * @throws \Behat\Mink\Exception\UnsupportedDriverActionException
   *   When the browser driver cannot override the clock.
   */
  public function browserTimeApplyTime(int $timestamp): void {
    $clock = $this->browserDriverFor(ClockCapabilityInterface::class);

    $this->browserTimeWasSet = TRUE;

    $clock->clockSetTime($timestamp);
  }

  /**
   * Applies a timezone to the browser.
   *
   * @param string $name
   *   An IANA timezone name, such as 'Australia/Melbourne'.
   *
   * @throws \RuntimeException
   *   When the name is not a timezone name.
   * @throws \Behat\Mink\Exception\UnsupportedDriverActionException
   *   When the browser driver cannot override the timezone.
   */
  public function browserTimeApplyTimezone(string $name): void {
    $clock = $this->browserDriverFor(ClockCapabilityInterface::class);

    $this->browserTimeParseTimezone($name);

    $this->browserTimeWasSet = TRUE;

    $clock->clockSetTimezone($name);
  }

  /**
   * Parses a step's time value into a Unix timestamp.
   *
   * @param string $value
   *   A Unix timestamp, or a date and time 'DateTimeImmutable' accepts.
   * @param string|null $timezone
   *   The timezone a date and time without an offset is read in, or NULL for
   *   PHP's default timezone.
   *
   * @return int
   *   The Unix timestamp, in seconds.
   *
   * @throws \RuntimeException
   *   When the value or the timezone cannot be parsed.
   */
  protected function browserTimeParseValue(string $value, ?string $timezone = NULL): int {
    $zone = $timezone === NULL ? NULL : $this->browserTimeParseTimezone($timezone);

    $timestamp = filter_var($value, FILTER_VALIDATE_INT);

    if ($timestamp !== FALSE) {
      return $timestamp;
    }

    $message = sprintf('The value must be a Unix timestamp or a date and time, but "%s" was given.', $value);

    // An empty string parses as the current time, which no step means.
    if (trim($value) === '') {
      throw new \RuntimeException($message);
    }

    try {
      return (new \DateTimeImmutable($value, $zone))->getTimestamp();
    }
    catch (\Exception $exception) {
      throw new \RuntimeException($message, 0, $exception);
    }
  }

  /**
   * Parses an IANA timezone name.
   *
   * An offset such as '+10:00' is rejected, since the browser accepts only a
   * timezone name.
   *
   * @param string $name
   *   The timezone name.
   *
   * @return \DateTimeZone
   *   The timezone.
   *
   * @throws \RuntimeException
   *   When the name is not a timezone name.
   */
  protected function browserTimeParseTimezone(string $name): \DateTimeZone {
    if (!in_array($name, \DateTimeZone::listIdentifiers(\DateTimeZone::ALL_WITH_BC), TRUE)) {
      throw new \RuntimeException(sprintf('The timezone must be a timezone name, such as "Europe/London", but "%s" was given.', $name));
    }

    return new \DateTimeZone($name);
  }

  /**
   * Declares the options this trait reads.
   *
   * @return array<int, \DrevOps\BehatSteps\Extension\Config\Option>
   *   The options this trait declares.
   */
  protected function browserTimeConfigSchema(): array {
    return [
      new Option('enabled', default: TRUE, description: 'If `TRUE`, restores the real browser time and timezone after a scenario that set either. If `FALSE`, the browser keeps them.'),
    ];
  }

}
