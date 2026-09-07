<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Driver\Unit;

use DrevOps\BehatSteps\Driver\DriverInterface;
use DrevOps\BehatSteps\Driver\DrushDriver;
use DrevOps\BehatSteps\Driver\DrushDriverInterface;
use DrevOps\BehatSteps\Driver\Exception\BootstrapException;
use DrevOps\BehatSteps\Tests\Driver\Unit\Fixtures\TestDrushDriver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the Drush driver.
 *
 * @group drivers
 * @group drush
 */
#[Group('drivers')]
#[Group('drush')]
class DrushDriverTest extends TestCase {

  /**
   * Tests that DrushDriver implements its composite contract.
   */
  public function testImplementsDrushDriverInterface(): void {
    $interfaces = (array) class_implements(DrushDriver::class);

    $this->assertContains(DrushDriverInterface::class, $interfaces);
    $this->assertContains(DriverInterface::class, $interfaces);
  }

  /**
   * Tests instantiating the driver with only an alias.
   */
  public function testWithAlias(): void {
    $driver = new DrushDriver('alias');
    $this->assertEquals('alias', $driver->alias, 'The drush alias was not properly set.');
  }

  /**
   * Tests instantiating the driver with a prefixed alias.
   */
  public function testWithAliasPrefix(): void {
    $driver = new DrushDriver('@alias');
    $this->assertEquals('alias', $driver->alias, 'The drush alias did not remove the "@" prefix.');
  }

  /**
   * Tests instantiating the driver with only the root path.
   */
  public function testWithRoot(): void {
    // Bit of a hack here to use the path to this file, but all the driver cares
    // about during initialization is that the root be a directory.
    $driver = new DrushDriver('', __FILE__);
    $this->assertEquals(__FILE__, $driver->root);
  }

  /**
   * Tests instantiating the driver with missing alias and root path.
   */
  public function testWithNeither(): void {
    $this->expectException(BootstrapException::class);
    new DrushDriver('', '');
  }

  /**
   * Tests 'parseUserId()' correctly extracts UID from drush output.
 *
 * @dataProvider dataProviderParseUserId
 */
  #[DataProvider('dataProviderParseUserId')]
  public function testParseUserId(string $drush_output, ?int $expected): void {
    $driver = new TestDrushDriver('alias');
    $result = $driver->callParseUserId($drush_output);
    $this->assertSame($expected, $result);
  }

  /**
   * Data provider for testParseUserId().
   */
  public static function dataProviderParseUserId(): \Iterator {
    yield 'legacy key-value format' => [
      "User ID   :   550895\nUser name :   test\n",
      550895,
    ];
    yield 'drush 12 table format' => [
      " --------- ----------- ----------- --------------- ------------- \n  User ID   User name   User mail   User roles      User status  \n --------- ----------- ----------- --------------- ------------- \n  550895    test        test@ex.co  authenticated   1            \n --------- ----------- ----------- --------------- ------------- \n",
      550895,
    ];
    yield 'no user id present' => [
      "Some random output\n",
      NULL,
    ];
    yield 'drush 12 table uid 1' => [
      " --------- ----------- ----------- --------------- ------------- \n  User ID   User name   User mail   User roles      User status  \n --------- ----------- ----------- --------------- ------------- \n  1         admin       a@ex.co     administrator   1            \n --------- ----------- ----------- --------------- ------------- \n",
      1,
    ];
  }

}
