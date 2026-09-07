<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests;

use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Asserts that every trait lays its members out in the documented order.
 *
 * A reader looking for a trait's hooks, its steps or its helpers finds them
 * in the same place in every file. CONTRIBUTING.md states the layout; this
 * test holds it.
 *
 * The order is settled between groups only. Members inside one group stay in
 * whatever order reads best, which is also what keeps STEPS.md stable: docs.php
 * sorts steps by Given, When and Then and preserves source order within each.
 */
#[CoversNothing]
class MemberOrderTest extends UnitTestCase {

  /**
   * Ordering groups, low to high.
   */
  protected const GROUP_COMPOSITION = 0;

  protected const GROUP_CONSTANT = 1;

  protected const GROUP_PROPERTY = 2;

  protected const GROUP_HOOK = 3;

  protected const GROUP_GIVEN = 4;

  protected const GROUP_WHEN = 5;

  protected const GROUP_THEN = 6;

  protected const GROUP_PUBLIC = 7;

  protected const GROUP_PROTECTED = 8;

  /**
   * How each group is named in a failure message.
   */
  protected const GROUP_LABELS = [
    self::GROUP_COMPOSITION => 'composed trait',
    self::GROUP_CONSTANT => 'constant',
    self::GROUP_PROPERTY => 'property',
    self::GROUP_HOOK => 'hook',
    self::GROUP_GIVEN => 'Given step',
    self::GROUP_WHEN => 'When step',
    self::GROUP_THEN => 'Then step',
    self::GROUP_PUBLIC => 'public method',
    self::GROUP_PROTECTED => 'protected helper',
  ];

  /**
   * Step attributes mapped to the group the method they mark belongs to.
   */
  protected const STEP_GROUPS = [
    Given::class => self::GROUP_GIVEN,
    When::class => self::GROUP_WHEN,
    Then::class => self::GROUP_THEN,
  ];

  /**
   * Assert that a trait's members appear in non-descending group order.
   *
   * @param string $trait
   *   Fully qualified trait name.
   */
  #[DataProvider('dataProviderMembersFollowDocumentedOrder')]
  public function testMembersFollowDocumentedOrder(string $trait): void {
    $members = static::orderedMembers($trait);

    $this->assertNotSame([], $members, sprintf('No members were resolved for %s.', $trait));

    $violations = [];
    $highest = NULL;

    foreach ($members as $member) {
      if ($highest !== NULL && $member['group'] < $highest['group']) {
        $violations[] = sprintf('%s is a %s but follows %s, a %s.', $member['name'], static::GROUP_LABELS[$member['group']], $highest['name'], static::GROUP_LABELS[$highest['group']]);
        continue;
      }

      $highest = $member;
    }

    $this->assertSame([], $violations, sprintf('%s lays its members out against the order documented in CONTRIBUTING.md.', $trait));
  }

  /**
   * Both conventions must cover the same traits, so discovery is shared.
   */
  public static function dataProviderMembersFollowDocumentedOrder(): array {
    return PublicSurfaceTest::dataProviderPublicMethodsAreStepsOrHooks();
  }

  /**
   * Return a trait's own members in source order, tagged with their group.
   *
   * Reflection flattens a composed trait's members into the composing trait,
   * so a member is taken as this trait's own when its declaration is found in
   * this trait's file.
   *
   * @param string $trait
   *   Fully qualified trait name.
   *
   * @return array<int, array{name: string, group: int}>
   *   Members ordered by the line declaring them.
   */
  protected static function orderedMembers(string $trait): array {
    /** @var class-string $trait */
    $reflection = new \ReflectionClass($trait);
    $file = (string) realpath((string) $reflection->getFileName());
    $lines = file($file) ?: [];

    $members = [];

    // A composition is read from the source rather than from
    // ReflectionClass::getTraitNames(), which reports the composed trait's
    // real name and so cannot be matched against an aliased import.
    foreach ($lines as $index => $line) {
      if (preg_match('/^\s+use\s+([A-Za-z\\\\][\w\\\\]*)\s*;/', $line, $matches) === 1) {
        $members[] = ['name' => 'use ' . $matches[1], 'group' => static::GROUP_COMPOSITION, 'line' => $index + 1];
      }
    }

    $constants = $reflection->getReflectionConstants();
    foreach ($constants as $constant) {
      $members[] = static::locate($lines, '/(^|\s)const\s+' . preg_quote($constant->getName(), '/') . '\s*=/', $constant->getName(), static::GROUP_CONSTANT);
    }

    $properties = $reflection->getProperties();
    foreach ($properties as $property) {
      $members[] = static::locate($lines, '/^\s*(public|protected|private)\s+(static\s+)?[^(){}]*\$' . preg_quote($property->getName(), '/') . '\s*(=|;)/', '$' . $property->getName(), static::GROUP_PROPERTY);
    }

    $methods = $reflection->getMethods();
    foreach ($methods as $method) {
      if (realpath((string) $method->getFileName()) !== $file) {
        continue;
      }

      $members[] = ['name' => $method->getName() . '()', 'group' => static::methodGroup($method), 'line' => $method->getStartLine()];
    }

    $found = array_values(array_filter($members, static fn(?array $member): bool => $member !== NULL));
    usort($found, static fn(array $a, array $b): int => $a['line'] <=> $b['line']);

    return $found;
  }

  /**
   * Locate a member declared without a reflectable line number.
   *
   * @param array<int, string> $lines
   *   Lines of the file declaring the trait.
   * @param string $pattern
   *   Pattern matching the declaration.
   * @param string $name
   *   Member name for a failure message.
   * @param int $group
   *   Ordering group the member belongs to.
   *
   * @return array{name: string, group: int, line: int}|null
   *   The member, or NULL when the declaration is in a composed trait.
   */
  protected static function locate(array $lines, string $pattern, string $name, int $group): ?array {
    foreach ($lines as $index => $line) {
      if (preg_match($pattern, $line) === 1) {
        return ['name' => $name, 'group' => $group, 'line' => $index + 1];
      }
    }

    return NULL;
  }

  /**
   * Return the ordering group a method belongs to.
   *
   * Hook attributes are recognised by namespace so that a hook from the Drupal
   * Extension counts alongside Behat's own.
   */
  protected static function methodGroup(\ReflectionMethod $method): int {
    $attributes = $method->getAttributes();

    foreach ($attributes as $attribute) {
      $name = $attribute->getName();

      if (str_contains($name, '\\Hook\\')) {
        return static::GROUP_HOOK;
      }

      if (array_key_exists($name, static::STEP_GROUPS)) {
        return static::STEP_GROUPS[$name];
      }
    }

    return $method->isPublic() ? static::GROUP_PUBLIC : static::GROUP_PROTECTED;
  }

}
