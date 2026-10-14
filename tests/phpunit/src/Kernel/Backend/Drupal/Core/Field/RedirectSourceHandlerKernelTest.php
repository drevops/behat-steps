<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Kernel\Backend\Drupal\Core\Field;

use DrevOps\BehatSteps\Backend\Drupal\Core\Field\RedirectSourceHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel round-trip test for RedirectSourceHandler via the Core backend.
 *
 * The 'redirect_source' field type (provided by drupal/redirect) stores a
 * 'path' and a 'query' map, which DefaultHandler rejects as a complex value.
 * This test proves the backend resolves RedirectSourceHandler for the type
 * and that the storage accepts what the handler emits.
 */
#[CoversClass(RedirectSourceHandler::class)]
#[Group('fields')]
#[RunTestsInSeparateProcesses]
class RedirectSourceHandlerKernelTest extends FieldHandlerKernelTestBase {

  /**
   * {@inheritdoc}
   *
   * @var array<string>
   */
  protected static $modules = [
    ...self::BASE_MODULES,
    'link',
    'path_alias',
    'redirect',
  ];

  #[DataProvider('dataProviderRedirectSourceRoundTrip')]
  public function testRedirectSourceRoundTrip(mixed $value): void {
    $this->attachField('field_source', 'redirect_source');

    $this->assertFieldRoundTripViaBackend('field_source', [$value]);
  }

  public static function dataProviderRedirectSourceRoundTrip(): \Iterator {
    yield 'record with a query map' => [['path' => '/old/about', 'query' => ['utm_source' => 'behat']]];
    yield 'bare path' => ['/legacy/contact'];
  }

}
