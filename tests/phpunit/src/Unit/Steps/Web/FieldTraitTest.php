<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Steps\Web;

use Behat\Gherkin\Node\TableNode;
use Behat\Mink\Driver\DriverInterface;
use Behat\Mink\Element\DocumentElement;
use Behat\Mink\Element\NodeElement;
use Behat\Mink\Exception\ElementNotFoundException;
use Behat\Mink\Exception\UnsupportedDriverActionException;
use Behat\Mink\Mink;
use Behat\Mink\Session;
use DrevOps\BehatSteps\Behat\Context\WebRawContext;
use DrevOps\BehatSteps\Behat\Mink\Capability\JavascriptCapabilityInterface;
use DrevOps\BehatSteps\Steps\Web\FieldTrait;
use DrevOps\BehatSteps\Tests\Unit\Behat\Mink\Fixtures\AnyDriverAdapter;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Tests for FieldTrait.
 */
#[CoversTrait(FieldTrait::class)]
class FieldTraitTest extends UnitTestCase {

  /**
   * A test implementation of FieldTrait.
   */
  protected FieldTraitTestImplementation $testObject;

  /**
   * The page served by the session under test.
   */
  protected DocumentElement&MockObject $page;

  /**
   * The driver behind the session under test.
   */
  protected DriverInterface&MockObject $driver;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->page = $this->createMock(DocumentElement::class);
    $this->driver = $this->createMock(DriverInterface::class);

    $session = $this->createMock(Session::class);
    $session->method('getPage')->willReturn($this->page);
    $session->method('getDriver')->willReturn($this->driver);

    $mink = new Mink(['default' => $session]);
    $mink->setDefaultSessionName('default');

    $this->testObject = new FieldTraitTestImplementation();
    $this->testObject->setMink($mink);
  }

  public function testFillMultiValueRequiresJavascriptDriver(): void {
    // No adapter supports a bare driver mock, so the step resolves no
    // JavaScript capability.
    $this->expectException(UnsupportedDriverActionException::class);
    $this->expectExceptionMessage(sprintf('No browser capability "%s" is available', JavascriptCapabilityInterface::class));

    $this->testObject->fieldFillMultiValue('Tags', new TableNode([['value'], ['Drupal']]));
  }

  public function testFillMultiValueThrowsWhenInputRowIsMissing(): void {
    $this->testObject->getBrowserResolver()->registerAdapter(AnyDriverAdapter::class);

    // 0 existing inputs count as 1 row, so no "Add another item" click is
    // attempted. The first value then has no input to fill.
    $wrapper = $this->createMock(NodeElement::class);
    $wrapper->method('findAll')->willReturn([]);
    $this->page->method('find')->willReturn($wrapper);

    $this->expectException(ElementNotFoundException::class);
    $this->expectExceptionMessage('Input row of the multi-value field "Tags" with index "0" not found.');

    $this->testObject->fieldFillMultiValue('Tags', new TableNode([['value'], ['Drupal']]));
  }

  /**
   * Tests that the validation tag is read from the scenario and its feature.
   *
   * @param list<string> $scenario_tags
   *   Tags on the scenario.
   * @param list<string> $feature_tags
   *   Tags on the feature.
   * @param bool $expected
   *   Whether validation is expected to be disabled on every form.
   */
  #[DataProvider('dataProviderBeforeScenarioReadsValidationTag')]
  public function testBeforeScenarioReadsValidationTag(array $scenario_tags, array $feature_tags, bool $expected): void {
    $this->testObject->fieldBeforeScenario($this->createBeforeScenarioScope($scenario_tags, $feature_tags));

    $this->assertSame($expected, $this->testObject->isAllFormValidationDisabled());
  }

  public static function dataProviderBeforeScenarioReadsValidationTag(): \Iterator {
    yield 'on neither' => [['javascript'], ['api'], FALSE];
    yield 'on the scenario' => [['disable-form-validation'], [], TRUE];
    yield 'on the feature' => [[], ['disable-form-validation'], TRUE];
    yield 'on both' => [['disable-form-validation'], ['disable-form-validation'], TRUE];
    yield 'skipped on the feature' => [['disable-form-validation'], ['behat-steps-skip:FieldTrait'], FALSE];
  }

}

/**
 * Test implementation of FieldTrait.
 */
class FieldTraitTestImplementation extends WebRawContext {

  use FieldTrait;

  /**
   * Whether validation is disabled on every form of the scenario.
   */
  public function isAllFormValidationDisabled(): bool {
    return $this->fieldDisableAllFormValidation;
  }

}
