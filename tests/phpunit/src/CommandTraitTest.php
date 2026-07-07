<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests;

use DrevOps\BehatSteps\CommandTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests for CommandTrait.
 */
#[CoversClass(CommandTrait::class)]
class CommandTraitTest extends UnitTestCase {

  /**
   * A test implementation of CommandTrait.
   *
   * @var \DrevOps\BehatSteps\Tests\CommandTraitTestImplementation
   */
  protected $testObject;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->testObject = new CommandTraitTestImplementation();
  }

  public function testRunCapturesOutputExitCodeAndDuration(): void {
    $this->testObject->commandRun('echo hello');

    $this->assertSame(0, $this->testObject->exitCode());
    $this->assertStringContainsString('hello', $this->testObject->stdout());
    $this->assertSame('', $this->testObject->stderr());
    $this->assertGreaterThan(0.0, $this->testObject->duration());

    // Pass-through assertions do not throw.
    $this->testObject->commandAssertSuccess();
    $this->testObject->commandAssertExitCode('0');
    $this->testObject->commandAssertOutputContains('hello');
    $this->testObject->commandAssertOutputEquals('hello');
    $this->testObject->commandAssertOutputNotContains('goodbye');
    $this->testObject->commandAssertDurationLessThan('60');
    $this->testObject->commandAssertDurationMoreThan('0');
  }

  public function testRunCapturesErrorOutputSeparately(): void {
    $this->testObject->commandRun('echo oops >&2');

    $this->assertStringContainsString('oops', $this->testObject->stderr());
    $this->assertSame('', trim($this->testObject->stdout()));

    $this->testObject->commandAssertErrorOutputContains('oops');
    $this->testObject->commandAssertOutputNotContains('oops');
  }

  public function testRunCapturesNonZeroExitCode(): void {
    $this->testObject->commandRun('exit 3');

    $this->assertSame(3, $this->testObject->exitCode());

    $this->testObject->commandAssertFailure();
    $this->testObject->commandAssertExitCode('3');
  }

  public function testRunReplacesPreviousState(): void {
    $this->testObject->commandRun('echo one');
    $this->testObject->commandRun('echo two');

    $this->assertStringContainsString('two', $this->testObject->stdout());
    $this->assertStringNotContainsString('one', $this->testObject->stdout());
  }

  public function testBeforeScenarioResetsState(): void {
    $this->testObject->commandRun('echo hello');
    $this->testObject->commandBeforeScenario();

    $this->assertNull($this->testObject->exitCode());
    $this->assertSame('', $this->testObject->stdout());
  }

  public function testAfterScenarioResetsState(): void {
    $this->testObject->commandRun('echo hello');
    $this->testObject->commandAfterScenario();

    $this->assertNull($this->testObject->exitCode());
    $this->assertSame('', $this->testObject->stdout());
    $this->assertSame('', $this->testObject->stderr());
    $this->assertSame(0.0, $this->testObject->duration());
  }

  #[DataProvider('dataProviderAssertionFailures')]
  public function testAssertionFailures(?string $command, string $method, array $args, string $exception_class, string $message_fragment): void {
    if ($command !== NULL) {
      $this->testObject->commandRun($command);
    }

    try {
      $this->testObject->{$method}(...$args);
    }
    catch (\Exception $e) {
      $this->assertSame($exception_class, $e::class);
      $this->assertStringContainsString($message_fragment, $e->getMessage());

      return;
    }

    $this->fail(sprintf('Expected "%s" to throw an exception, but none was thrown.', $method));
  }

  public static function dataProviderAssertionFailures(): array {
    return [
      'succeed on a failed command' => ['exit 1', 'commandAssertSuccess', [], \Exception::class, 'Expected the command to succeed, but it exited with code 1.'],
      'fail on a successful command' => ['echo hello', 'commandAssertFailure', [], \Exception::class, 'Expected the command to fail, but it exited with code 0.'],
      'exit code mismatch' => ['echo hello', 'commandAssertExitCode', ['3'], \Exception::class, 'Expected the command to exit with code 3, but it exited with code 0.'],
      'output does not contain' => ['echo hello', 'commandAssertOutputContains', ['goodbye'], \Exception::class, 'Expected the command output to contain "goodbye"'],
      'output unexpectedly contains' => ['echo hello', 'commandAssertOutputNotContains', ['hello'], \Exception::class, 'Expected the command output to not contain "hello"'],
      'output does not equal' => ['echo hello', 'commandAssertOutputEquals', ['goodbye'], \Exception::class, 'Expected the command output to be "goodbye", but got "hello".'],
      'error output does not contain' => ['echo hello', 'commandAssertErrorOutputContains', ['missing'], \Exception::class, 'Expected the command error output to contain "missing"'],
      'duration exceeds the limit' => ['sleep 2', 'commandAssertDurationLessThan', ['1'], \Exception::class, 'Expected the command to complete in less than 1 seconds'],
      'duration below the floor' => ['echo fast', 'commandAssertDurationMoreThan', ['5'], \Exception::class, 'Expected the command to complete in more than 5 seconds'],
      'assertion before any command' => [NULL, 'commandAssertSuccess', [], \RuntimeException::class, 'No command has been run.'],
    ];
  }

}

/**
 * Test implementation of CommandTrait.
 */
class CommandTraitTestImplementation {

  use CommandTrait;

  /**
   * Expose the captured exit code.
   */
  public function exitCode(): ?int {
    return $this->commandExitCode;
  }

  /**
   * Expose the captured standard output.
   */
  public function stdout(): string {
    return $this->commandStdout;
  }

  /**
   * Expose the captured error output.
   */
  public function stderr(): string {
    return $this->commandStderr;
  }

  /**
   * Expose the captured duration.
   */
  public function duration(): float {
    return $this->commandDuration;
  }

}
