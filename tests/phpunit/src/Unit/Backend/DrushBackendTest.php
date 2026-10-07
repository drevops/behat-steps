<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Backend;

use DrevOps\BehatSteps\Backend\BackendInterface;
use DrevOps\BehatSteps\Backend\DrushBackend;
use DrevOps\BehatSteps\Backend\DrushBackendInterface;
use DrevOps\BehatSteps\Backend\Exception\BootstrapException;
use DrevOps\BehatSteps\Tests\Unit\Backend\Fixtures\ParserExposingDrushBackend;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for the Drush backend.
 */
#[CoversClass(DrushBackend::class)]
#[Group('backends')]
#[Group('drush')]
class DrushBackendTest extends UnitTestCase {

  public function testImplementsDrushBackendInterface(): void {
    $interfaces = (array) class_implements(DrushBackend::class);

    $this->assertContains(DrushBackendInterface::class, $interfaces);
    $this->assertContains(BackendInterface::class, $interfaces);
  }

  /**
   * Tests instantiating the backend with only an alias.
   */
  public function testWithAlias(): void {
    $backend = new DrushBackend('alias');
    $this->assertSame('alias', $backend->alias, 'The drush alias was not properly set.');
  }

  /**
   * Tests instantiating the backend with a prefixed alias.
   */
  public function testWithAliasPrefix(): void {
    $backend = new DrushBackend('@alias');
    $this->assertSame('alias', $backend->alias, 'The drush alias did not remove the "@" prefix.');
  }

  /**
   * Tests instantiating the backend with only the root path.
   */
  public function testWithRoot(): void {
    // The backend only resolves the root with 'realpath()', so the path to this
    // file serves as a root.
    $backend = new DrushBackend('', __FILE__);
    $this->assertSame(__FILE__, $backend->root);
  }

  /**
   * Tests instantiating the backend with missing alias and root path.
   */
  public function testWithNeither(): void {
    $this->expectException(BootstrapException::class);
    new DrushBackend('', '');
  }

  /**
   * Tests that 'parseUserId()' extracts the UID from Drush output.
   */
  #[DataProvider('dataProviderParseUserId')]
  public function testParseUserId(string $drush_output, ?int $expected): void {
    $backend = new ParserExposingDrushBackend('alias');
    $result = $backend->callParseUserId($drush_output);
    $this->assertSame($expected, $result);
  }

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
