<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Behat\Hook;

use DrevOps\BehatSteps\Behat\Hook\Attribute\AfterEntityCreate;
use DrevOps\BehatSteps\Behat\Hook\Attribute\AfterNodeCreate;
use DrevOps\BehatSteps\Behat\Hook\Attribute\AfterTermCreate;
use DrevOps\BehatSteps\Behat\Hook\Attribute\AfterUserCreate;
use DrevOps\BehatSteps\Behat\Hook\Attribute\BeforeEntityCreate;
use DrevOps\BehatSteps\Behat\Hook\Attribute\BeforeNodeCreate;
use DrevOps\BehatSteps\Behat\Hook\Attribute\BeforeTermCreate;
use DrevOps\BehatSteps\Behat\Hook\Attribute\BeforeUserCreate;
use DrevOps\BehatSteps\Behat\Hook\Attribute\DrupalHookInterface;
use DrevOps\BehatSteps\Behat\Hook\Attribute\FilterStringTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests the attributes a context method declares its entity hooks with.
 */
#[CoversClass(AfterEntityCreate::class)]
#[CoversClass(AfterNodeCreate::class)]
#[CoversClass(AfterTermCreate::class)]
#[CoversClass(AfterUserCreate::class)]
#[CoversClass(BeforeEntityCreate::class)]
#[CoversClass(BeforeNodeCreate::class)]
#[CoversClass(BeforeTermCreate::class)]
#[CoversClass(BeforeUserCreate::class)]
#[CoversTrait(FilterStringTrait::class)]
class AttributeTest extends TestCase {

  /**
   * Tests that an attribute declared without arguments carries no filter.
   *
   * @param class-string<\DrevOps\BehatSteps\Behat\Hook\Attribute\DrupalHookInterface> $attribute_class
   *   The attribute to build.
   */
  #[DataProvider('dataProviderFilterStringDefaultsToNone')]
  public function testFilterStringDefaultsToNone(string $attribute_class): void {
    $attribute = new $attribute_class();

    $this->assertInstanceOf(DrupalHookInterface::class, $attribute);
    $this->assertNull($attribute->getFilterString());
  }

  public static function dataProviderFilterStringDefaultsToNone(): \Iterator {
    yield from self::attributeClasses();
  }

  /**
   * Tests that a declared filter string is readable off the attribute.
   *
   * @param class-string<\DrevOps\BehatSteps\Behat\Hook\Attribute\DrupalHookInterface> $attribute_class
   *   The attribute to build.
   */
  #[DataProvider('dataProviderFilterStringIsReadBack')]
  public function testFilterStringIsReadBack(string $attribute_class): void {
    $this->assertSame('@api', (new $attribute_class('@api'))->getFilterString());
  }

  public static function dataProviderFilterStringIsReadBack(): \Iterator {
    yield from self::attributeClasses();
  }

  /**
   * Tests that every attribute targets methods and repeats.
   *
   * @param class-string<\DrevOps\BehatSteps\Behat\Hook\Attribute\DrupalHookInterface> $attribute_class
   *   The attribute to reflect.
   */
  #[DataProvider('dataProviderAttributeTargetsRepeatableMethods')]
  public function testAttributeTargetsRepeatableMethods(string $attribute_class): void {
    $attributes = (new \ReflectionClass($attribute_class))->getAttributes(\Attribute::class);

    $this->assertCount(1, $attributes);
    $this->assertSame(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE, $attributes[0]->newInstance()->flags);
  }

  public static function dataProviderAttributeTargetsRepeatableMethods(): \Iterator {
    yield from self::attributeClasses();
  }

  /**
   * Lists every hook attribute the reader maps to a call class.
   *
   * @return \Iterator<string, array{class-string}>
   *   One row per attribute, keyed by description.
   */
  protected static function attributeClasses(): \Iterator {
    yield 'before entity' => [BeforeEntityCreate::class];
    yield 'after entity' => [AfterEntityCreate::class];
    yield 'before node' => [BeforeNodeCreate::class];
    yield 'after node' => [AfterNodeCreate::class];
    yield 'before term' => [BeforeTermCreate::class];
    yield 'after term' => [AfterTermCreate::class];
    yield 'before user' => [BeforeUserCreate::class];
    yield 'after user' => [AfterUserCreate::class];
  }

}
