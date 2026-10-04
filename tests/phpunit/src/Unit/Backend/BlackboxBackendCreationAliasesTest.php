<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Backend;

use DrevOps\BehatSteps\Backend\BlackboxBackend;
use DrevOps\BehatSteps\Backend\Capability\CreationAliasCapabilityInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests that 'BlackboxBackend' lacks the creation-alias capability.
 */
#[CoversClass(BlackboxBackend::class)]
#[Group('backends')]
#[Group('blackbox')]
#[Group('aliases')]
class BlackboxBackendCreationAliasesTest extends TestCase {

  public function testDoesNotImplementCreationAliasCapability(): void {
    $this->assertNotContains(CreationAliasCapabilityInterface::class, (array) class_implements(BlackboxBackend::class));
  }

}
