<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Backend\Drupal\Core\Field;

use Composer\InstalledVersions;
use DrevOps\BehatSteps\Backend\Drupal\Core\Field\FieldHandlerInterface;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Base unit test for field handlers.
 */
abstract class FieldHandlerUnitTestBase extends UnitTestCase {

  /**
   * Absolute path to the backend fixture files, with a trailing separator.
   */
  protected const FIXTURES_PATH = __DIR__ . '/../../../../../../fixtures/backend/files/';

  /**
   * Produces the configured handler under test.
   */
  abstract protected function createHandler(): FieldHandlerInterface;

  /**
   * Tests 'expand()' end-to-end with caller-shaped input.
   *
   * @param mixed $input
   *   The loose input fed to 'expand()'.
   * @param mixed $expected
   *   The expected storage-shape output, or NULL when an exception is
   *   expected.
   * @param class-string<\Throwable>|null $exception
   *   The expected exception class, or NULL for the happy path.
   * @param string|null $expected_message
   *   Substring the exception message must contain, or NULL.
   */
  #[DataProvider('dataProviderExpand')]
  public function testExpand(mixed $input, mixed $expected, ?string $exception, ?string $expected_message): void {
    $handler = $this->createHandler();

    if ($exception !== NULL) {
      $this->expectException($exception);

      if ($expected_message !== NULL) {
        $this->expectExceptionMessage($expected_message);
      }
    }

    // Suppress PHP warnings only on rows that expect an exception, where
    // 'file_get_contents()' can raise a warning before the handler throws. A
    // success-path row must not suppress an unexpected warning.
    $result = $exception !== NULL
      ? @$handler->expand($input)
      : $handler->expand($input);

    if ($exception === NULL) {
      $this->assertSame($expected, $result);
    }
  }

  /**
   * Data provider for 'testExpand()'.
   *
   * @return \Iterator<string, array{mixed, mixed, ?string, ?string}>
   *   Rows of: input, expected storage shape, exception class, message
   *   substring.
   */
  abstract public static function dataProviderExpand(): \Iterator;

  /**
   * Loads datetime module classes from the Composer-resolved drupal/core path.
   *
   * The datetime module is outside the default drupal/core PSR-4 namespace
   * coverage, so the relevant files are loaded explicitly. Returns TRUE when
   * DateTimeItemInterface is available after loading.
   */
  protected function loadDatetimeModuleClasses(): bool {
    if (interface_exists(DateTimeItemInterface::class)) {
      return TRUE;
    }

    if (!class_exists(InstalledVersions::class)) {
      return FALSE;
    }

    $core_path = InstalledVersions::getInstallPath('drupal/core');

    if ($core_path === NULL) {
      return FALSE;
    }

    $interface_file = $core_path . '/modules/datetime/src/Plugin/Field/FieldType/DateTimeItemInterface.php';
    $item_file = $core_path . '/modules/datetime/src/Plugin/Field/FieldType/DateTimeItem.php';

    if (!is_file($interface_file) || !is_file($item_file)) {
      return FALSE;
    }

    require_once $interface_file;
    require_once $item_file;

    return interface_exists(DateTimeItemInterface::class);
  }

}
