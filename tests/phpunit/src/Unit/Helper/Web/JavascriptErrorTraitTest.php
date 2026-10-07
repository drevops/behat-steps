<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Helper\Web;

use Behat\Mink\Driver\CoreDriver;
use Behat\Mink\Session;
use Behat\MinkExtension\Context\RawMinkContext;
use DrevOps\BehatSteps\Helper\Web\JavascriptErrorTrait;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests for JavascriptErrorTrait.
 */
#[CoversTrait(JavascriptErrorTrait::class)]
class JavascriptErrorTraitTest extends UnitTestCase {

  /**
   * A test implementation of JavascriptErrorTrait.
   */
  protected JavascriptErrorTraitTestImplementation $testObject;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->testObject = new JavascriptErrorTraitTestImplementation();
  }

  public function testTheRegistryStartsEmpty(): void {
    $this->assertSame([], $this->testObject->callGetAll());
    $this->assertSame([], $this->testObject->callGetMessages());
  }

  public function testRecordedErrorsAreKeptPerUrlInOrder(): void {
    $this->testObject->callRecord('http://example.com/a', [['message' => 'A1']]);
    $this->testObject->callRecord('http://example.com/b', [['message' => 'B1']]);
    $this->testObject->callRecord('http://example.com/a', [['message' => 'A2']]);

    $this->assertSame([
      'http://example.com/a' => [['message' => 'A1'], ['message' => 'A2']],
      'http://example.com/b' => [['message' => 'B1']],
    ], $this->testObject->callGetAll());
  }

  public function testRecordingNoErrorsLeavesTheRegistryEmpty(): void {
    $this->testObject->callRecord('http://example.com/a', []);

    $this->assertSame([], $this->testObject->callGetAll());
  }

  public function testMessagesFollowTheRecordingOrderAndSkipErrorsWithoutOne(): void {
    $this->testObject->callRecord('http://example.com/a', [['message' => 'A1'], ['source' => 'no message']]);
    $this->testObject->callRecord('http://example.com/b', [['message' => 'B1']]);

    $this->assertSame(['A1', 'B1'], $this->testObject->callGetMessages());
  }

  public function testClearDropsEveryRecordedError(): void {
    $this->testObject->callRecord('http://example.com/a', [['message' => 'A1']]);

    $this->testObject->callClear();

    $this->assertSame([], $this->testObject->callGetAll());
  }

  #[DataProvider('dataProviderReadBuffer')]
  public function testReadBuffer(mixed $buffer, array $expected): void {
    $this->testObject->session->buffer = $buffer;

    $this->assertSame($expected, $this->testObject->callReadBuffer());
  }

  public static function dataProviderReadBuffer(): array {
    return [
      'errors' => [[['message' => 'A1']], [['message' => 'A1']]],
      'empty buffer' => [[], []],
      'no collector on the page' => [NULL, []],
    ];
  }

  public function testReadBufferEvaluatesTheCollectorBuffer(): void {
    $this->testObject->callReadBuffer();

    $this->assertStringContainsString('window.jsErrors', (string) $this->testObject->session->evaluated);
  }

  public function testClearBufferEmptiesTheCollectorBuffer(): void {
    $this->testObject->callClearBuffer();

    $this->assertSame('window.jsErrors = [];', $this->testObject->session->executed);
  }

}

/**
 * Test implementation of JavascriptErrorTrait.
 *
 * Exposes the protected helper methods under the test.
 */
class JavascriptErrorTraitTestImplementation extends RawMinkContext {

  use JavascriptErrorTrait;

  /**
   * The fake session returned by getSession().
   */
  public JavascriptErrorFakeSession $session;

  public function __construct() {
    $this->session = new JavascriptErrorFakeSession();
  }

  public function getSession(mixed $name = NULL): Session {
    return $this->session;
  }

  /**
   * Reads the page's error buffer.
   *
   * @return array<int, array<string, mixed>>
   *   The buffered errors.
   */
  public function callReadBuffer(): array {
    return $this->javascriptErrorReadBuffer();
  }

  public function callClearBuffer(): void {
    $this->javascriptErrorClearBuffer();
  }

  /**
   * Records errors collected from a page.
   *
   * @param string $url
   *   The URL of the page.
   * @param array<int, array<string, mixed>> $errors
   *   The errors.
   */
  public function callRecord(string $url, array $errors): void {
    $this->javascriptErrorRecord($url, $errors);
  }

  /**
   * Returns the recorded errors.
   *
   * @return array<string, array<int, array<string, mixed>>>
   *   The errors, keyed by page URL.
   */
  public function callGetAll(): array {
    return $this->javascriptErrorGetAll();
  }

  /**
   * Returns the message of every recorded error.
   *
   * @return array<int, string>
   *   The messages.
   */
  public function callGetMessages(): array {
    return $this->javascriptErrorGetMessages();
  }

  public function callClear(): void {
    $this->javascriptErrorClear();
  }

}

/**
 * A fake Mink session that returns a set buffer and records the scripts run.
 */
class JavascriptErrorFakeSession extends Session {

  /**
   * The value evaluateScript() returns.
   */
  public mixed $buffer = [];

  /**
   * The last script passed to evaluateScript().
   */
  public ?string $evaluated = NULL;

  /**
   * The last script passed to executeScript().
   */
  public ?string $executed = NULL;

  public function __construct() {
    parent::__construct(new JavascriptErrorFakeDriver());
  }

  public function evaluateScript(string $script): mixed {
    $this->evaluated = $script;

    return $this->buffer;
  }

  public function executeScript(string $script): void {
    $this->executed = $script;
  }

}

/**
 * A stand-in driver the fake session wraps.
 */
class JavascriptErrorFakeDriver extends CoreDriver {}
