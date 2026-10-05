<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Backend\Core;

use DrevOps\BehatSteps\Backend\Core\Core;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests permission label and machine name conversion in the Core backend.
 */
#[CoversClass(Core::class)]
#[Group('core')]
class CorePermissionsTest extends UnitTestCase {

  /**
   * Tests that human-readable titles are converted to machine names.
   *
   * Drupal returns permission titles as TranslatableMarkup objects. A strict
   * comparison against a plain string label never matches, so the backend
   * casts the title to string before the lookup.
   */
  public function testConvertPermissionsMapsStringableTitlesToMachineNames(): void {
    $core = new InjectedPermissionsCore(__DIR__, 'default');
    $core->testSetPermissions([
      'administer content types' => [
        'title' => $this->createStringable('Administer content types'),
      ],
      'administer users' => [
        'title' => $this->createStringable('Administer users'),
      ],
    ]);

    $permissions = ['Administer content types', 'Administer users'];
    $this->callConvertPermissions($core, $permissions);

    $this->assertSame(['administer content types', 'administer users'], $permissions);
  }

  public function testConvertPermissionsLeavesMachineNamesAlone(): void {
    $core = new InjectedPermissionsCore(__DIR__, 'default');
    $core->testSetPermissions([
      'administer users' => [
        'title' => $this->createStringable('Administer users'),
      ],
    ]);

    $permissions = ['administer users'];
    $this->callConvertPermissions($core, $permissions);

    $this->assertSame(['administer users'], $permissions);
  }

  public function testCheckPermissionsAcceptsValidMachineNames(): void {
    $core = new InjectedPermissionsCore(__DIR__, 'default');
    $core->testSetPermissions([
      'administer users' => ['title' => 'Administer users'],
      'access content' => ['title' => 'Access content'],
    ]);

    $permissions = ['administer users', 'access content'];
    $this->callCheckPermissions($core, $permissions);

    $this->assertSame(['administer users', 'access content'], $permissions);
  }

  public function testCheckPermissionsThrowsForUnknownPermission(): void {
    $core = new InjectedPermissionsCore(__DIR__, 'default');
    $core->testSetPermissions([
      'administer users' => ['title' => 'Administer users'],
    ]);

    $permissions = ['administer users', 'unknown permission'];

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Invalid permission "unknown permission".');

    $this->callCheckPermissions($core, $permissions);
  }

  /**
   * Invokes the protected 'convertPermissions()' method by reference.
   *
   * @param \DrevOps\BehatSteps\Backend\Core\Core $core
   *   The core instance to invoke the method on.
   * @param array<string> &$permissions
   *   The permissions array to convert.
   */
  protected function callConvertPermissions(Core $core, array &$permissions): void {
    $method = new \ReflectionMethod($core, 'convertPermissions');
    $method->invokeArgs($core, [&$permissions]);
  }

  /**
   * Invokes the protected 'checkPermissions()' method by reference.
   *
   * @param \DrevOps\BehatSteps\Backend\Core\Core $core
   *   The core instance to invoke the method on.
   * @param array<string> &$permissions
   *   The permissions array to check.
   */
  protected function callCheckPermissions(Core $core, array &$permissions): void {
    $method = new \ReflectionMethod($core, 'checkPermissions');
    $method->invokeArgs($core, [&$permissions]);
  }

  /**
   * Returns an anonymous Stringable that mimics a Drupal TranslatableMarkup.
   */
  protected function createStringable(string $label): object {
    return new readonly class($label) {

      public function __construct(protected string $label) {}

      public function __toString(): string {
        return $this->label;
      }

    };
  }

}

/**
 * Subclass that overrides 'getAllPermissions()'.
 */
class InjectedPermissionsCore extends Core {

  /**
   * Stored permissions keyed by machine name.
   *
   * @var array<string, mixed>
   */
  protected array $permissions = [];

  /**
   * Sets the permissions returned by 'getAllPermissions()'.
   *
   * @param array<string, mixed> $permissions
   *   The permissions to set.
   */
  public function testSetPermissions(array $permissions): void {
    $this->permissions = $permissions;
  }

  /**
   * {@inheritdoc}
   */
  protected function getAllPermissions(): array {
    return $this->permissions;
  }

}
