<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests;

use DrevOps\BehatSteps\Backend\BackendInterface;
use DrevOps\BehatSteps\Backend\Core\Core;
use DrevOps\BehatSteps\Backend\Core\Field\AbstractHandler;
use DrevOps\BehatSteps\Backend\Core\Field\FieldHandlerInterface;
use DrevOps\BehatSteps\Backend\Core\Field\Parser\Exception\ParseException;
use DrevOps\BehatSteps\Backend\Core\Field\SupportedImageHandler;
use DrevOps\BehatSteps\Backend\DrushBackend;
use DrevOps\BehatSteps\Behat\Config\Option;
use DrevOps\BehatSteps\Behat\Context\DrupalContext;
use DrevOps\BehatSteps\Behat\Context\WebContext;
use DrevOps\BehatSteps\Behat\Context\WebRawContext;
use DrevOps\BehatSteps\Behat\Http\HttpClientFactory;
use DrevOps\BehatSteps\Behat\Mink\BrowserAdapterInterface;
use DrevOps\BehatSteps\Behat\Mink\Element\DocumentElement;
use DrevOps\BehatSteps\Behat\Registry\BackendRegistry;
use DrevOps\BehatSteps\Behat\Tag;
use DrevOps\BehatSteps\Exception\AssertionException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Asserts that a class under `src/` is final unless a project extends it.
 *
 * CONTRIBUTING.md states the rule.
 */
#[CoversNothing]
class ExtensionPointTest extends UnitTestCase {

  /**
   * Types a project extends, each with the reason.
   *
   * A type covers itself and every class extending or implementing it.
   */
  protected const EXTENSION_POINTS = [
    WebRawContext::class => 'A project context extends one of the shipped contexts.',
    BackendInterface::class => 'A project backend may extend a shipped one.',
    Core::class => 'A version-specific core extends it to register its own field handlers and creation aliases.',
    FieldHandlerInterface::class => 'A handler for another field type extends a shipped one, as OgStandardReferenceHandler does.',
    BrowserAdapterInterface::class => "An adapter for a browser driver derived from a shipped one extends that driver's adapter.",
    HttpClientFactory::class => 'A package replaces the HTTP client factory service with a subclass.',
    DocumentElement::class => "It is installed in place of Mink's own DocumentElement, which other code extends.",
  ];

  /**
   * Assert that a class is final unless it is an extension point.
   *
   * @param class-string $class
   *   A concrete class under `src/`.
   */
  #[DataProvider('dataProviderClassIsFinalUnlessExtensionPoint')]
  public function testClassIsFinalUnlessExtensionPoint(string $class): void {
    $is_final = static::reflect($class)->isFinal();
    $reason = static::findExtensionReason($class);

    if ($reason === NULL) {
      $this->assertTrue($is_final, sprintf('%s is neither final nor an extension point. Declare it final, or add it to EXTENSION_POINTS with the reason a project extends it.', $class));

      return;
    }

    $this->assertFalse($is_final, sprintf('%s is final, but a project extends it: %s', $class, $reason));
  }

  /**
   * Provides every concrete class under `src/` that no class there extends.
   *
   * A final class cannot be extended, so a class the package extends itself
   * is open whatever this test asserts.
   */
  public static function dataProviderClassIsFinalUnlessExtensionPoint(): array {
    $types = static::discoverSourceTypes();
    $extended = static::collectExtendedClasses($types);

    $classes = [];

    foreach ($types as $key => [$type]) {
      $reflection = static::reflect($type);

      if ($reflection->isInterface() || $reflection->isTrait() || $reflection->isAbstract() || in_array($type, $extended, TRUE)) {
        continue;
      }

      $classes[$key] = [$type];
    }

    return $classes;
  }

  /**
   * Assert that a class is matched against the listed extension points.
   *
   * @param string $class
   *   A fully qualified class name.
   * @param bool $expected
   *   Whether a listed type covers the class.
   */
  #[DataProvider('dataProviderExtensionPointsAreDetected')]
  public function testExtensionPointsAreDetected(string $class, bool $expected): void {
    $this->assertSame($expected, static::findExtensionReason($class) !== NULL);
  }

  public static function dataProviderExtensionPointsAreDetected(): array {
    return [
      'listed class' => [WebRawContext::class, TRUE],
      'subclass of a listed class' => [DrupalContext::class, TRUE],
      'implementation of a listed interface' => [DrushBackend::class, TRUE],
      'subclass of an implementation' => [SupportedImageHandler::class, TRUE],
      'service with an interface' => [BackendRegistry::class, FALSE],
      'value object' => [Option::class, FALSE],
      'exception' => [AssertionException::class, FALSE],
    ];
  }

  /**
   * Assert that a class another class under `src/` extends is collected.
   *
   * @param string $class
   *   A fully qualified class name.
   * @param bool $expected
   *   Whether a class under `src/` extends it.
   */
  #[DataProvider('dataProviderExtendedClassesAreCollected')]
  public function testExtendedClassesAreCollected(string $class, bool $expected): void {
    $this->assertSame($expected, in_array($class, static::collectExtendedClasses(static::discoverSourceTypes()), TRUE));
  }

  public static function dataProviderExtendedClassesAreCollected(): array {
    return [
      'parent of an exception' => [ParseException::class, TRUE],
      'parent of a context' => [WebContext::class, TRUE],
      'abstract base' => [AbstractHandler::class, TRUE],
      'class nothing extends' => [Tag::class, FALSE],
      'handler nothing extends' => [SupportedImageHandler::class, FALSE],
    ];
  }

  /**
   * Find why a project extends a class.
   *
   * @param string $class
   *   A fully qualified class name.
   *
   * @return string|null
   *   The reason given for the listed type covering the class, or NULL when
   *   no listed type covers it.
   */
  protected static function findExtensionReason(string $class): ?string {
    foreach (static::EXTENSION_POINTS as $type => $reason) {
      if ($class === $type || is_subclass_of($class, $type)) {
        return $reason;
      }
    }

    return NULL;
  }

  /**
   * Collect the classes another class under `src/` extends.
   *
   * @param array<string, array{string}> $types
   *   The types under `src/`, as data provider rows.
   *
   * @return array<int, string>
   *   Fully qualified names of the parent classes.
   */
  protected static function collectExtendedClasses(array $types): array {
    $extended = [];

    foreach ($types as [$type]) {
      $parent = static::reflect($type)->getParentClass();

      if ($parent !== FALSE) {
        $extended[] = $parent->getName();
      }
    }

    return array_values(array_unique($extended));
  }

}
