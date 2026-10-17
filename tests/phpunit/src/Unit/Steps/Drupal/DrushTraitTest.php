<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Steps\Drupal;

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
    yield 'quotes the Gherkin parser escaped' => ['user:create \"Jane Doe\"', ['user:create', 'Jane Doe']];
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
    $drush->expects($this->once())->method('drush')->with('config:get', ['system.site', 'name'])->willReturn('Behat Steps');
    $context = $this->createContextWithBackend($drush);

    $context->{$method}(...$arguments);

    $this->assertSame('Behat Steps', $context->drushGetOutput());
  }

  public static function dataProviderRunPassesTheParsedWords(): \Iterator {
    yield 'arguments in the command' => ['drushRun', ['config:get system.site name']];
    yield 'arguments of their own' => ['drushRunWithArguments', ['config:get', 'system.site name']];
    yield 'arguments in both' => ['drushRunWithArguments', ['config:get system.site', 'name']];
  }

  public function testRunExpectingFailurePassesTheParsedWords(): void {
    $drush = $this->createMock(DrushBackendInterface::class);
    $drush->expects($this->once())->method('drushResult')->with('pm:uninstall', ['no_such_module'])->willReturn(new DrushResult(1, '', 'Unable to uninstall no_such_module.'));
    $context = $this->createContextWithBackend($drush);

    $context->drushRunExpectingFailure('pm:uninstall no_such_module');

    $this->assertSame('Unable to uninstall no_such_module.', $context->drushGetOutput());
  }

  public function testRunRejectsAnEmptyCommand(): void {
    $drush = $this->createMock(DrushBackendInterface::class);
    $drush->expects($this->never())->method('drush');
    $context = $this->createContextWithBackend($drush);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('The drush command is empty.');

    $context->drushRunWithArguments('  ', 'system.site');
  }

  /**
   * Create a context whose only backend is the Drush backend given.
   *
   * @param \DrevOps\BehatSteps\Backend\Drupal\DrushBackendInterface $drush
   *   The Drush backend.
   */
  protected function createContextWithBackend(DrushBackendInterface $drush): DrushTraitTestImplementation {
    $registry = new BackendRegistry(['drush' => $drush]);
    $registry->setScenarioBackends(['drush' => 'drush']);

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
