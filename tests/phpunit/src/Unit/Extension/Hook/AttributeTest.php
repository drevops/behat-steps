<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Extension\Hook;

use DrevOps\BehatSteps\Extension\Drupal\Hook\Attribute\AfterNodeCreate;
use DrevOps\BehatSteps\Extension\Drupal\Hook\Attribute\AfterTermCreate;
use DrevOps\BehatSteps\Extension\Drupal\Hook\Attribute\BeforeNodeCreate;
use DrevOps\BehatSteps\Extension\Drupal\Hook\Attribute\BeforeTermCreate;
use DrevOps\BehatSteps\Extension\Drupal\Hook\Call\AfterNodeCreate as AfterNodeCreateCall;
use DrevOps\BehatSteps\Extension\Drupal\Hook\Call\AfterTermCreate as AfterTermCreateCall;
use DrevOps\BehatSteps\Extension\Drupal\Hook\Call\BeforeNodeCreate as BeforeNodeCreateCall;
use DrevOps\BehatSteps\Extension\Drupal\Hook\Call\BeforeTermCreate as BeforeTermCreateCall;
use DrevOps\BehatSteps\Extension\Hook\Attribute\AfterEntityCreate;
use DrevOps\BehatSteps\Extension\Hook\Attribute\AfterUserCreate;
use DrevOps\BehatSteps\Extension\Hook\Attribute\BeforeEntityCreate;
use DrevOps\BehatSteps\Extension\Hook\Attribute\BeforeUserCreate;
use DrevOps\BehatSteps\Extension\Hook\Attribute\HookAttributeInterface;
use DrevOps\BehatSteps\Extension\Hook\Call\AfterEntityCreate as AfterEntityCreateCall;
use DrevOps\BehatSteps\Extension\Hook\Call\AfterUserCreate as AfterUserCreateCall;
use DrevOps\BehatSteps\Extension\Hook\Call\BeforeEntityCreate as BeforeEntityCreateCall;
use DrevOps\BehatSteps\Extension\Hook\Call\BeforeUserCreate as BeforeUserCreateCall;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

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
class AttributeTest extends UnitTestCase {

  /**
   * Tests that every attribute is a marker that takes no argument.
   *
   * @param class-string<\DrevOps\BehatSteps\Extension\Hook\Attribute\HookAttributeInterface> $attribute_class
   *   The attribute to build.
   */
  #[DataProvider('dataProviderAttributeTakesNoArgument')]
  public function testAttributeTakesNoArgument(string $attribute_class): void {
    $this->assertInstanceOf(HookAttributeInterface::class, new $attribute_class());
    $this->assertNull(static::reflect($attribute_class)->getConstructor());
  }

  public static function dataProviderAttributeTakesNoArgument(): \Iterator {
    yield from static::listAttributeClasses();
  }

  /**
   * Tests that every attribute targets methods and repeats.
   *
   * @param class-string<\DrevOps\BehatSteps\Extension\Hook\Attribute\HookAttributeInterface> $attribute_class
   *   The attribute to reflect.
   */
  #[DataProvider('dataProviderAttributeTargetsRepeatableMethods')]
  public function testAttributeTargetsRepeatableMethods(string $attribute_class): void {
    $attributes = static::reflect($attribute_class)->getAttributes(\Attribute::class);

    $this->assertCount(1, $attributes);
    $this->assertSame(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE, $attributes[0]->newInstance()->flags);
  }

  public static function dataProviderAttributeTargetsRepeatableMethods(): \Iterator {
    yield from static::listAttributeClasses();
  }

  /**
   * Tests that every attribute names the call class the reader builds.
   *
   * @param class-string<\DrevOps\BehatSteps\Extension\Hook\Attribute\HookAttributeInterface> $attribute_class
   *   The attribute to build.
   * @param class-string<\DrevOps\BehatSteps\Extension\Hook\Call\EntityHook> $call_class
   *   The call class the attribute names.
   */
  #[DataProvider('dataProviderAttributeNamesItsCall')]
  public function testAttributeNamesItsCall(string $attribute_class, string $call_class): void {
    $this->assertSame($call_class, (new $attribute_class())->getCallClass());
  }

  public static function dataProviderAttributeNamesItsCall(): \Iterator {
    yield 'before entity' => [BeforeEntityCreate::class, BeforeEntityCreateCall::class];
    yield 'after entity' => [AfterEntityCreate::class, AfterEntityCreateCall::class];
    yield 'before node' => [BeforeNodeCreate::class, BeforeNodeCreateCall::class];
    yield 'after node' => [AfterNodeCreate::class, AfterNodeCreateCall::class];
    yield 'before term' => [BeforeTermCreate::class, BeforeTermCreateCall::class];
    yield 'after term' => [AfterTermCreate::class, AfterTermCreateCall::class];
    yield 'before user' => [BeforeUserCreate::class, BeforeUserCreateCall::class];
    yield 'after user' => [AfterUserCreate::class, AfterUserCreateCall::class];
  }

  /**
   * Lists every hook attribute the reader maps to a call class.
   *
   * @return \Iterator<string, array{class-string}>
   *   1 row per attribute, keyed by description.
   */
  protected static function listAttributeClasses(): \Iterator {
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
