<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests;

use DrevOps\BehatSteps\Behat\Config\ConfigSchemaReader;
use DrevOps\BehatSteps\Behat\Config\GroupName;
use DrevOps\BehatSteps\Behat\Config\Option;
use DrevOps\BehatSteps\Behat\Context\DrupalContext;
use DrevOps\BehatSteps\Steps\Drupal\BigPipeTrait;
use DrevOps\BehatSteps\Steps\Drupal\CacheTrait;
use DrevOps\BehatSteps\Steps\Drupal\ConfigOverrideTrait;
use DrevOps\BehatSteps\Steps\Drupal\ConfigTrait;
use DrevOps\BehatSteps\Steps\Drupal\EmailTrait;
use DrevOps\BehatSteps\Steps\Drupal\FileTrait;
use DrevOps\BehatSteps\Steps\Drupal\ModuleTrait;
use DrevOps\BehatSteps\Steps\Drupal\QueueTrait;
use DrevOps\BehatSteps\Steps\Drupal\StateTrait;
use DrevOps\BehatSteps\Steps\Drupal\TestmodeTrait;
use DrevOps\BehatSteps\Steps\Drupal\TimeTrait;
use DrevOps\BehatSteps\Steps\Drupal\WatchdogTrait;
use DrevOps\BehatSteps\Steps\Web\AccessibilityTrait;
use DrevOps\BehatSteps\Steps\Web\BasicAuthTrait;
use DrevOps\BehatSteps\Steps\Web\CommandTrait;
use DrevOps\BehatSteps\Steps\Web\DateTrait;
use DrevOps\BehatSteps\Steps\Web\DiagnosticsTrait;
use DrevOps\BehatSteps\Steps\Web\ElementTrait;
use DrevOps\BehatSteps\Steps\Web\FieldTrait;
use DrevOps\BehatSteps\Steps\Web\FileDownloadTrait;
use DrevOps\BehatSteps\Steps\Web\JavascriptTrait;
use DrevOps\BehatSteps\Steps\Web\MappingTrait;
use DrevOps\BehatSteps\Steps\Web\MessageTrait;
use DrevOps\BehatSteps\Steps\Web\ModalTrait;
use DrevOps\BehatSteps\Steps\Web\RandomTrait;
use DrevOps\BehatSteps\Steps\Web\RestTrait;
use DrevOps\BehatSteps\Steps\Web\TableTrait;
use DrevOps\BehatSteps\Steps\Web\WaitTrait;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests the options every shipped step trait declares.
 *
 * The runtime reads a trait's options under the group derived from its
 * method prefix. The documentation generator looks them up under the group
 * derived from the trait's name, so the 2 have to agree for every trait.
 */
#[CoversTrait(BigPipeTrait::class)]
#[CoversTrait(CacheTrait::class)]
#[CoversTrait(ConfigOverrideTrait::class)]
#[CoversTrait(ConfigTrait::class)]
#[CoversTrait(EmailTrait::class)]
#[CoversTrait(FileTrait::class)]
#[CoversTrait(ModuleTrait::class)]
#[CoversTrait(QueueTrait::class)]
#[CoversTrait(StateTrait::class)]
#[CoversTrait(TestmodeTrait::class)]
#[CoversTrait(TimeTrait::class)]
#[CoversTrait(WatchdogTrait::class)]
#[CoversTrait(AccessibilityTrait::class)]
#[CoversTrait(BasicAuthTrait::class)]
#[CoversTrait(CommandTrait::class)]
#[CoversTrait(DateTrait::class)]
#[CoversTrait(DiagnosticsTrait::class)]
#[CoversTrait(ElementTrait::class)]
#[CoversTrait(FieldTrait::class)]
#[CoversTrait(FileDownloadTrait::class)]
#[CoversTrait(JavascriptTrait::class)]
#[CoversTrait(MappingTrait::class)]
#[CoversTrait(MessageTrait::class)]
#[CoversTrait(ModalTrait::class)]
#[CoversTrait(RandomTrait::class)]
#[CoversTrait(RestTrait::class)]
#[CoversTrait(TableTrait::class)]
#[CoversTrait(WaitTrait::class)]
class OptionDeclarationsTest extends UnitTestCase {

  /**
   * Tests that a trait declares its options under its own name.
   *
   * @param class-string $trait
   *   The step trait to read.
   */
  #[DataProvider('dataProviderDeclarationsAreReadUnderTheTraitGroup')]
  public function testDeclarationsAreReadUnderTheTraitGroup(string $trait): void {
    $short_name = static::reflect($trait)->getShortName();
    $method = ConfigSchemaReader::methodFor($short_name);

    $this->assertSame([$method], static::listSchemaMethods($trait), sprintf('%s declares its options in %s() and in no other method.', $short_name, $method));

    // The reader caches a context class's declarations for the whole run, so
    // the method is invoked directly to execute it in this test.
    $declared = (new \ReflectionMethod(DrupalContext::class, $method))->invoke((new \ReflectionClass(DrupalContext::class))->newInstanceWithoutConstructor());
    $this->assertIsArray($declared);
    $this->assertNotEmpty($declared);

    $names = [];

    foreach ($declared as $option) {
      $this->assertInstanceOf(Option::class, $option);
      $names[] = $option->name;
    }

    $schema = (new ConfigSchemaReader())->read(DrupalContext::class);

    $this->assertSame($names, array_keys($schema[GroupName::fromTraitName($short_name)] ?? []));
  }

  public static function dataProviderDeclarationsAreReadUnderTheTraitGroup(): array {
    return static::buildDeclaringTraitRows();
  }

  /**
   * Tests that a trait reads its options through its own accessors.
   *
   * An option read anywhere else gives a project no method to override it
   * with. The 'enabled' option is read by 'skipTag()' instead.
   *
   * @param class-string $trait
   *   The step trait to read.
   */
  #[DataProvider('dataProviderOptionsAreReadThroughAccessors')]
  public function testOptionsAreReadThroughAccessors(string $trait): void {
    $short_name = static::reflect($trait)->getShortName();
    $accessor_prefix = lcfirst(substr($short_name, 0, -strlen(GroupName::TRAIT_SUFFIX))) . 'Get';
    $outliers = array_values(array_filter(static::listOptionReaders($trait), static fn(string $method): bool => !str_starts_with($method, $accessor_prefix)));

    $this->assertSame([], $outliers, sprintf('%s reads an option outside a %s...() accessor, in %s(). Read it through an accessor, as CONTRIBUTING.md describes.', $short_name, $accessor_prefix, implode('() and ', $outliers)));
  }

  public static function dataProviderOptionsAreReadThroughAccessors(): array {
    return static::buildDeclaringTraitRows();
  }

  /**
   * Tests that the methods reading an option are found.
   *
   * @param class-string $trait
   *   The step trait to read.
   * @param array<int, string> $expected
   *   The methods expected to read an option, sorted.
   */
  #[DataProvider('dataProviderOptionReadersAreFound')]
  public function testOptionReadersAreFound(string $trait, array $expected): void {
    $this->assertSame($expected, static::listOptionReaders($trait));
  }

  public static function dataProviderOptionReadersAreFound(): array {
    return [
      'an accessor' => [WaitTrait::class, ['waitGetAjaxTimeout']],
      'an accessor per option' => [FileDownloadTrait::class, ['fileDownloadGetTempDir', 'fileDownloadGetTimeout']],
      'an accessor read by 2 hooks' => [WatchdogTrait::class, ['watchdogGetFailOnErrors']],
      'only the enabled option' => [BasicAuthTrait::class, []],
    ];
  }

  public function testEveryGroupTheRuntimeReadsBelongsToOneTrait(): void {
    $groups = array_map(static fn(string $trait): string => GroupName::fromTraitName(static::reflect($trait)->getShortName()), static::listDeclaringTraits());
    sort($groups);

    $this->assertSame($groups, array_keys((new ConfigSchemaReader())->read(DrupalContext::class)));
  }

  /**
   * Tests that the coverage targets name every trait that declares options.
   *
   * A trait missing from them runs its declarations here without being
   * credited for them.
   */
  public function testCoverageTargetsNameEveryDeclaringTrait(): void {
    $targets = array_map(static fn(\ReflectionAttribute $attribute): mixed => $attribute->getArguments()[0], (new \ReflectionClass(static::class))->getAttributes(CoversTrait::class));
    sort($targets);

    $this->assertSame(static::listDeclaringTraits(), $targets, 'Add a #[CoversTrait] attribute for every trait that declares options, and remove the one for a trait that no longer does.');
  }

  /**
   * Lists every step trait the shipped contexts compose that declares options.
   *
   * @return array<int, class-string>
   *   Trait names, sorted.
   */
  protected static function listDeclaringTraits(): array {
    $traits = [];

    for ($class = new \ReflectionClass(DrupalContext::class); $class instanceof \ReflectionClass; $class = $class->getParentClass()) {
      foreach ($class->getTraits() as $trait) {
        if (str_starts_with($trait->getName(), 'DrevOps\\BehatSteps\\Steps\\') && static::listSchemaMethods($trait->getName()) !== []) {
          $traits[] = $trait->getName();
        }
      }
    }

    sort($traits);

    return $traits;
  }

  /**
   * Builds a data provider row for every trait that declares options.
   *
   * @return array<string, array{class-string}>
   *   Trait names, keyed by their short name.
   */
  protected static function buildDeclaringTraitRows(): array {
    $rows = [];

    foreach (static::listDeclaringTraits() as $trait) {
      $rows[static::reflect($trait)->getShortName()] = [$trait];
    }

    return $rows;
  }

  /**
   * Lists the methods a trait declares that read an option.
   *
   * @param class-string $trait
   *   The trait to read.
   *
   * @return array<int, string>
   *   The method names, sorted.
   */
  protected static function listOptionReaders(string $trait): array {
    $reflection = static::reflect($trait);
    $lines = file((string) $reflection->getFileName()) ?: [];
    $readers = [];

    foreach ($reflection->getMethods() as $method) {
      if ($method->getFileName() !== $reflection->getFileName()) {
        continue;
      }

      $body = implode('', array_slice($lines, (int) $method->getStartLine() - 1, (int) $method->getEndLine() - (int) $method->getStartLine() + 1));

      if (preg_match('/->getOption(?:Bool|Int|Float|String|Array)?\(/', $body) === 1) {
        $readers[] = $method->getName();
      }
    }

    sort($readers);

    return $readers;
  }

  /**
   * Lists the methods of a trait carrying the declaring method's suffix.
   *
   * @param class-string $trait
   *   The trait to read.
   *
   * @return array<int, string>
   *   The method names.
   */
  protected static function listSchemaMethods(string $trait): array {
    $names = array_map(static fn(\ReflectionMethod $method): string => $method->getName(), static::reflect($trait)->getMethods());

    return array_values(array_filter($names, static fn(string $name): bool => str_ends_with($name, ConfigSchemaReader::METHOD_SUFFIX)));
  }

}
