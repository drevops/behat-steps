<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests;

use Behat\Gherkin\Node\PyStringNode;
use Behat\Gherkin\Node\TableNode;
use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Asserts that a step method declares exactly the arguments its step binds.
 *
 * Behat binds every placeholder as a string, and a Turnip pattern cannot make
 * a placeholder optional. CONTRIBUTING.md states the rule.
 */
#[CoversNothing]
class StepArgumentTest extends UnitTestCase {

  /**
   * The attributes that register a method as a step.
   */
  protected const STEP_ATTRIBUTES = [Given::class, When::class, Then::class];

  /**
   * The types Behat passes as the multiline argument of a step.
   */
  protected const MULTILINE_TYPES = [PyStringNode::class, TableNode::class];

  /**
   * Assert that every placeholder binds a required string parameter.
   *
   * Behat binds a placeholder to the parameter of the same name, so a missing
   * parameter only fails at run time. Behat passes a number as a string too,
   * so the step parses it.
   *
   * @param string $trait
   *   The trait to check.
   */
  #[DataProvider('dataProviderPlaceholdersBindRequiredStrings')]
  public function testPlaceholdersBindRequiredStrings(string $trait): void {
    $violations = [];

    foreach (static::collectStepMethods($trait) as [$method, $pattern]) {
      $parameters = [];

      foreach ($method->getParameters() as $parameter) {
        $parameters[$parameter->getName()] = $parameter;
      }

      foreach (static::listPlaceholders($pattern) as $placeholder) {
        $parameter = $parameters[$placeholder] ?? NULL;

        if ($parameter === NULL) {
          $violations[] = sprintf('%s() declares no $%s for ":%s"', $method->getName(), $placeholder, $placeholder);

          continue;
        }

        if (!static::isRequiredString($parameter)) {
          $violations[] = sprintf('%s() declares %s for ":%s"', $method->getName(), static::describeParameter($parameter), $placeholder);
        }
      }
    }

    $this->assertSame([], $violations, 'Declare each placeholder as a required "string" named after it: "tableAssertRowCount(string $selector, string $count)", not "int $count" or "?string $count = NULL". Parse a number with "stringParseInteger()" or "stringParseNumber()".');
  }

  public static function dataProviderPlaceholdersBindRequiredStrings(): array {
    return static::discoverTraits();
  }

  /**
   * Assert that a step method declares no parameter its step does not bind.
   *
   * The only parameter beyond the placeholders is the multiline argument,
   * which Behat passes last.
   *
   * @param string $trait
   *   The trait to check.
   */
  #[DataProvider('dataProviderStepsDeclareOnlyTheirArguments')]
  public function testStepsDeclareOnlyTheirArguments(string $trait): void {
    $violations = [];

    foreach (static::collectStepMethods($trait) as [$method, $pattern]) {
      $placeholders = static::listPlaceholders($pattern);
      $last = $method->getNumberOfParameters() - 1;

      foreach ($method->getParameters() as $parameter) {
        if (in_array($parameter->getName(), $placeholders, TRUE)) {
          continue;
        }

        if ($parameter->getPosition() === $last && static::isMultilineArgument($parameter)) {
          continue;
        }

        $violations[] = sprintf('%s() declares %s, which its step does not bind', $method->getName(), static::describeParameter($parameter));
      }
    }

    $this->assertSame([], $violations, 'A step method takes only what its step binds: a "string" per placeholder and a trailing "TableNode" or "PyStringNode". Move a parameter only PHP callers pass to a helper.');
  }

  public static function dataProviderStepsDeclareOnlyTheirArguments(): array {
    return static::discoverTraits();
  }

  /**
   * Collect the step methods a trait declares in its own file.
   *
   * @param string $trait
   *   The trait to read.
   *
   * @return array<int, array{\ReflectionMethod, string}>
   *   Each step method paired with its step pattern.
   */
  protected static function collectStepMethods(string $trait): array {
    $reflection = static::reflect($trait);
    $steps = [];

    foreach ($reflection->getMethods() as $method) {
      // A composed helper trait reports its methods too, so only the methods
      // declared in this file are the trait's own.
      if ($method->getFileName() !== $reflection->getFileName()) {
        continue;
      }

      foreach ($method->getAttributes() as $attribute) {
        if (!in_array($attribute->getName(), static::STEP_ATTRIBUTES, TRUE)) {
          continue;
        }

        /** @var \Behat\Step\Given|\Behat\Step\When|\Behat\Step\Then $step */
        $step = $attribute->newInstance();
        $steps[] = [$method, (string) $step->getPattern()];
      }
    }

    return $steps;
  }

  /**
   * List the placeholder names in a step pattern.
   *
   * @param string $pattern
   *   The Turnip step pattern.
   *
   * @return array<int, string>
   *   The placeholder names, without the leading colon.
   */
  protected static function listPlaceholders(string $pattern): array {
    preg_match_all('/:(\w+)/', $pattern, $matches);

    return $matches[1];
  }

  /**
   * Check whether a parameter is a required, non-nullable string.
   */
  protected static function isRequiredString(\ReflectionParameter $parameter): bool {
    $type = $parameter->getType();

    return $type instanceof \ReflectionNamedType && $type->getName() === 'string' && !$type->allowsNull() && !$parameter->isDefaultValueAvailable();
  }

  /**
   * Check whether a parameter is a required multiline argument.
   */
  protected static function isMultilineArgument(\ReflectionParameter $parameter): bool {
    $type = $parameter->getType();

    return $type instanceof \ReflectionNamedType && in_array($type->getName(), static::MULTILINE_TYPES, TRUE) && !$type->allowsNull() && !$parameter->isDefaultValueAvailable();
  }

  /**
   * Describe a parameter by its type, name and default value.
   */
  protected static function describeParameter(\ReflectionParameter $parameter): string {
    $type = $parameter->getType();
    $description = ($type instanceof \ReflectionType ? $type . ' ' : '') . '$' . $parameter->getName();

    if ($parameter->isDefaultValueAvailable()) {
      $description .= ' = ' . var_export($parameter->getDefaultValue(), TRUE);
    }

    return $description;
  }

}
