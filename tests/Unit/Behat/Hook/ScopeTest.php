<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Behat\Hook;

use Behat\Behat\Context\Context;
use Behat\Testwork\Environment\Environment;
use Behat\Testwork\Suite\Suite;
use DrevOps\BehatSteps\Behat\Hook\Scope\AfterEntityCreateScope;
use DrevOps\BehatSteps\Behat\Hook\Scope\AfterLanguageCreateScope;
use DrevOps\BehatSteps\Behat\Hook\Scope\AfterNodeCreateScope;
use DrevOps\BehatSteps\Behat\Hook\Scope\AfterTermCreateScope;
use DrevOps\BehatSteps\Behat\Hook\Scope\AfterUserCreateScope;
use DrevOps\BehatSteps\Behat\Hook\Scope\BaseEntityScope;
use DrevOps\BehatSteps\Behat\Hook\Scope\BeforeEntityCreateScope;
use DrevOps\BehatSteps\Behat\Hook\Scope\BeforeLanguageCreateScope;
use DrevOps\BehatSteps\Behat\Hook\Scope\BeforeNodeCreateScope;
use DrevOps\BehatSteps\Behat\Hook\Scope\BeforeTermCreateScope;
use DrevOps\BehatSteps\Behat\Hook\Scope\BeforeUserCreateScope;
use DrevOps\BehatSteps\Behat\Hook\Scope\EntityScopeInterface;
use DrevOps\BehatSteps\Driver\Entity\EntityStub;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests the scope objects dispatched around entity creation.
 */
#[CoversClass(BaseEntityScope::class)]
#[CoversClass(AfterEntityCreateScope::class)]
#[CoversClass(AfterLanguageCreateScope::class)]
#[CoversClass(AfterNodeCreateScope::class)]
#[CoversClass(AfterTermCreateScope::class)]
#[CoversClass(AfterUserCreateScope::class)]
#[CoversClass(BeforeEntityCreateScope::class)]
#[CoversClass(BeforeLanguageCreateScope::class)]
#[CoversClass(BeforeNodeCreateScope::class)]
#[CoversClass(BeforeTermCreateScope::class)]
#[CoversClass(BeforeUserCreateScope::class)]
class ScopeTest extends TestCase {

  /**
   * Tests the hook name each concrete scope reports.
   *
   * @param class-string<\DrevOps\BehatSteps\Behat\Hook\Scope\BaseEntityScope> $scope_class
   *   The scope to build.
   * @param string $expected
   *   The hook name the scope is expected to report.
   */
  #[DataProvider('dataProviderScopeName')]
  public function testScopeName(string $scope_class, string $expected): void {
    $scope = new $scope_class($this->createMock(Environment::class), $this->createMock(Context::class), new EntityStub('node'));

    $this->assertSame($expected, $scope->getName());
  }

  public static function dataProviderScopeName(): \Iterator {
    yield 'before entity' => [BeforeEntityCreateScope::class, EntityScopeInterface::BEFORE];
    yield 'after entity' => [AfterEntityCreateScope::class, EntityScopeInterface::AFTER];
    yield 'before node' => [BeforeNodeCreateScope::class, 'node.create.before'];
    yield 'after node' => [AfterNodeCreateScope::class, 'node.create.after'];
    yield 'before term' => [BeforeTermCreateScope::class, 'term.create.before'];
    yield 'after term' => [AfterTermCreateScope::class, 'term.create.after'];
    yield 'before user' => [BeforeUserCreateScope::class, 'user.create.before'];
    yield 'after user' => [AfterUserCreateScope::class, 'user.create.after'];
    yield 'before language' => [BeforeLanguageCreateScope::class, 'language.create.before'];
    yield 'after language' => [AfterLanguageCreateScope::class, 'language.create.after'];
  }

  public function testTheScopeCarriesItsContextStubAndEnvironment(): void {
    $context = $this->createMock(Context::class);
    $environment = $this->createMock(Environment::class);
    $stub = new EntityStub('node', 'page', ['title' => 'Test']);

    $scope = new BeforeNodeCreateScope($environment, $context, $stub);

    $this->assertSame($context, $scope->getContext());
    $this->assertSame($stub, $scope->getStub());
    $this->assertSame($environment, $scope->getEnvironment());
  }

  public function testTheSuiteComesFromTheEnvironment(): void {
    $suite = $this->createMock(Suite::class);
    $environment = $this->createMock(Environment::class);
    $environment->method('getSuite')->willReturn($suite);

    $scope = new BeforeNodeCreateScope($environment, $this->createMock(Context::class), new EntityStub('node'));

    $this->assertSame($suite, $scope->getSuite());
  }

}
