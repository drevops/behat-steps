<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Kernel\Backend\Core\Field;

use DrevOps\BehatSteps\Backend\Core\Field\LinkHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel round-trip test for link fields via the Core backend.
 *
 * Link is a multi-property field (uri, title, options). This test verifies
 * that the base helper handles associative-array deltas and that
 * LinkHandler's output, including the enforced empty 'options' array,
 * round-trips through real storage.
 */
#[CoversClass(LinkHandler::class)]
#[Group('fields')]
#[RunTestsInSeparateProcesses]
class LinkHandlerKernelTest extends FieldHandlerKernelTestBase {

  /**
   * {@inheritdoc}
   *
   * @var array<string>
   */
  protected static $modules = [
    ...self::BASE_MODULES,
    'link',
  ];

  public function testLinkWithTitleRoundTrip(): void {
    $this->attachField('field_homepage', 'link');

    $this->assertFieldRoundTripViaBackend('field_homepage', [
      ['uri' => 'https://example.com', 'title' => 'Example'],
    ]);
  }

  /**
   * Tests round-trip when the handler is given a URI-only string.
   *
   * LinkHandler converts a bare string into ['uri' => $string] during expand,
   * so the backend-mutated stub holds an array after createEntity. The base
   * assertion compares that array against the stored field and so checks
   * that the scalar-to-array normalization reached storage intact.
   */
  public function testUriOnlyStringRoundTrip(): void {
    $this->attachField('field_homepage', 'link');

    $this->assertFieldRoundTripViaBackend('field_homepage', ['https://example.com']);
  }

}
