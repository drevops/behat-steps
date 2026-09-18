<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Behat\Hook;

use Behat\Behat\Context\Context;
use Behat\Testwork\Environment\Environment;
use DrevOps\BehatSteps\Behat\Hook\Call\AfterEntityCreate;
use DrevOps\BehatSteps\Behat\Hook\Call\AfterNodeCreate;
use DrevOps\BehatSteps\Behat\Hook\Call\AfterTermCreate;
use DrevOps\BehatSteps\Behat\Hook\Call\AfterUserCreate;
use DrevOps\BehatSteps\Behat\Hook\Call\BeforeEntityCreate;
use DrevOps\BehatSteps\Behat\Hook\Call\BeforeNodeCreate;
use DrevOps\BehatSteps\Behat\Hook\Call\BeforeTermCreate;
use DrevOps\BehatSteps\Behat\Hook\Call\BeforeUserCreate;
use DrevOps\BehatSteps\Behat\Hook\Call\EntityHook;
use DrevOps\BehatSteps\Behat\Hook\Scope\BeforeNodeCreateScope;
use DrevOps\BehatSteps\Driver\Entity\EntityStub;
use DrevOps\BehatSteps\Tests\Unit\Behat\Fixtures\HookedContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

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
class CallTest extends TestCase {

  /**
   * Tests the name and scope each hook call reports.
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
    $call = new $call_class(NULL, HookedContext::beforeNode(...));

    $this->assertSame($expected_name, $call->getName());
    $this->assertSame($expected_scope, $call->getScopeName());
    $this->assertNull($call->getFilterString());
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

  public function testAnUnfilteredHookMatchesTheScope(): void {
    $call = new BeforeNodeCreate(NULL, HookedContext::beforeNode(...));

    $this->assertTrue($call->filterMatches($this->createScope()));
  }

  public function testFilteredHookMatchesNothing(): void {
    $call = new BeforeNodeCreate('@api', HookedContext::beforeNode(...));

    $this->assertSame('@api', $call->getFilterString());
    $this->assertFalse($call->filterMatches($this->createScope()));
  }

  public function testTheDescriptionIsCarried(): void {
    $call = new BeforeNodeCreate(NULL, HookedContext::beforeNode(...), 'Alters node values.');

    $this->assertSame('Alters node values.', $call->getDescription());
  }

  /**
   * Builds a scope to filter a hook against.
   */
  protected function createScope(): BeforeNodeCreateScope {
    return new BeforeNodeCreateScope($this->createMock(Environment::class), $this->createMock(Context::class), new EntityStub('node'));
  }

}
