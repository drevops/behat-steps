<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Driver;

use DrevOps\BehatSteps\Driver\BlackboxDriver;
use DrevOps\BehatSteps\Driver\Capability\CreationAliasCapabilityInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests that 'BlackboxDriver' opts out of the creation-alias capability.
 *
 * The driver declares no capabilities, so it does not implement
 * 'CreationAliasCapabilityInterface'; consumers must 'instanceof'-check
 * before calling 'getCreationAliases()'.
 *
 * @group drivers
 * @group blackbox
 * @group aliases
 */
#[CoversClass(BlackboxDriver::class)]
#[Group('drivers')]
#[Group('blackbox')]
#[Group('aliases')]
class BlackboxDriverCreationAliasesTest extends TestCase {

  /**
   * Tests that BlackboxDriver does NOT implement the capability.
   */
  public function testDoesNotImplementCreationAliasCapability(): void {
    $this->assertNotContains(CreationAliasCapabilityInterface::class, (array) class_implements(BlackboxDriver::class));
  }

}
