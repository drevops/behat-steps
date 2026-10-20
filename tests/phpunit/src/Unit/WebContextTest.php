<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit;

use Behat\Behat\Context\Environment\ContextEnvironment;
use Behat\Testwork\Hook\Scope\BeforeSuiteScope;
use Behat\Testwork\Specification\SpecificationIterator;
use Behat\Testwork\Suite\Suite;
use DrevOps\BehatSteps\DrupalContext;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use DrevOps\BehatSteps\WebContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests the guard against a suite registering 2 web vocabularies.
 */
#[CoversClass(WebContext::class)]
class WebContextTest extends UnitTestCase {

  public function testAnEnvironmentWithoutContextsIsAccepted(): void {
    $this->expectNotToPerformAssertions();

    WebContext::assertOneContext($this->createBeforeSuiteScope());
  }

  /**
   * Tests that a suite registering 1 class of the chain is accepted.
   *
   * @param array<int, class-string> $classes
   *   The context classes the suite registers.
   */
  #[DataProvider('dataProviderOneRegisteredVocabularyIsAccepted')]
  public function testOneRegisteredVocabularyIsAccepted(array $classes): void {
    $this->expectNotToPerformAssertions();

    WebContext::assertOneContext($this->createContextSuiteScope($classes));
  }

  public static function dataProviderOneRegisteredVocabularyIsAccepted(): \Iterator {
    yield 'a shipped context' => [[DrupalContext::class, UnrelatedContext::class]];
    yield 'a project context extending a shipped one' => [[FeatureContext::class, UnrelatedContext::class]];
  }

  /**
   * Tests that the message names the registered class to keep.
   *
   * @param array<int, class-string> $classes
   *   The context classes the suite registers, in registration order.
   * @param string $expected_message
   *   The whole message the guard throws with.
   */
  #[DataProvider('dataProviderTwoRegisteredVocabulariesNameTheMistake')]
  public function testTwoRegisteredVocabulariesNameTheMistake(array $classes, string $expected_message): void {
    try {
      WebContext::assertOneContext($this->createContextSuiteScope($classes));
      $this->fail('A suite registering 2 web vocabularies was accepted.');
    }
    catch (\RuntimeException $exception) {
      $this->assertSame($expected_message, $exception->getMessage());
    }
  }

  public static function dataProviderTwoRegisteredVocabulariesNameTheMistake(): \Iterator {
    yield 'both shipped contexts' => [
      [WebContext::class, DrupalContext::class],
      sprintf('The "default" suite registers %1$s and %2$s, which all carry the %1$s vocabulary, so every web step would register more than once. Keep %2$s, which extends every other one, and drop %1$s.', WebContext::class, DrupalContext::class),
    ];
    yield 'a project context beside the shipped context it extends' => [
      [FeatureContext::class, DrupalContext::class],
      sprintf('The "default" suite registers %1$s and %2$s, which all carry the %3$s vocabulary, so every web step would register more than once. Keep %1$s, which extends every other one, and drop %2$s.', FeatureContext::class, DrupalContext::class, WebContext::class),
    ];
    yield 'a project context registered between both shipped contexts' => [
      [WebContext::class, FeatureContext::class, DrupalContext::class],
      sprintf('The "default" suite registers %1$s and %2$s and %3$s, which all carry the %1$s vocabulary, so every web step would register more than once. Keep %2$s, which extends every other one, and drop %1$s and %3$s.', WebContext::class, FeatureContext::class, DrupalContext::class),
    ];
    yield 'project contexts neither of which extends the other' => [
      [FeatureContext::class, UiContext::class],
      sprintf('The "default" suite registers %1$s and %2$s, which all carry the %3$s vocabulary, so every web step would register more than once. None of them extends every other one, so keep 1 and move the steps of the rest into it.', FeatureContext::class, UiContext::class, WebContext::class),
    ];
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
class UnrelatedContext {}

/**
 * Context extending 'DrupalContext', as a project's own context does.
 */
class FeatureContext extends DrupalContext {}

/**
 * Context extending 'WebContext', so neither it nor 'FeatureContext' extends
 * the other.
 */
class UiContext extends WebContext {}
