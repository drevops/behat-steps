<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Behat\Prerequisite;

use DrevOps\BehatSteps\Behat\Prerequisite\Prerequisite;
use DrevOps\BehatSteps\Behat\Prerequisite\PrerequisiteReader;
use DrevOps\BehatSteps\Driver\Capability\CoreCapabilityInterface;
use DrevOps\BehatSteps\Driver\Capability\ModuleCapabilityInterface;
use DrevOps\BehatSteps\Steps\Drupal\SearchApiTrait;
use DrevOps\BehatSteps\Tests\Unit\Behat\Fixtures\ConfigurableContext;
use DrevOps\BehatSteps\Tests\Unit\Behat\Fixtures\CountedPrerequisiteTrait;
use DrevOps\BehatSteps\Tests\Unit\Behat\Fixtures\NotListPrerequisiteTrait;
use DrevOps\BehatSteps\Tests\Unit\Behat\Fixtures\PrerequisiteContext;
use DrevOps\BehatSteps\Tests\Unit\Behat\Fixtures\PrerequisiteReaderHost;
use DrevOps\BehatSteps\Tests\Unit\Behat\Fixtures\RedeclaringPrerequisiteReaderHost;
use DrevOps\BehatSteps\Tests\Unit\Behat\Fixtures\SamplePrerequisiteTrait;
use DrevOps\BehatSteps\Tests\Unit\Behat\Fixtures\SampleConfigTrait;
use DrevOps\BehatSteps\Tests\Unit\Behat\Fixtures\WrongEntryPrerequisiteTrait;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests reading the prerequisites a trait declares.
 */
#[CoversClass(PrerequisiteReader::class)]
class PrerequisiteReaderTest extends UnitTestCase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // The cache and the read counter are static, so another test reading the
    // counted trait first would leave them populated.
    (new \ReflectionProperty(PrerequisiteReader::class, 'cache'))->setValue(NULL, []);
    PrerequisiteReaderHost::$countedPrerequisiteReads = 0;
  }

  #[DataProvider('dataProviderMethodFor')]
  public function testMethodFor(string $trait, string $expected): void {
    $this->assertSame($expected, PrerequisiteReader::methodFor($trait));
  }

  public static function dataProviderMethodFor(): \Iterator {
    yield 'short name' => ['WatchdogTrait', 'watchdogPrerequisites'];
    yield 'fully qualified name' => [SearchApiTrait::class, 'searchApiPrerequisites'];
    yield 'several words' => ['BigPipeTrait', 'bigPipePrerequisites'];
  }

  public function testReadsTheDeclarationsInOrder(): void {
    $prerequisites = (new PrerequisiteReader())->read(new PrerequisiteContext(), SamplePrerequisiteTrait::class);

    $this->assertContainsOnlyInstancesOf(Prerequisite::class, $prerequisites);
    $this->assertSame([CoreCapabilityInterface::class, ModuleCapabilityInterface::class], array_map(static fn(Prerequisite $prerequisite): string => $prerequisite->capability, $prerequisites));
  }

  public function testReadsNothingForTraitDeclaringNone(): void {
    $this->assertSame([], (new PrerequisiteReader())->read(new ConfigurableContext(), SampleConfigTrait::class));
  }

  public function testReadsDeclarationOncePerRun(): void {
    $reader = new PrerequisiteReader();

    $first = $reader->read(new PrerequisiteReaderHost(), CountedPrerequisiteTrait::class);
    $second = (new PrerequisiteReader())->read(new PrerequisiteReaderHost(), CountedPrerequisiteTrait::class);

    $this->assertSame($first, $second);
    $this->assertSame(1, PrerequisiteReaderHost::$countedPrerequisiteReads);
  }

  public function testReadsEachContextClassSeparately(): void {
    $reader = new PrerequisiteReader();

    $declared = $reader->read(new PrerequisiteReaderHost(), CountedPrerequisiteTrait::class);
    $redeclared = $reader->read(new RedeclaringPrerequisiteReaderHost(), CountedPrerequisiteTrait::class);

    $this->assertSame([CoreCapabilityInterface::class], array_map(static fn(Prerequisite $prerequisite): string => $prerequisite->capability, $declared));
    $this->assertSame([ModuleCapabilityInterface::class], array_map(static fn(Prerequisite $prerequisite): string => $prerequisite->capability, $redeclared));
  }

  #[DataProvider('dataProviderRejectsMalformedDeclarations')]
  public function testRejectsMalformedDeclarations(string $trait, string $message): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage($message);

    (new PrerequisiteReader())->read(new PrerequisiteReaderHost(), $trait);
  }

  public static function dataProviderRejectsMalformedDeclarations(): \Iterator {
    yield 'not a list' => [
      NotListPrerequisiteTrait::class,
      sprintf('%s::notListPrerequisitePrerequisites() must return a list of %s objects.', NotListPrerequisiteTrait::class, Prerequisite::class),
    ];
    yield 'a list holding something else' => [
      WrongEntryPrerequisiteTrait::class,
      sprintf('%s::wrongEntryPrerequisitePrerequisites() must return a list of %s objects, but it lists a stdClass.', WrongEntryPrerequisiteTrait::class, Prerequisite::class),
    ];
  }

}
