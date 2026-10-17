<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Backend\Drupal\Core\Alias;

use DrevOps\BehatSteps\Backend\Alias\PreCreateAliasInterface;
use DrevOps\BehatSteps\Backend\Drupal\Core\Alias\ParentTermAlias;
use DrevOps\BehatSteps\Backend\Entity\EntityStub;
use DrevOps\BehatSteps\Backend\Exception\CreationAliasResolutionException;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the 'ParentTermAlias' creation alias.
 */
#[CoversClass(ParentTermAlias::class)]
#[Group('aliases')]
class ParentTermAliasTest extends UnitTestCase {

  public function testMetadataAccessors(): void {
    $alias = new ParentTermAlias(static fn(): ?int => NULL);

    $this->assertInstanceOf(PreCreateAliasInterface::class, $alias);
    $this->assertSame('parent', $alias->getName());
    $this->assertSame('taxonomy_term', $alias->getEntityType());
    $this->assertNotSame('', $alias->getDescription());
  }

  public function testApplyToStubResolvesParent(): void {
    $alias = new ParentTermAlias(static fn(string $name, string $vid): int => $name === 'Frameworks' && $vid === 'tags' ? 99 : 0);

    $stub = new EntityStub('taxonomy_term', 'tags', ['name' => 'Symfony', 'parent' => 'Frameworks']);

    $alias->applyToStub($stub);

    $this->assertSame(99, $stub->getValue('parent'));
  }

  public function testApplyToStubFallsBackToVidValue(): void {
    $received_vid = NULL;
    $alias = new ParentTermAlias(static function (string $name, string $vid) use (&$received_vid): int {
      $received_vid = $vid;

      return 7;
    });

    $stub = new EntityStub('taxonomy_term', NULL, ['parent' => 'Frameworks', 'vid' => 'tags']);

    $alias->applyToStub($stub);

    $this->assertSame('tags', $received_vid);
    $this->assertSame(7, $stub->getValue('parent'));
  }

  public function testApplyToStubThrowsOnUnknownParent(): void {
    $alias = new ParentTermAlias(static fn(): ?int => NULL);

    $stub = new EntityStub('taxonomy_term', 'tags', ['parent' => 'Nope']);

    try {
      $alias->applyToStub($stub);
      $this->fail('Expected CreationAliasResolutionException.');
    }
    catch (CreationAliasResolutionException $exception) {
      $this->assertStringContainsString('"Nope"', $exception->getMessage());
      $this->assertStringContainsString('"tags"', $exception->getMessage());
      $this->assertSame('Nope', $stub->getValue('parent'));
    }
  }

  /**
   * Tests that a missing vocabulary throws with a clear message.
   *
   * Pins the strict-failure branch taken when 'parent' is resolved without a
   * bundle or 'vid' to scope the term lookup.
   */
  public function testApplyToStubThrowsOnMissingVocabulary(): void {
    $alias = new ParentTermAlias(static fn(): int => 1);

    $stub = new EntityStub('taxonomy_term', NULL, ['parent' => 'Frameworks']);

    try {
      $alias->applyToStub($stub);
      $this->fail('Expected CreationAliasResolutionException.');
    }
    catch (CreationAliasResolutionException $exception) {
      $this->assertStringContainsString('"Frameworks"', $exception->getMessage());
      $this->assertStringContainsString('no vocabulary', $exception->getMessage());
      $this->assertSame('Frameworks', $stub->getValue('parent'));
    }
  }

  /**
   * Tests that empty parent values short-circuit without calling lookup.
   *
   * @param mixed $parent
   *   The empty-ish value placed on the stub.
   */
  #[DataProvider('dataProviderApplyToStubNoOpsOnEmptyParent')]
  public function testApplyToStubNoOpsOnEmptyParent(mixed $parent): void {
    $calls = 0;
    $alias = new ParentTermAlias(static function () use (&$calls): int {
      $calls++;

      return 1;
    });

    $stub = new EntityStub('taxonomy_term', 'tags', ['parent' => $parent]);

    $alias->applyToStub($stub);

    $this->assertSame(0, $calls, 'Lookup must not be invoked for empty values.');
    $this->assertSame($parent, $stub->getValue('parent'), 'Value must remain unchanged.');
  }

  /**
   * Data provider for 'testApplyToStubNoOpsOnEmptyParent()'.
   *
   * @return \Iterator<string, array<int, mixed>>
   *   Cases of empty-ish parent value.
   */
  public static function dataProviderApplyToStubNoOpsOnEmptyParent(): \Iterator {
    yield 'empty string' => [''];
    yield 'null' => [NULL];
    yield 'zero integer' => [0];
    yield 'empty array' => [[]];
  }

  /**
   * Tests that a parent the alias cannot resolve to a valid tid is rejected.
   *
   * @param array<string, mixed> $values
   *   The stub values, which carry no bundle.
   * @param int|string $tid
   *   The tid the lookup returns.
   * @param string $expected_message
   *   The message the alias is expected to throw with.
   */
  #[DataProvider('dataProviderApplyToStubRejectsUnresolvableParent')]
  public function testApplyToStubRejectsUnresolvableParent(array $values, int|string $tid, string $expected_message): void {
    $alias = new ParentTermAlias(static fn(): int|string => $tid);

    $stub = new EntityStub('taxonomy_term', NULL, $values);

    try {
      $alias->applyToStub($stub);
      $this->fail('Expected CreationAliasResolutionException.');
    }
    catch (CreationAliasResolutionException $exception) {
      $this->assertSame($expected_message, $exception->getMessage());
      $this->assertSame($values['parent'], $stub->getValue('parent'), 'The parent value must stay as given when resolution fails.');
    }
  }

  public static function dataProviderApplyToStubRejectsUnresolvableParent(): \Iterator {
    yield 'parent that is not a name' => [['parent' => ['Frameworks'], 'vid' => 'tags'], 7, "Cannot resolve parent term because the 'parent' value is not a scalar or stringable object."];
    yield 'vocabulary that is not a name' => [['parent' => 'Frameworks', 'vid' => ['tags']], 7, 'Cannot resolve parent term because the vocabulary value is not a scalar or stringable object.'];
    yield 'lookup returning a tid of 0' => [['parent' => 'Frameworks', 'vid' => 'tags'], 0, 'Cannot resolve parent term "Frameworks" in vocabulary "tags" because the lookup returned an invalid tid.'];
    yield 'lookup returning a tid that is not a number' => [['parent' => 'Frameworks', 'vid' => 'tags'], 'abc', 'Cannot resolve parent term "Frameworks" in vocabulary "tags" because the lookup returned an invalid tid.'];
  }

}
