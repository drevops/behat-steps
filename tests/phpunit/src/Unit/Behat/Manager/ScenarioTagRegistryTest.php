<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Behat\Manager;

use DrevOps\BehatSteps\Behat\Manager\ScenarioTagRegistry;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests the registry holding the running scenario's tags.
 */
#[CoversClass(ScenarioTagRegistry::class)]
class ScenarioTagRegistryTest extends UnitTestCase {

  public function testItHoldsNoTagBeforeTheFirstScenario(): void {
    $this->assertSame([], (new ScenarioTagRegistry())->getTags());
  }

  public function testTheTagsOfOneScenarioReplaceThePrevious(): void {
    $registry = new ScenarioTagRegistry();

    $registry->setTags(['javascript', 'error']);
    $this->assertSame(['javascript', 'error'], $registry->getTags());

    $registry->setTags(['api']);
    $this->assertSame(['api'], $registry->getTags());
  }

  public function testTheTagsAreReindexed(): void {
    $registry = new ScenarioTagRegistry();

    $registry->setTags([3 => 'javascript', 7 => 'error']);

    $this->assertSame(['javascript', 'error'], $registry->getTags());
  }

}
