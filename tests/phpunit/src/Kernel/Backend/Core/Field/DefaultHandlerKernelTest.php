<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Kernel\Backend\Core\Field;

use DrevOps\BehatSteps\Backend\Core\Field\DefaultHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel round-trip test for DefaultHandler via the Core backend.
 *
 * DefaultHandler is the fallback used for any field type without a dedicated
 * handler class. The 'string' field type has no DrupalBackend handler, so the
 * lookup chain resolves to DefaultHandler.
 */
#[CoversClass(DefaultHandler::class)]
#[Group('fields')]
#[RunTestsInSeparateProcesses]
class DefaultHandlerKernelTest extends FieldHandlerKernelTestBase {

  /**
   * {@inheritdoc}
   *
   * @var array<string>
   */
  protected static $modules = self::BASE_MODULES;

  public function testStringRoundTripViaDefaultHandler(): void {
    $this->attachField('field_note', 'string');

    $this->assertFieldRoundTripViaBackend('field_note', ['hello world']);
  }

}
