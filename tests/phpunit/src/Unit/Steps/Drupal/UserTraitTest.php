<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Steps\Drupal;

use DrevOps\BehatSteps\Backend\Capability\UserCapabilityInterface;
use DrevOps\BehatSteps\Backend\Entity\EntityStub;
use DrevOps\BehatSteps\Backend\Entity\EntityStubInterface;
use DrevOps\BehatSteps\Steps\Drupal\UserTrait;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use DrevOps\BehatSteps\WebRawContext;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests for UserTrait.
 */
#[CoversTrait(UserTrait::class)]
class UserTraitTest extends UnitTestCase {

  /**
   * Tests which role lists name only the anonymous role.
   */
  #[DataProvider('dataProviderIsAnonymousRole')]
  public function testIsAnonymousRole(string $roles, bool $expected): void {
    $this->assertSame($expected, (new UserTraitTestImplementation())->userIsAnonymousRole($roles));
  }

  public static function dataProviderIsAnonymousRole(): \Iterator {
    yield 'machine name' => ['anonymous', TRUE];
    yield 'label' => ['anonymous user', TRUE];
    yield 'capitalized' => ['Anonymous', TRUE];
    yield 'upper case label' => ['ANONYMOUS USER', TRUE];
    yield 'surrounding whitespace' => ['  anonymous  ', TRUE];
    yield 'trailing comma' => ['anonymous,', TRUE];
    yield 'beside another role' => ['anonymous, editor', FALSE];
    yield 'repeated' => ['anonymous, anonymous', FALSE];
    yield 'authenticated' => ['authenticated', FALSE];
    yield 'another role' => ['editor', FALSE];
    yield 'partial name' => ['anonymous editor', FALSE];
    yield 'empty' => ['', FALSE];
  }

  /**
   * Tests that a role list parses into the roles an account can be assigned.
   *
   * @param string $roles
   *   The role list.
   * @param list<string> $expected
   *   The parsed roles, in order.
   */
  #[DataProvider('dataProviderParseRoles')]
  public function testParseRoles(string $roles, array $expected): void {
    $this->assertSame($expected, (new UserTraitTestImplementation())->userParseRoles($roles));
  }

  public static function dataProviderParseRoles(): \Iterator {
    yield 'single role' => ['editor', ['editor']];
    yield 'several roles' => ['editor, admin', ['editor', 'admin']];
    yield 'authenticated skipped' => ['authenticated, editor, Authenticated User', ['editor']];
    yield 'only authenticated' => ['authenticated', []];
    yield 'empty entries skipped' => ['editor, , admin,', ['editor', 'admin']];
    yield 'empty' => ['', []];
  }

  /**
   * Tests that a role list naming the anonymous role fails to parse.
   */
  #[DataProvider('dataProviderParseRolesRejectsAnonymous')]
  public function testParseRolesRejectsAnonymous(string $roles, string $message): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage($message);

    (new UserTraitTestImplementation())->userParseRoles($roles);
  }

  public static function dataProviderParseRolesRejectsAnonymous(): \Iterator {
    yield 'machine name' => ['anonymous', 'The role "anonymous" cannot be assigned to a user.'];
    yield 'label' => ['Anonymous User', 'The role "Anonymous User" cannot be assigned to a user.'];
    yield 'first in a list' => ['anonymous, editor', 'The role "anonymous" cannot be assigned to a user.'];
    yield 'after another role' => ['editor, anonymous', 'The role "anonymous" cannot be assigned to a user.'];
  }

  /**
   * Tests that roles reach the backend in list order.
   */
  public function testAssignRoles(): void {
    $assigned = [];
    $backend = $this->createMock(UserCapabilityInterface::class);
    $backend->method('addUserRole')->willReturnCallback(static function (EntityStubInterface $stub, string $role) use (&$assigned): void {
      $assigned[] = $role;
    });

    (new UserTraitTestImplementation())->userAssignRoles($backend, new EntityStub('user'), 'editor, authenticated, admin');

    $this->assertSame(['editor', 'admin'], $assigned);
  }

  /**
   * Tests that a list naming the anonymous role assigns none of its roles.
   */
  #[DataProvider('dataProviderAssignRolesRejectsAnonymous')]
  public function testAssignRolesRejectsAnonymous(string $roles, string $message): void {
    $backend = $this->createMock(UserCapabilityInterface::class);
    $backend->expects($this->never())->method('addUserRole');

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage($message);

    (new UserTraitTestImplementation())->userAssignRoles($backend, new EntityStub('user'), $roles);
  }

  public static function dataProviderAssignRolesRejectsAnonymous(): \Iterator {
    yield from static::dataProviderParseRolesRejectsAnonymous();
  }

}

/**
 * Test implementation of UserTrait.
 */
class UserTraitTestImplementation extends WebRawContext {

  use UserTrait;

}
