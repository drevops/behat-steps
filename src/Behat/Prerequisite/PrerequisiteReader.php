<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\Prerequisite;

use DrevOps\BehatSteps\Behat\Config\GroupName;

/**
 * Reads the prerequisites a trait declares.
 *
 * A trait declares them in a '<prefix>Prerequisites()' method, named by the
 * prefix its other methods carry, as it declares its options in
 * '<prefix>ConfigSchema()'. A consuming project's own trait takes part without
 * being registered anywhere.
 */
class PrerequisiteReader {

  /**
   * Suffix of the method a trait declares its prerequisites in.
   */
  public const string METHOD_SUFFIX = 'Prerequisites';

  /**
   * Declared prerequisites, keyed by context class and trait name.
   *
   * Declarations are code, so they cannot change within a run, and a step
   * that checks them would otherwise rebuild them on every call. A context
   * can redeclare the declaring method, so the key includes its class.
   *
   * @var array<string, array<int, \DrevOps\BehatSteps\Behat\Prerequisite\Prerequisite>>
   */
  protected static array $cache = [];

  /**
   * Names the method a trait declares its prerequisites in.
   *
   * @param string $trait
   *   The trait name, fully qualified or short.
   *
   * @return string
   *   The method name, such as 'watchdogPrerequisites'.
   */
  public static function methodFor(string $trait): string {
    return GroupName::toMethodPrefix(GroupName::fromTraitName($trait)) . self::METHOD_SUFFIX;
  }

  /**
   * Reads the prerequisites a trait declares.
   *
   * @param object $context
   *   A context composing the trait.
   * @param string $trait
   *   The trait name, fully qualified or short.
   *
   * @return array<int, \DrevOps\BehatSteps\Behat\Prerequisite\Prerequisite>
   *   The prerequisites in declaration order, empty when the trait declares
   *   none.
   *
   * @throws \RuntimeException
   *   When the declaring method returns anything other than a list of
   *   prerequisites.
   */
  public function read(object $context, string $trait): array {
    $key = $context::class . '|' . $trait;

    if (isset(self::$cache[$key])) {
      return self::$cache[$key];
    }

    $method = self::methodFor($trait);

    if (!method_exists($context, $method)) {
      self::$cache[$key] = [];

      return [];
    }

    $where = sprintf('%s::%s()', $trait, $method);
    $declarations = (new \ReflectionMethod($context, $method))->invoke($context);

    if (!is_array($declarations) || !array_is_list($declarations)) {
      throw new \RuntimeException(sprintf('%s must return a list of %s objects.', $where, Prerequisite::class));
    }

    $prerequisites = [];

    foreach ($declarations as $declaration) {
      if (!$declaration instanceof Prerequisite) {
        throw new \RuntimeException(sprintf('%s must return a list of %s objects, but it lists a %s.', $where, Prerequisite::class, get_debug_type($declaration)));
      }

      $prerequisites[] = $declaration;
    }

    self::$cache[$key] = $prerequisites;

    return $prerequisites;
  }

}
