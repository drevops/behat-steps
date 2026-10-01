<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Behat\Prerequisite;

use DrevOps\BehatSteps\Backend\Capability\CoreCapabilityInterface;
use DrevOps\BehatSteps\Backend\Capability\ModuleCapabilityInterface;
use DrevOps\BehatSteps\Behat\Prerequisite\Prerequisite;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests the prerequisite a trait declares.
 */
#[CoversClass(Prerequisite::class)]
class PrerequisiteTest extends UnitTestCase {

  public function testCapabilityCarriesItsDescription(): void {
    $prerequisite = Prerequisite::capability(CoreCapabilityInterface::class, 'a backend runs Drupal in the Behat process');

    $this->assertSame(CoreCapabilityInterface::class, $prerequisite->capability);
    $this->assertNull($prerequisite->check);
    $this->assertSame('a backend runs Drupal in the Behat process', $prerequisite->description);
  }

  public function testCapabilityDescriptionDefaultsToNamingTheCapability(): void {
    $this->assertSame('a backend in the scenario\'s list provides "CoreCapabilityInterface"', Prerequisite::capability(CoreCapabilityInterface::class)->description);
  }

  public function testCheckTakesItsCapabilityFromTheClosure(): void {
    $check = static fn(ModuleCapabilityInterface $backend): bool => $backend->moduleIsEnabled('dblog');

    $prerequisite = Prerequisite::check($check, 'the core "dblog" module is enabled');

    $this->assertSame(ModuleCapabilityInterface::class, $prerequisite->capability);
    $this->assertSame($check, $prerequisite->check);
    $this->assertSame('the core "dblog" module is enabled', $prerequisite->description);
  }

  #[DataProvider('dataProviderRejectsMalformedDeclaration')]
  public function testRejectsMalformedDeclaration(\Closure $declare, string $message): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage($message);

    $declare();
  }

  public static function dataProviderRejectsMalformedDeclaration(): \Iterator {
    yield 'blank description' => [
      static fn(): Prerequisite => Prerequisite::capability(CoreCapabilityInterface::class, ' '),
      'A prerequisite declares a description.',
    ];
    yield 'capability that is not an interface' => [
      static fn(): Prerequisite => Prerequisite::capability(\stdClass::class),
      'The prerequisite "a backend in the scenario\'s list provides "stdClass"" names "stdClass", which is not an interface.',
    ];
    yield 'check bound to an object' => [
      static fn(): Prerequisite => Prerequisite::check((new class() {

        /**
         * What the bound check returns.
         */
        public bool $holds = TRUE;

        /**
         * Builds a closure bound to this object.
         */
        public function check(): \Closure {
          return fn(ModuleCapabilityInterface $backend): bool => $this->holds;
        }

      })->check(), 'bound'),
      'The prerequisite "bound" declares its check as a static closure.',
    ];
    yield 'check taking no parameter' => [
      static fn(): Prerequisite => Prerequisite::check(static fn(): bool => TRUE, 'none'),
      'The prerequisite "none" declares a check taking exactly 1 parameter, typed to a capability interface.',
    ];
    yield 'check taking 2 parameters' => [
      static fn(): Prerequisite => Prerequisite::check(static fn(ModuleCapabilityInterface $first, ModuleCapabilityInterface $second): bool => TRUE, 'two'),
      'The prerequisite "two" declares a check taking exactly 1 parameter, typed to a capability interface.',
    ];
    yield 'check taking an untyped parameter' => [
      static fn(): Prerequisite => Prerequisite::check(static fn($backend): bool => TRUE, 'untyped'),
      'The prerequisite "untyped" declares a check taking exactly 1 parameter, typed to a capability interface.',
    ];
    yield 'check taking a nullable parameter' => [
      static fn(): Prerequisite => Prerequisite::check(static fn(?ModuleCapabilityInterface $backend): bool => TRUE, 'nullable'),
      'The prerequisite "nullable" declares a check taking exactly 1 parameter, typed to a capability interface.',
    ];
    yield 'check taking a union parameter' => [
      static fn(): Prerequisite => Prerequisite::check(static fn(ModuleCapabilityInterface|CoreCapabilityInterface $backend): bool => TRUE, 'union'),
      'The prerequisite "union" declares a check taking exactly 1 parameter, typed to a capability interface.',
    ];
    yield 'check taking a class' => [
      static fn(): Prerequisite => Prerequisite::check(static fn(\stdClass $backend): bool => TRUE, 'class'),
      'The prerequisite "class" names "stdClass", which is not an interface.',
    ];
    yield 'check typed to another capability' => [
      static fn(): Prerequisite => new Prerequisite(CoreCapabilityInterface::class, static fn(ModuleCapabilityInterface $backend): bool => TRUE, 'other'),
      sprintf('The prerequisite "other" declares a check whose parameter is typed to "%s" rather than to its capability "%s".', ModuleCapabilityInterface::class, CoreCapabilityInterface::class),
    ];
    yield 'check returning true rather than bool' => [
      static fn(): Prerequisite => Prerequisite::check(static fn(ModuleCapabilityInterface $backend): true => TRUE, 'always'),
      'The prerequisite "always" declares a check returning "bool".',
    ];
    yield 'check returning another type' => [
      static fn(): Prerequisite => Prerequisite::check(static fn(ModuleCapabilityInterface $backend): int => 1, 'integer'),
      'The prerequisite "integer" declares a check returning "bool".',
    ];
  }

}
