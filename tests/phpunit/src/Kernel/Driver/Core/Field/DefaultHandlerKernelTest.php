<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Kernel\Driver\Core\Field;

use DrevOps\BehatSteps\Driver\Core\Field\DefaultHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Kernel round-trip test for DefaultHandler via the Core driver.
 *
 * DefaultHandler is the fallback used for any field type without a dedicated
 * handler class. This test verifies the fallback resolves correctly and that
 * the passthrough output round-trips through real storage. The 'string' field
 * type has no DrupalDriver handler, so the lookup chain lands on
 * DefaultHandler.
 *
 * @group fields
 */
#[CoversClass(DefaultHandler::class)]
#[Group('fields')]
class DefaultHandlerKernelTest extends FieldHandlerKernelTestBase {

  /**
   * {@inheritdoc}
   *
   * @var array<string>
   */
  protected static $modules = self::BASE_MODULES;

  /**
   * Tests round-trip for a string field (no specific handler defined).
   */
  public function testStringRoundTripViaDefaultHandler(): void {
    $this->attachField('field_note', 'string');

    $this->assertFieldRoundTripViaDriver('field_note', ['hello world']);
  }

}
