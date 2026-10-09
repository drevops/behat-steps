<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Behat\Hook;

use Behat\Testwork\Hook\FilterableHook;
use DrevOps\BehatSteps\Behat\Hook\Call\AfterEntityCreate;
use DrevOps\BehatSteps\Behat\Hook\Call\AfterNodeCreate;
use DrevOps\BehatSteps\Behat\Hook\Call\AfterTermCreate;
use DrevOps\BehatSteps\Behat\Hook\Call\AfterUserCreate;
use DrevOps\BehatSteps\Behat\Hook\Call\BeforeEntityCreate;
use DrevOps\BehatSteps\Behat\Hook\Call\BeforeNodeCreate;
use DrevOps\BehatSteps\Behat\Hook\Call\BeforeTermCreate;
use DrevOps\BehatSteps\Behat\Hook\Call\BeforeUserCreate;
use DrevOps\BehatSteps\Behat\Hook\Call\EntityHook;
use DrevOps\BehatSteps\Tests\Unit\Behat\Fixtures\HookedContext;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests the hook calls the attribute reader constructs.
 */
#[CoversClass(EntityHook::class)]
#[CoversClass(AfterEntityCreate::class)]
#[CoversClass(AfterNodeCreate::class)]
#[CoversClass(AfterTermCreate::class)]
#[CoversClass(AfterUserCreate::class)]
#[CoversClass(BeforeEntityCreate::class)]
#[CoversClass(BeforeNodeCreate::class)]
#[CoversClass(BeforeTermCreate::class)]
#[CoversClass(BeforeUserCreate::class)]
class CallTest extends UnitTestCase {

  /**
   * Tests each hook call's name and scope, and that it takes no filter.
   *
   * @param class-string<\DrevOps\BehatSteps\Behat\Hook\Call\EntityHook> $call_class
   *   The hook call to build.
   * @param string $expected_name
   *   The name the call is expected to report.
   * @param string $expected_scope
   *   The scope name the call is expected to bind to.
   */
  #[DataProvider('dataProviderNameAndScope')]
  public function testNameAndScope(string $call_class, string $expected_name, string $expected_scope): void {
    $call = new $call_class(HookedContext::beforeNode(...));

    $this->assertSame($expected_name, $call->getName());
    $this->assertSame($expected_scope, $call->getScopeName());
    $this->assertNotInstanceOf(FilterableHook::class, $call);
  }

  public static function dataProviderNameAndScope(): \Iterator {
    yield 'before entity' => [BeforeEntityCreate::class, 'BeforeEntityCreate', 'entity.create.before'];
    yield 'after entity' => [AfterEntityCreate::class, 'AfterEntityCreate', 'entity.create.after'];
    yield 'before node' => [BeforeNodeCreate::class, 'BeforeNodeCreate', 'node.create.before'];
    yield 'after node' => [AfterNodeCreate::class, 'AfterNodeCreate', 'node.create.after'];
    yield 'before term' => [BeforeTermCreate::class, 'BeforeTermCreate', 'term.create.before'];
    yield 'after term' => [AfterTermCreate::class, 'AfterTermCreate', 'term.create.after'];
    yield 'before user' => [BeforeUserCreate::class, 'BeforeUserCreate', 'user.create.before'];
    yield 'after user' => [AfterUserCreate::class, 'AfterUserCreate', 'user.create.after'];
  }

  public function testTheDescriptionIsCarried(): void {
    $call = new BeforeNodeCreate(HookedContext::beforeNode(...), 'Alters node values.');

    $this->assertSame('Alters node values.', $call->getDescription());
  }

}
