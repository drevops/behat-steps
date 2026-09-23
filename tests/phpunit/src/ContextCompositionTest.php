<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests;

use DrevOps\BehatSteps\Behat\Context\DrupalContext;
use DrevOps\BehatSteps\Behat\Context\DrupalRawContext;
use DrevOps\BehatSteps\Behat\Context\WebContext;
use DrevOps\BehatSteps\Behat\Context\WebRawContext;
use DrevOps\BehatSteps\Helper\RequestHeadersTrait;
use DrevOps\BehatSteps\Tests\Unit\Behat\Fixtures\BareMinkContext;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Asserts what the shipped contexts compose.
 *
 * A project registering a shipped context gets the whole half it names, and
 * a project composing its own gets the helpers on '$this' without a second
 * copy of their state.
 */
#[CoversNothing]
class ContextCompositionTest extends UnitTestCase {

  /**
   * Vocabulary directories mapped to the context that has to compose them.
   */
  protected const CONTEXTS = [
    'Web' => WebContext::class,
    'Drupal' => DrupalContext::class,
  ];

  /**
   * Assert that a context composes every trait of its own directory.
   *
   * @param string $directory
   *   The vocabulary directory under 'src/Steps'.
   * @param class-string $context
   *   The context that has to compose the directory's traits.
   */
  #[DataProvider('dataProviderContextComposesItsWholeDirectory')]
  public function testContextComposesItsWholeDirectory(string $directory, string $context): void {
    $missing = array_values(array_diff(static::directoryTraits($directory), static::composedTraits($context)));

    $this->assertSame([], $missing, sprintf('%s has to compose every trait under src/Steps/%s.', $context, $directory));
  }

  public static function dataProviderContextComposesItsWholeDirectory(): array {
    return static::contextRows();
  }

  /**
   * Assert that a context composes nothing beyond its own directory.
   *
   * @param string $directory
   *   The vocabulary directory under 'src/Steps'.
   * @param class-string $context
   *   The context that has to compose the directory's traits.
   */
  #[DataProvider('dataProviderContextComposesNothingElse')]
  public function testContextComposesNothingElse(string $directory, string $context): void {
    $extra = array_values(array_diff(static::composedTraits($context), static::directoryTraits($directory)));

    $this->assertSame([], $extra, sprintf('%s composes a trait from outside src/Steps/%s.', $context, $directory));
  }

  public static function dataProviderContextComposesNothingElse(): array {
    return static::contextRows();
  }

  /**
   * Assert that neither vocabulary context registers its sibling's steps.
   */
  public function testTheTwoHalvesShareNoTrait(): void {
    $shared = array_values(array_intersect(static::composedTraits(WebContext::class), static::composedTraits(DrupalContext::class)));

    $this->assertSame([], $shared, 'Two registered contexts composing the same trait register its steps twice.');
  }

  /**
   * Assert that each raw context carries no step vocabulary.
   *
   * @param class-string $context
   *   The raw context to inspect.
   */
  #[DataProvider('dataProviderRawContextsComposeNoVocabulary')]
  public function testRawContextsComposeNoVocabulary(string $context): void {
    $vocabulary = array_values(array_filter(static::composedTraits($context), static fn(string $trait): bool => str_contains($trait, '\\Steps\\')));

    $this->assertSame([], $vocabulary, sprintf('%s registers no steps of its own.', $context));
  }

  public static function dataProviderRawContextsComposeNoVocabulary(): array {
    return [
      'web' => [WebRawContext::class],
      'drupal' => [DrupalRawContext::class],
    ];
  }

  /**
   * Assert that a helper trait composed twice holds one slot of state.
   *
   * A step trait composes the helper it needs and the raw context composes it
   * too, so both reach the same bag rather than a copy each.
   */
  public function testHelperComposedTwiceSharesItsState(): void {
    $context = new HelperStateSubject();

    $context->requestHeadersSet('X-A', '1');
    $context->setThroughTrait('X-B', '2');

    $this->assertSame(['X-A' => '1', 'X-B' => '2'], $context->readThroughTrait());

    $properties = (new \ReflectionClass($context))->getProperties();
    $slots = array_filter($properties, static fn(\ReflectionProperty $property): bool => $property->getName() === 'requestHeaders');

    $this->assertCount(1, $slots, 'A helper trait composed at both levels declares one property, not two.');
  }

  /**
   * Assert that every trait promising a bare Mink host still composes on one.
   *
   * The fixture proves the composition compiles; this holds the fixture
   * against the annotations, so a trait whose requirement tightens is caught.
   */
  public function testTheBareMinkFixtureMatchesTheAnnotatedTraits(): void {
    $annotated = [];

    foreach (static::directoryTraits('Web') as $trait) {
      /** @var class-string $trait */
      $comment = (string) (new \ReflectionClass($trait))->getDocComment();

      if (str_contains($comment, '@phpstan-require-extends \Behat\MinkExtension\Context\RawMinkContext')) {
        $annotated[] = $trait;
      }
    }

    sort($annotated);

    $composed = static::composedTraits(BareMinkContext::class);
    sort($composed);

    $this->assertSame($annotated, $composed);
  }

  /**
   * Pair each vocabulary directory with the context composing it.
   *
   * @return array<string, array{string, class-string}>
   *   Directory and context, keyed by directory.
   */
  protected static function contextRows(): array {
    $rows = [];

    foreach (static::CONTEXTS as $directory => $context) {
      $rows[$directory] = [$directory, $context];
    }

    return $rows;
  }

  /**
   * List the traits declared in one vocabulary directory.
   *
   * @param string $directory
   *   The directory name under 'src/Steps'.
   *
   * @return array<int, string>
   *   Fully qualified trait names, sorted.
   */
  protected static function directoryTraits(string $directory): array {
    $path = dirname(__DIR__, 3) . '/src/Steps/' . $directory;
    $traits = [];

    foreach (scandir($path) ?: [] as $entry) {
      if (!str_ends_with($entry, '.php')) {
        continue;
      }

      $traits[] = 'DrevOps\\BehatSteps\\Steps\\' . $directory . '\\' . basename($entry, '.php');
    }

    sort($traits);

    return $traits;
  }

  /**
   * List the traits a class composes itself.
   *
   * @param class-string $class
   *   The class to inspect.
   *
   * @return array<int, string>
   *   Fully qualified trait names, sorted.
   */
  protected static function composedTraits(string $class): array {
    $traits = array_keys((new \ReflectionClass($class))->getTraits());
    sort($traits);

    return $traits;
  }

}

/**
 * Base context composing the shared header bag.
 */
class HelperStateBase {

  use RequestHeadersTrait;

}

/**
 * Step trait composing the same header bag as its host.
 */
trait HelperStateStepTrait {

  use RequestHeadersTrait;

  public function setThroughTrait(string $name, string $value): void {
    $this->requestHeadersSet($name, $value);
  }

  /**
   * Read the bag the trait writes to.
   *
   * @return array<string, string>
   *   Header values keyed by header name.
   */
  public function readThroughTrait(): array {
    return $this->requestHeadersAll();
  }

}

/**
 * Context reaching the header bag from both levels of composition.
 */
class HelperStateSubject extends HelperStateBase {

  use HelperStateStepTrait;

}
