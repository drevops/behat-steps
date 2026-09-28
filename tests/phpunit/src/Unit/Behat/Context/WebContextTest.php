<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Behat\Context;

use Behat\Behat\Context\Environment\ContextEnvironment;
use Behat\Testwork\Hook\Scope\BeforeSuiteScope;
use Behat\Testwork\Specification\SpecificationIterator;
use Behat\Testwork\Suite\Suite;
use DrevOps\BehatSteps\Behat\Context\DrupalContext;
use DrevOps\BehatSteps\Behat\Context\WebContext;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests the guard against a suite registering two web vocabularies.
 */
#[CoversClass(WebContext::class)]
class WebContextTest extends UnitTestCase {

  public function testAnEnvironmentWithoutContextsIsAccepted(): void {
    $this->expectNotToPerformAssertions();

    WebContext::assertOneContext($this->createBeforeSuiteScope());
  }

  public function testOneRegisteredVocabularyIsAccepted(): void {
    $this->expectNotToPerformAssertions();

    WebContext::assertOneContext($this->createContextSuiteScope([DrupalContext::class, UnrelatedContext::class]));
  }

  public function testTwoRegisteredVocabulariesNameTheMistake(): void {
    $scope = $this->createContextSuiteScope([WebContext::class, DrupalContext::class]);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage(sprintf('The "default" suite registers %s and %s, which all carry the %s vocabulary', WebContext::class, DrupalContext::class, WebContext::class));

    WebContext::assertOneContext($scope);
  }

  /**
   * Builds a suite scope over an environment holding the given contexts.
   *
   * @param array<int, class-string> $classes
   *   The context classes the suite registers.
   */
  protected function createContextSuiteScope(array $classes): BeforeSuiteScope {
    $suite = $this->createStub(Suite::class);
    $suite->method('getName')->willReturn('default');

    $environment = $this->createStub(ContextEnvironment::class);
    $environment->method('getSuite')->willReturn($suite);
    $environment->method('getContextClasses')->willReturn($classes);

    return new BeforeSuiteScope($environment, $this->createStub(SpecificationIterator::class));
  }

}

/**
 * Context carrying none of the shipped vocabulary.
 */
class UnrelatedContext {
}
