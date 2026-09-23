<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Steps\Web;

use Behat\Gherkin\Node\TableNode;
use DrevOps\BehatSteps\Behat\Context\RawContext;
use DrevOps\BehatSteps\Steps\Web\RandomTrait;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversTrait;

/**
 * Tests that a scenario can switch the random token transform off.
 */
#[CoversTrait(RandomTrait::class)]
class RandomTraitTest extends UnitTestCase {

  /**
   * A test implementation of RandomTrait.
   */
  protected RandomTraitTestImplementation $testObject;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->testObject = new RandomTraitTestImplementation();
  }

  public function testSkippedScenarioLeavesTokensUntouched(): void {
    $this->testObject->randomBeforeScenario($this->createBeforeScenarioScope(['behat-steps-skip:RandomTrait']));

    $this->assertSame('[?title]', $this->testObject->randomTransformValue('[?title]'));

    $table = new TableNode([['title'], ['[?title]']]);
    $this->assertSame([['title'], ['[?title]']], $this->testObject->randomTransformTable($table)->getRows());
  }

  public function testUnskippedScenarioResolvesTokens(): void {
    $this->testObject->randomBeforeScenario($this->createBeforeScenarioScope());

    $resolved = $this->testObject->randomTransformValue('[?title]');

    $this->assertIsString($resolved);
    $this->assertNotSame('[?title]', $resolved);
    $this->assertSame(10, strlen($resolved));

    // One value per token for the whole scenario, so the table cell holds the
    // same string the scalar argument resolved to.
    $table = new TableNode([['title'], ['[?title]']]);
    $this->assertSame([['title'], [$resolved]], $this->testObject->randomTransformTable($table)->getRows());
  }

  public function testTheConfiguredSwitchTurnsTheTransformOff(): void {
    $context = new RandomTraitTestImplementation(['random' => ['enabled' => FALSE]]);
    $context->randomBeforeScenario($this->createBeforeScenarioScope());

    $this->assertSame('[?title]', $context->randomTransformValue('[?title]'));
  }

}

/**
 * Test implementation of RandomTrait.
 */
class RandomTraitTestImplementation extends RawContext {

  use RandomTrait;

}
