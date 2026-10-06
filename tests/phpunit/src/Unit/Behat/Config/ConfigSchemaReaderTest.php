<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Behat\Config;

use DrevOps\BehatSteps\Behat\Config\ConfigSchemaReader;
use DrevOps\BehatSteps\Behat\Config\Option;
use DrevOps\BehatSteps\Steps\Drupal\BigPipeTrait;
use DrevOps\BehatSteps\Tests\Unit\Behat\Fixtures\BareConfigContext;
use DrevOps\BehatSteps\Tests\Unit\Behat\Fixtures\ConfigurableContext;
use DrevOps\BehatSteps\Tests\Unit\Behat\Fixtures\DuplicateOptionConfigContext;
use DrevOps\BehatSteps\Tests\Unit\Behat\Fixtures\InvalidOptionConfigContext;
use DrevOps\BehatSteps\Tests\Unit\Behat\Fixtures\NonOptionConfigContext;
use DrevOps\BehatSteps\Tests\Unit\Behat\Fixtures\UntypedConfigContext;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests the discovery of the options a context's traits declare.
 */
#[CoversClass(ConfigSchemaReader::class)]
class ConfigSchemaReaderTest extends UnitTestCase {

  #[DataProvider('dataProviderMethodFor')]
  public function testMethodFor(string $trait, string $expected): void {
    $this->assertSame($expected, ConfigSchemaReader::methodFor($trait));
  }

  public static function dataProviderMethodFor(): \Iterator {
    yield 'short name' => ['CacheTrait', 'cacheConfigSchema'];
    yield 'fully qualified name' => [BigPipeTrait::class, 'bigPipeConfigSchema'];
    yield 'several words' => ['FileDownloadExtraTrait', 'fileDownloadExtraConfigSchema'];
  }

  public function testEveryComposedTraitIsDiscovered(): void {
    $schema = (new ConfigSchemaReader())->read(ConfigurableContext::class);

    $this->assertSame(['other_sample', 'sample', 'sample_extra'], array_keys($schema));
    $this->assertSame(['enabled', 'label', 'limit', 'ratio', 'selectors', 'anything'], array_keys($schema['sample']));
    $this->assertInstanceOf(Option::class, $schema['sample']['label']);
    $this->assertSame('a default', $schema['sample']['label']->default);
  }

  public function testContextComposingNoDeclaringTraitReadsAsEmpty(): void {
    $this->assertSame([], (new ConfigSchemaReader())->read(BareConfigContext::class));
  }

  public function testTheDeclarationsAreReadOnce(): void {
    $first = (new ConfigSchemaReader())->read(ConfigurableContext::class);
    $second = (new ConfigSchemaReader())->read(ConfigurableContext::class);

    $this->assertSame($first['sample']['label'], $second['sample']['label']);
  }

  /**
   * Tests that a declaration a context cannot use is rejected on reading.
   *
   * @param string $context_class
   *   The context whose declaration is malformed.
   * @param string $expected_message
   *   The message the discovery is expected to throw with.
   */
  #[DataProvider('dataProviderMalformedDeclarationIsRejected')]
  public function testMalformedDeclarationIsRejected(string $context_class, string $expected_message): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage($expected_message);

    (new ConfigSchemaReader())->read($context_class);
  }

  public static function dataProviderMalformedDeclarationIsRejected(): \Iterator {
    yield 'a method returning no array' => [
      UntypedConfigContext::class,
      UntypedConfigContext::class . '::untypedConfigSchema() must return a list of ' . Option::class . ' objects.',
    ];

    yield 'a method listing something else' => [
      NonOptionConfigContext::class,
      NonOptionConfigContext::class . '::nonOptionConfigSchema() must return a list of ' . Option::class . ' objects, but it lists a string.',
    ];

    yield 'a method naming one option twice' => [
      DuplicateOptionConfigContext::class,
      DuplicateOptionConfigContext::class . '::duplicateOptionConfigSchema() declares the "duplicate_option.label" option twice.',
    ];

    yield 'an option that rejects its own declaration' => [
      InvalidOptionConfigContext::class,
      InvalidOptionConfigContext::class . '::invalidOptionConfigSchema() declares a malformed option: The "label" option declares a description.',
    ];
  }

}
