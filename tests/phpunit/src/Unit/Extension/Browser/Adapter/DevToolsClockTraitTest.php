<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Extension\Browser\Adapter;

use DrevOps\BehatSteps\Extension\Browser\Adapter\DevToolsClockTrait;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests overriding the browser clock and timezone over CDP.
 */
#[CoversTrait(DevToolsClockTrait::class)]
class DevToolsClockTraitTest extends UnitTestCase {

  public function testSetTimeAddsScriptOffsetFromTheBrowserClock(): void {
    $clock = new DevToolsClockTraitTestImplementation(new \stdClass());
    $clock->testSetBrowserClock(['result' => ['type' => 'number', 'value' => 1000000.4]]);

    $clock->clockSetTime(1798156740);

    $this->assertSame(['Runtime.evaluate', 'Page.addScriptToEvaluateOnNewDocument'], array_column($clock->commands, 0));
    $this->assertSame(['expression' => 'performance.timeOrigin + performance.now()', 'returnByValue' => TRUE], $clock->commands[0][1]);
    $this->assertSame(1798156740000 - 1000000, $this->readOffset($clock->commands[1][1]));
  }

  /**
   * Tests that the offset falls back to the clock of this process.
   *
   * @param array<string, mixed> $browser_clock
   *   The result the browser returns for the clock expression.
   */
  #[DataProvider('dataProviderSetTimeFallsBackToTheProcessClock')]
  public function testSetTimeFallsBackToTheProcessClock(array $browser_clock): void {
    $clock = new DevToolsClockTraitTestImplementation(new \stdClass());
    $clock->testSetBrowserClock($browser_clock);

    $before = (int) round(microtime(TRUE) * 1000);
    $clock->clockSetTime(1798156740);
    $after = (int) round(microtime(TRUE) * 1000);

    $offset = $this->readOffset($clock->commands[1][1]);
    $this->assertGreaterThanOrEqual(1798156740000 - $after, $offset);
    $this->assertLessThanOrEqual(1798156740000 - $before, $offset);
  }

  public static function dataProviderSetTimeFallsBackToTheProcessClock(): \Iterator {
    yield 'no result' => [[]];
    yield 'scalar result' => [['result' => 'unexpected']];
    yield 'no value' => [['result' => ['type' => 'undefined']]];
    yield 'value not a number' => [['result' => ['type' => 'string', 'value' => 'soon']]];
  }

  public function testSetTimeReplacesTheScriptAddedBefore(): void {
    $clock = new DevToolsClockTraitTestImplementation(new \stdClass());

    $clock->clockSetTime(1798156740);
    $clock->clockSetTime(1798160400);

    $this->assertSame(['Runtime.evaluate', 'Page.addScriptToEvaluateOnNewDocument', 'Page.removeScriptToEvaluateOnNewDocument', 'Runtime.evaluate', 'Page.addScriptToEvaluateOnNewDocument'], array_column($clock->commands, 0));
    $this->assertSame(['identifier' => 'script-1'], $clock->commands[2][1]);
  }

  public function testResetRemovesScriptAddedThroughAnotherAdapterOnTheSameConnection(): void {
    $connection = new \stdClass();
    (new DevToolsClockTraitTestImplementation($connection))->clockSetTime(1798156740);

    $clock = new DevToolsClockTraitTestImplementation($connection);
    $clock->clockReset();

    $this->assertSame([
      ['Page.removeScriptToEvaluateOnNewDocument', ['identifier' => 'script-1']],
      ['Emulation.setTimezoneOverride', ['timezoneId' => '']],
    ], $clock->commands);
  }

  public function testResetLeavesTheScriptOfAnotherConnection(): void {
    (new DevToolsClockTraitTestImplementation(new \stdClass()))->clockSetTime(1798156740);

    $clock = new DevToolsClockTraitTestImplementation(new \stdClass());
    $clock->clockReset();

    $this->assertSame([['Emulation.setTimezoneOverride', ['timezoneId' => '']]], $clock->commands);
  }

  public function testResetRemovesTheScriptOnce(): void {
    $clock = new DevToolsClockTraitTestImplementation(new \stdClass());

    $clock->clockSetTime(1798156740);
    $clock->clockReset();
    $clock->clockReset();

    $this->assertSame(['Runtime.evaluate', 'Page.addScriptToEvaluateOnNewDocument', 'Page.removeScriptToEvaluateOnNewDocument', 'Emulation.setTimezoneOverride', 'Emulation.setTimezoneOverride'], array_column($clock->commands, 0));
  }

  public function testSetTimezoneOverridesTheTimezone(): void {
    $clock = new DevToolsClockTraitTestImplementation(new \stdClass());

    $clock->clockSetTimezone('Australia/Melbourne');

    $this->assertSame([['Emulation.setTimezoneOverride', ['timezoneId' => 'Australia/Melbourne']]], $clock->commands);
  }

  public function testSetTimeFailsWithoutScriptIdentifier(): void {
    $clock = new DevToolsClockTraitTestImplementation(new \stdClass());
    $clock->testSetScriptResult([]);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('The browser did not return an identifier for the clock script.');

    $clock->clockSetTime(1798156740);
  }

  /**
   * Reads the offset the clock script was built with.
   *
   * @param array<string, mixed> $params
   *   The parameters of the command that added the script.
   *
   * @return int
   *   The offset, in milliseconds.
   */
  protected function readOffset(array $params): int {
    $source = $params['source'] ?? NULL;
    $this->assertIsString($source);
    $this->assertSame(1, preg_match('/const offset = (-?\d+);/', $source, $matches));

    return (int) ($matches[1] ?? '');
  }

}

/**
 * Test implementation of DevToolsClockTrait.
 *
 * Records each CDP command, answers the clock expression with a set result
 * and an added script with a fresh identifier.
 */
class DevToolsClockTraitTestImplementation {

  use DevToolsClockTrait;

  /**
   * Commands sent, as method and parameter pairs.
   *
   * @var array<int, array{0: string, 1: array<string, mixed>}>
   */
  public array $commands = [];

  /**
   * The result the clock expression returns.
   *
   * @var array<string, mixed>
   */
  protected array $browserClock = ['result' => ['type' => 'number', 'value' => 0]];

  /**
   * The result an added script returns, or NULL for a fresh identifier.
   *
   * @var array<string, mixed>|null
   */
  protected ?array $scriptResult = NULL;

  /**
   * The number of scripts added.
   */
  protected int $scripts = 0;

  /**
   * Constructs an implementation sending through the given connection.
   *
   * @param object $connection
   *   The connection the clock state is kept against.
   */
  public function __construct(protected object $connection) {}

  public function testSetBrowserClock(array $browser_clock): void {
    $this->browserClock = $browser_clock;
  }

  public function testSetScriptResult(array $result): void {
    $this->scriptResult = $result;
  }

  /**
   * {@inheritdoc}
   */
  protected function devToolsConnection(): object {
    return $this->connection;
  }

  /**
   * {@inheritdoc}
   */
  protected function devToolsSend(string $method, array $params): array {
    $this->commands[] = [$method, $params];

    if ($method === 'Runtime.evaluate') {
      return $this->browserClock;
    }

    if ($method !== 'Page.addScriptToEvaluateOnNewDocument') {
      return [];
    }

    return $this->scriptResult ?? ['identifier' => 'script-' . ++$this->scripts];
  }

}
