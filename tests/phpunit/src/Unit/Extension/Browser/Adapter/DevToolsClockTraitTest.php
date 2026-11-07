<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Extension\Browser\Adapter;

use DrevOps\BehatSteps\Extension\Browser\Adapter\DevToolsClockTrait;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversTrait;

/**
 * Tests overriding the browser clock and timezone over CDP.
 */
#[CoversTrait(DevToolsClockTrait::class)]
class DevToolsClockTraitTest extends UnitTestCase {

  public function testSetTimeAddsScriptOffsetFromTheRealClock(): void {
    $clock = new DevToolsClockTraitTestImplementation(new \stdClass());

    $before = (int) round(microtime(TRUE) * 1000);
    $clock->clockSetTime(1798156740);
    $after = (int) round(microtime(TRUE) * 1000);

    $this->assertCount(1, $clock->commands);
    [$method, $params] = $clock->commands[0];
    $this->assertSame('Page.addScriptToEvaluateOnNewDocument', $method);
    $this->assertIsString($params['source']);
    $this->assertSame(1, preg_match('/const offset = (-?\d+);/', $params['source'], $matches));

    $offset = (int) ($matches[1] ?? '');
    $this->assertGreaterThanOrEqual(1798156740000 - $after, $offset);
    $this->assertLessThanOrEqual(1798156740000 - $before, $offset);
  }

  public function testSetTimeReplacesTheScriptAddedBefore(): void {
    $clock = new DevToolsClockTraitTestImplementation(new \stdClass());

    $clock->clockSetTime(1798156740);
    $clock->clockSetTime(1798160400);

    $this->assertSame(['Page.addScriptToEvaluateOnNewDocument', 'Page.removeScriptToEvaluateOnNewDocument', 'Page.addScriptToEvaluateOnNewDocument'], array_column($clock->commands, 0));
    $this->assertSame(['identifier' => '1'], $clock->commands[1][1]);
  }

  public function testResetRemovesScriptAddedThroughAnotherAdapterOnTheSameConnection(): void {
    $connection = new \stdClass();
    (new DevToolsClockTraitTestImplementation($connection))->clockSetTime(1798156740);

    $clock = new DevToolsClockTraitTestImplementation($connection);
    $clock->clockReset();

    $this->assertSame([
      ['Page.removeScriptToEvaluateOnNewDocument', ['identifier' => '1']],
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

    $this->assertSame(['Page.addScriptToEvaluateOnNewDocument', 'Page.removeScriptToEvaluateOnNewDocument', 'Emulation.setTimezoneOverride', 'Emulation.setTimezoneOverride'], array_column($clock->commands, 0));
  }

  public function testSetTimezoneOverridesTheTimezone(): void {
    $clock = new DevToolsClockTraitTestImplementation(new \stdClass());

    $clock->clockSetTimezone('Australia/Melbourne');

    $this->assertSame([['Emulation.setTimezoneOverride', ['timezoneId' => 'Australia/Melbourne']]], $clock->commands);
  }

  public function testSetTimeFailsWithoutScriptIdentifier(): void {
    $clock = new DevToolsClockTraitTestImplementation(new \stdClass());
    $clock->testSetResult([]);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('The browser did not return an identifier for the clock script.');

    $clock->clockSetTime(1798156740);
  }

}

/**
 * Test implementation of DevToolsClockTrait.
 *
 * Records each CDP command and answers an added script with an identifier.
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
   * The result an added script returns, or NULL for a fresh identifier.
   *
   * @var array<string, mixed>|null
   */
  protected ?array $result = NULL;

  /**
   * Constructs an implementation sending through the given connection.
   *
   * @param object $connection
   *   The connection the clock state is kept against.
   */
  public function __construct(protected object $connection) {}

  public function testSetResult(array $result): void {
    $this->result = $result;
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

    if ($method !== 'Page.addScriptToEvaluateOnNewDocument') {
      return [];
    }

    return $this->result ?? ['identifier' => (string) count($this->commands)];
  }

}
