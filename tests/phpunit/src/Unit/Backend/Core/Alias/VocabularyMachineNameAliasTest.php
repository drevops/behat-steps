<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Backend\Core\Alias;

use DrevOps\BehatSteps\Backend\Alias\PreCreateAliasInterface;
use DrevOps\BehatSteps\Backend\Core\Alias\VocabularyMachineNameAlias;
use DrevOps\BehatSteps\Backend\Entity\EntityStub;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the 'VocabularyMachineNameAlias' creation alias.
 */
#[CoversClass(VocabularyMachineNameAlias::class)]
#[Group('aliases')]
class VocabularyMachineNameAliasTest extends UnitTestCase {

  public function testMetadataAccessors(): void {
    $alias = new VocabularyMachineNameAlias();

    $this->assertInstanceOf(PreCreateAliasInterface::class, $alias);
    $this->assertSame('vocabulary_machine_name', $alias->getName());
    $this->assertSame('taxonomy_term', $alias->getEntityType());
    $this->assertNotSame('', $alias->getDescription());
  }

  /**
   * Tests resolution behaviour across stub shapes.
   *
   * @param string|null $bundle
   *   The bundle passed to the stub constructor.
   * @param array<string, mixed> $values
   *   The initial stub values.
   * @param string|null $expected_vid
   *   The expected 'vid' value after the alias runs, or NULL when 'vid'
   *   should remain absent.
   */
  #[DataProvider('dataProviderApplyToStub')]
  public function testApplyToStub(?string $bundle, array $values, ?string $expected_vid): void {
    $alias = new VocabularyMachineNameAlias();
    $stub = new EntityStub('taxonomy_term', $bundle, $values);

    $alias->applyToStub($stub);

    $this->assertFalse($stub->hasValue('vocabulary_machine_name'), 'Alias must be removed after it runs.');

    if ($expected_vid === NULL) {
      $this->assertFalse($stub->hasValue('vid'));
    }
    else {
      $this->assertSame($expected_vid, $stub->getValue('vid'));
    }
  }

  /**
   * Data provider for 'testApplyToStub()'.
   *
   * @return \Iterator<string, array<int, mixed>>
   *   Cases of bundle, stub values, expected 'vid' (or NULL).
   */
  public static function dataProviderApplyToStub(): \Iterator {
    yield 'no bundle, alias only' => [
      NULL,
      ['vocabulary_machine_name' => 'tags'],
      'tags',
    ];
    yield 'bundle wins over alias' => [
      'categories',
      ['vocabulary_machine_name' => 'tags'],
      NULL,
    ];
    yield 'explicit vid wins over alias' => [
      NULL,
      ['vocabulary_machine_name' => 'tags', 'vid' => 'categories'],
      'categories',
    ];
    yield 'empty alias copies through' => [
      NULL,
      ['vocabulary_machine_name' => ''],
      '',
    ];
  }

}
