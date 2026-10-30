<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Steps\Drupal;

use DrevOps\BehatSteps\Backend\BackendInterface;
use DrevOps\BehatSteps\Backend\Capability\CacheCapabilityInterface;
use DrevOps\BehatSteps\Backend\Capability\ContentCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drupal\Drush\DrushResult;
use DrevOps\BehatSteps\Backend\Drupal\DrushBackendInterface;
use DrevOps\BehatSteps\Extension\Registry\BackendRegistry;
use DrevOps\BehatSteps\Steps\Drupal\DrushTrait;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use DrevOps\BehatSteps\WebRawContext;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests how the Drush steps turn their step text into a Drush command.
 */
#[CoversTrait(DrushTrait::class)]
class DrushTraitTest extends UnitTestCase {

  /**
   * Tests how a command line splits into words.
   *
   * @param string $command_line
   *   The command line, as the step receives it.
   * @param array<int, string> $expected
   *   The words.
   */
  #[DataProvider('dataProviderParseWords')]
  public function testParseWords(string $command_line, array $expected): void {
    $this->assertSame($expected, (new DrushTraitTestImplementation())->callParseWords($command_line));
  }

  public static function dataProviderParseWords(): \Iterator {
    yield 'empty' => ['', []];
    yield 'whitespace only' => [" \t ", []];
    yield 'one word' => ['status', ['status']];
    yield 'several words' => ['config:get system.site name', ['config:get', 'system.site', 'name']];
    yield 'runs of whitespace' => ["  pm:list \t --status=enabled  ", ['pm:list', '--status=enabled']];
    yield 'double-quoted part' => ['user:create "Jane Doe"', ['user:create', 'Jane Doe']];
    yield 'single-quoted part' => ["sql:query 'SELECT 1'", ['sql:query', 'SELECT 1']];
    yield 'quoted part inside a word' => ['--fields="name, uid"', ['--fields=name, uid']];
    yield 'one quote inside the other' => ['"it\'s" \'say "hi"\'', ["it's", 'say "hi"']];
    yield 'empty quoted part' => ['config:set system.site slogan ""', ['config:set', 'system.site', 'slogan', '']];
    yield 'escaped quote inside double quotes' => ['php:eval "echo \"hi\";"', ['php:eval', 'echo "hi";']];
    yield 'escaped backslash inside double quotes' => ['"a\\\\b"', ['a\\b']];
    yield 'other backslash inside double quotes' => ['"a\nb"', ['a\nb']];
    yield 'backslash inside single quotes' => ["'a\\b'", ['a\\b']];
    yield 'escaped quotes outside quotes' => ['user:create \"Jane Doe\"', ['user:create', '"Jane', 'Doe"']];
    yield 'escaped space outside quotes' => ['foo\ bar', ['foo bar']];
    yield 'trailing backslash' => ['foo\\', ['foo\\']];
  }

  public function testParseWordsRejectsAnUnclosedQuote(): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('The quote in "user:create "Jane" is not closed.');

    (new DrushTraitTestImplementation())->callParseWords('user:create "Jane');
  }

  /**
   * Tests that each step runs the command with the words it parsed.
   *
   * @param string $method
   *   The step method.
   * @param array<int, string> $arguments
   *   The step arguments.
   */
  #[DataProvider('dataProviderRunPassesTheParsedWords')]
  public function testRunPassesTheParsedWords(string $method, array $arguments): void {
    $drush = $this->createMock(DrushBackendInterface::class);
    $drush->expects($this->once())->method('drushResult')->with('config:get', ['system.site', 'name'])->willReturn(new DrushResult(0, 'Behat Steps', ''));
    $context = $this->createContextWithBackend($drush);

    $context->{$method}(...$arguments);

    $this->assertSame('Behat Steps', $context->drushGetOutput());
  }

  public static function dataProviderRunPassesTheParsedWords(): \Iterator {
    yield 'arguments in the command' => ['drushRun', ['config:get system.site name']];
    yield 'arguments of their own' => ['drushRunWithArguments', ['config:get', 'system.site name']];
    yield 'arguments in both' => ['drushRunWithArguments', ['config:get system.site', 'name']];
  }

  /**
   * Tests that a run keeps stdout followed by stderr as the output.
   *
   * @param string $method
   *   The helper that runs the command.
   * @param int $exit_code
   *   The exit code the command returns.
   * @param string $stdout
   *   The stdout of the command.
   * @param string $stderr
   *   The stderr of the command.
   * @param string $expected
   *   The output kept.
   */
  #[DataProvider('dataProviderRunKeepsBothStreams')]
  public function testRunKeepsBothStreams(string $method, int $exit_code, string $stdout, string $stderr, string $expected): void {
    $drush = $this->createMock(DrushBackendInterface::class);
    $drush->method('drushResult')->willReturn(new DrushResult($exit_code, $stdout, $stderr));
    $context = $this->createContextWithBackend($drush);

    $context->{$method}('radio_station:pull');

    $this->assertSame($expected, $context->drushGetOutput());
  }

  public static function dataProviderRunKeepsBothStreams(): \Iterator {
    foreach (['drushRunExpectingSuccess' => 0, 'drushRunExpectingFailure' => 1] as $method => $exit_code) {
      yield $method . ', stdout only' => [$method, $exit_code, "Pulling episodes\n", '', "Pulling episodes\n"];
      yield $method . ', stderr only' => [$method, $exit_code, '', "Created: 1\n", "Created: 1\n"];
      yield $method . ', both' => [$method, $exit_code, "Pulling episodes\n", "Created: 1\n", "Pulling episodes\nCreated: 1\n"];
      yield $method . ', both, stdout ending in a blank line' => [$method, $exit_code, "Pulling episodes\n\n", "Created: 1\n", "Pulling episodes\n\nCreated: 1\n"];
      yield $method . ', both, stdout without a line break' => [$method, $exit_code, 'Pulling episodes', 'Created: 1', 'Pulling episodes' . PHP_EOL . 'Created: 1'];
      yield $method . ', both, stdout of 0' => [$method, $exit_code, '0', 'Created: 1', '0' . PHP_EOL . 'Created: 1'];
      yield $method . ', neither' => [$method, $exit_code, '', '', ''];
    }
  }

  public function testRunExpectingSuccessFailsOnNonZeroExit(): void {
    $drush = $this->createMock(DrushBackendInterface::class);
    $drush->method('drushResult')->willReturn(new DrushResult(3, "Pulling episodes\n", "Feed unreachable.\n"));
    $context = $this->createContextWithBackend($drush);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('The drush command "radio_station:pull" exited with code 3. Output:' . PHP_EOL . PHP_EOL . "Pulling episodes\nFeed unreachable.\n");

    $context->drushRunExpectingSuccess('radio_station:pull', 'episode');
  }

  public function testRunExpectingFailurePassesTheParsedWords(): void {
    $drush = $this->createMock(DrushBackendInterface::class);
    $drush->expects($this->once())->method('drushResult')->with('pm:uninstall', ['no_such_module'])->willReturn(new DrushResult(1, '', 'Unable to uninstall no_such_module.'));
    $context = $this->createContextWithBackend($drush);

    $context->drushRunExpectingFailure('pm:uninstall no_such_module');

    $this->assertSame('Unable to uninstall no_such_module.', $context->drushGetOutput());
  }

  /**
   * Tests that a command clears the static caches whatever its exit code.
   *
   * @param string $method
   *   The step method.
   * @param int $exit_code
   *   The exit code the command returns.
   */
  #[DataProvider('dataProviderRunClearsTheStaticCaches')]
  public function testRunClearsTheStaticCaches(string $method, int $exit_code): void {
    $drush = $this->createMock(DrushBackendInterface::class);
    $drush->method('drushResult')->willReturn(new DrushResult($exit_code, '', ''));
    /** @var \DrevOps\BehatSteps\Backend\BackendInterface&\PHPUnit\Framework\MockObject\MockObject $drupal */
    $drupal = $this->createMockForIntersectionOfInterfaces([BackendInterface::class, CacheCapabilityInterface::class, ContentCapabilityInterface::class]);
    $drupal->expects($this->once())->method('cacheClearStatic');
    $context = $this->createContextWithBackend($drush, $drupal);
    $context->backendFor(ContentCapabilityInterface::class);

    $context->{$method}('user:role:add content_editor jane');
  }

  public static function dataProviderRunClearsTheStaticCaches(): \Iterator {
    yield 'succeeding command' => ['drushRun', 0];
    yield 'failing command' => ['drushRunFailing', 1];
  }

  public function testRunRejectsAnEmptyCommand(): void {
    $drush = $this->createMock(DrushBackendInterface::class);
    $drush->expects($this->never())->method('drushResult');
    $context = $this->createContextWithBackend($drush);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('The drush command is empty.');

    $context->drushRunWithArguments('  ', 'system.site');
  }

  /**
   * Create a context over the Drush backend given and an optional second one.
   *
   * @param \DrevOps\BehatSteps\Backend\Drupal\DrushBackendInterface $drush
   *   The Drush backend, first in the scenario's order.
   * @param \DrevOps\BehatSteps\Backend\BackendInterface|null $drupal
   *   A backend after it in the order, or NULL for none.
   */
  protected function createContextWithBackend(DrushBackendInterface $drush, ?BackendInterface $drupal = NULL): DrushTraitTestImplementation {
    $backends = array_filter(['drush' => $drush, 'drupal' => $drupal]);
    $names = array_keys($backends);

    $registry = new BackendRegistry($backends);
    $registry->setScenarioBackends(array_combine($names, $names));

    $context = new DrushTraitTestImplementation();
    $context->setBackendRegistry($registry);

    return $context;
  }

}

/**
 * Test implementation of DrushTrait.
 */
class DrushTraitTestImplementation extends WebRawContext {

  use DrushTrait;

  /**
   * Split a command line into words.
   *
   * @param string $command_line
   *   The command line.
   *
   * @return array<int, string>
   *   The words.
   */
  public function callParseWords(string $command_line): array {
    return $this->drushParseWords($command_line);
  }

}
