<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests;

use Behat\MinkExtension\Context\RawMinkContext;
use DrevOps\BehatSteps\Behat\Context\DrupalContext;
use DrevOps\BehatSteps\Behat\Context\UserAwareInterface;
use DrevOps\BehatSteps\Behat\Context\WebContext;
use DrevOps\BehatSteps\Behat\Context\WebRawContext;
use DrevOps\BehatSteps\Helper\Web\LastStepTrait;
use DrevOps\BehatSteps\Helper\Web\RequestHeadersTrait;
use DrevOps\BehatSteps\Helper\Web\StringTrait;
use DrevOps\BehatSteps\Tests\Unit\Behat\Fixtures\BareMinkContext;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Asserts what the shipped contexts compose.
 *
 * A project extending a shipped context gets the whole vocabulary that
 * context names. A project composing its own gets the helpers on '$this'
 * without a second copy of their state.
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
   * The shipped chain, from the root down.
   */
  protected const CHAIN = [WebRawContext::class, WebContext::class, DrupalContext::class];

  public function testTheChainIsLinear(): void {
    $parents = [];

    foreach (static::CHAIN as $class) {
      $parent = static::reflect($class)->getParentClass();
      $parents[$class] = $parent instanceof \ReflectionClass ? $parent->getName() : NULL;
    }

    $expected = [
      WebRawContext::class => RawMinkContext::class,
      WebContext::class => WebRawContext::class,
      DrupalContext::class => WebContext::class,
    ];

    $this->assertSame($expected, $parents);
  }

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
    $missing = array_values(array_diff(static::listDirectoryTraits($directory), static::collectComposedTraits($context)));

    $this->assertSame([], $missing, sprintf('%s has to compose every trait under src/Steps/%s.', $context, $directory));
  }

  public static function dataProviderContextComposesItsWholeDirectory(): array {
    return static::listContextRows();
  }

  /**
   * Assert that a context composes no vocabulary beyond its own directory.
   *
   * @param string $directory
   *   The vocabulary directory under 'src/Steps'.
   * @param class-string $context
   *   The context that has to compose the directory's traits.
   */
  #[DataProvider('dataProviderContextComposesNothingElse')]
  public function testContextComposesNothingElse(string $directory, string $context): void {
    $extra = array_values(array_diff(static::collectComposedTraits($context, 'Steps'), static::listDirectoryTraits($directory)));

    $this->assertSame([], $extra, sprintf('%s composes a trait from outside src/Steps/%s.', $context, $directory));
  }

  public static function dataProviderContextComposesNothingElse(): array {
    return static::listContextRows();
  }

  /**
   * Assert that no context re-composes a trait one above it already has.
   *
   * A subclass re-composing an inherited trait registers each of its steps a
   * second time, which Behat rejects with a 'RedundantStepException'.
   */
  public function testTheChainComposesEachTraitOnce(): void {
    $seen = [];
    $repeated = [];

    foreach (static::CHAIN as $class) {
      foreach (static::collectComposedTraits($class) as $trait) {
        if (in_array($trait, $seen, TRUE)) {
          $repeated[] = $trait;
        }

        $seen[] = $trait;
      }
    }

    $this->assertSame([], $repeated);
  }

  public function testTheRootContextComposesTheWebHelpersOnly(): void {
    $expected = [LastStepTrait::class, RequestHeadersTrait::class, StringTrait::class];
    $composed = static::collectComposedTraits(WebRawContext::class, 'Helper');

    $this->assertSame($expected, $composed);
    $this->assertSame([], static::collectComposedTraits(WebRawContext::class, 'Steps'), sprintf('%s registers no steps of its own.', WebRawContext::class));
  }

  public function testTheDrupalContextIsUserAware(): void {
    $this->assertContains(UserAwareInterface::class, class_implements(DrupalContext::class));
  }

  /**
   * Assert that a step trait composes every helper member its body calls.
   *
   * A member reached through '$this' that no composed helper declares fails
   * only when the step runs.
   *
   * @param string $directory
   *   The vocabulary directory under 'src/Steps'.
   */
  #[DataProvider('dataProviderStepTraitsComposeWhatTheyCall')]
  public function testStepTraitsComposeWhatTheyCall(string $directory): void {
    $owners = static::collectHelperMembers();
    $missing = [];

    foreach (static::listDirectoryTraits($directory) as $trait) {
      /** @var class-string $trait */
      $reflection = static::reflect($trait);
      $body = (string) file_get_contents((string) $reflection->getFileName());
      $composed = static::collectComposedMembers($reflection);

      foreach ($owners as $member => $owner) {
        $is_called = str_contains($body, '$this->' . $member . '(') || str_contains($body, 'static::' . $member . '(');

        if ($is_called && !in_array($member, $composed, TRUE)) {
          $missing[] = sprintf('%s calls %s() without composing %s', $reflection->getShortName(), $member, $owner);
        }
      }
    }

    $this->assertSame([], $missing);
  }

  public static function dataProviderStepTraitsComposeWhatTheyCall(): \Iterator {
    yield 'web' => ['Web'];
    yield 'drupal' => ['Drupal'];
  }

  /**
   * Map every public and protected helper member to the trait declaring it.
   *
   * @return array<string, string>
   *   Helper trait short name, keyed by member name.
   */
  protected static function collectHelperMembers(): array {
    $owners = [];

    foreach (glob(dirname(__DIR__, 3) . '/src/Helper/*/*.php') ?: [] as $file) {
      /** @var class-string $trait */
      $trait = 'DrevOps\\BehatSteps\\Helper\\' . basename(dirname($file)) . '\\' . basename($file, '.php');

      foreach (static::reflect($trait)->getMethods() as $method) {
        $owners[$method->getName()] = basename($file, '.php');
      }
    }

    return $owners;
  }

  /**
   * List the member names a trait reaches through its own composition.
   *
   * @param \ReflectionClass<object> $reflection
   *   The trait to walk.
   *
   * @return array<int, string>
   *   Method names, including the trait's own.
   */
  protected static function collectComposedMembers(\ReflectionClass $reflection): array {
    $members = array_map(static fn(\ReflectionMethod $method): string => $method->getName(), $reflection->getMethods());

    foreach ($reflection->getTraits() as $composed) {
      $members = array_merge($members, static::collectComposedMembers($composed));
    }

    return array_values(array_unique($members));
  }

  /**
   * Assert that a helper trait composed twice holds 1 slot of state.
   *
   * A step trait and the root context compose the same helper, so they read
   * and write the same state rather than a copy each.
   */
  public function testHelperComposedTwiceSharesItsState(): void {
    $context = new HelperStateSubject();

    $context->requestHeadersSet('X-A', '1');
    $context->testSetThroughTrait('X-B', '2');

    $this->assertSame(['X-A' => '1', 'X-B' => '2'], $context->testReadThroughTrait());

    $properties = static::reflect($context)->getProperties();
    $slots = array_filter($properties, static fn(\ReflectionProperty $property): bool => $property->getName() === 'requestHeaders');

    $this->assertCount(1, $slots, 'A helper trait composed at both levels declares one property, not two.');
  }

  /**
   * Assert that every trait annotated for a bare Mink host composes on one.
   *
   * The fixture proves the composition compiles; this test compares the
   * fixture with the annotations, so a trait whose requirement tightens is
   * caught.
   */
  public function testTheBareMinkFixtureMatchesTheAnnotatedTraits(): void {
    $annotated = [];

    foreach (static::listDirectoryTraits('Web') as $trait) {
      /** @var class-string $trait */
      $comment = (string) static::reflect($trait)->getDocComment();

      if (str_contains($comment, '@phpstan-require-extends \Behat\MinkExtension\Context\RawMinkContext')) {
        $annotated[] = $trait;
      }
    }

    sort($annotated);

    $composed = static::collectComposedTraits(BareMinkContext::class);
    sort($composed);

    $this->assertSame($annotated, $composed);
  }

  /**
   * Pair each vocabulary directory with the context composing it.
   *
   * @return array<string, array{string, class-string}>
   *   Directory and context, keyed by directory.
   */
  protected static function listContextRows(): array {
    $rows = [];

    foreach (static::CONTEXTS as $directory => $context) {
      $rows[$directory] = [$directory, $context];
    }

    return $rows;
  }

  /**
   * List the traits declared in 1 vocabulary directory.
   *
   * @param string $directory
   *   The directory name under 'src/Steps'.
   *
   * @return array<int, string>
   *   Fully qualified trait names, sorted.
   */
  protected static function listDirectoryTraits(string $directory): array {
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
   * @param string|null $directory
   *   The 'src' subdirectory a trait has to sit in, or NULL for every trait.
   *
   * @return array<int, string>
   *   Fully qualified trait names, sorted.
   */
  protected static function collectComposedTraits(string $class, ?string $directory = NULL): array {
    $traits = static::reflect($class)->getTraits();

    if ($directory !== NULL) {
      $path = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . $directory . DIRECTORY_SEPARATOR;
      $traits = array_filter($traits, static fn(\ReflectionClass $trait): bool => str_starts_with((string) $trait->getFileName(), $path));
    }

    $names = array_keys($traits);
    sort($names);

    return $names;
  }

}

/**
 * Base context composing 'RequestHeadersTrait'.
 */
class HelperStateBase {

  use RequestHeadersTrait;

}

/**
 * Step trait composing the same 'RequestHeadersTrait' as its host.
 */
trait HelperStateStepTrait {

  use RequestHeadersTrait;

  public function testSetThroughTrait(string $name, string $value): void {
    $this->requestHeadersSet($name, $value);
  }

  /**
   * Read the headers the trait writes.
   *
   * @return array<string, string>
   *   Header values keyed by header name.
   */
  public function testReadThroughTrait(): array {
    return $this->requestHeadersAll();
  }

}

/**
 * Context composing 'RequestHeadersTrait' at both levels.
 */
class HelperStateSubject extends HelperStateBase {

  use HelperStateStepTrait;

}
