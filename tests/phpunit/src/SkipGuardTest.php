<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests;

use Behat\Hook\AfterScenario;
use Behat\Hook\BeforeScenario;
use DrevOps\BehatSteps\Behat\Config\TagOverrides;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests that every scenario hook is switched off by its trait's skip tag.
 *
 * A skip tag names a trait and switches off every hook that trait registers,
 * so a guard names its own trait through '__TRAIT__' and never a hook. A
 * scenario hook that has nothing to switch off is listed in UNGUARDED_HOOKS
 * with the reason, so each one is a reviewed decision.
 */
#[CoversNothing]
class SkipGuardTest extends UnitTestCase {

  /**
   * The call a guarded scenario hook makes.
   */
  protected const GUARD = '$this->skipTag(__TRAIT__, ';

  /**
   * Scenario hooks that carry no skip guard, and why.
   */
  protected const UNGUARDED_HOOKS = [
    'Helper\\Drupal\\StaticCacheTrait::staticCacheClear' => 'Clears the static caches the scenario filled.',
    'Steps\\Drupal\\ConfigTrait::configBeforeScenario' => 'Clears the snapshot registry.',
    'Steps\\Drupal\\StateTrait::stateBeforeScenario' => 'Clears the snapshot registry.',
    'Steps\\Drupal\\WatchdogTrait::watchdogAfterScenario' => 'Checks only a scenario whose start time watchdogSetScenario() set behind its guard.',
    'Steps\\Web\\AccessibilityTrait::accessibilityFinalizeScenario' => 'Reads the flag accessibilitySetupScenario() sets behind its guard.',
    'Steps\\Web\\CommandTrait::commandAfterScenario' => 'Clears the captured command output.',
    'Steps\\Web\\CommandTrait::commandBeforeScenario' => 'Clears the captured command output.',
    'Steps\\Web\\FieldTrait::fieldAfterScenario' => 'Clears the form validation registry.',
    'Steps\\Web\\JavascriptTrait::javascriptAfterScenario' => 'Reads the flag javascriptBeforeScenario() sets behind its guard.',
    'Steps\\Web\\JsonTrait::jsonAfterScenario' => 'Clears the decoded JSON.',
    'Steps\\Web\\JsonTrait::jsonBeforeScenario' => 'Clears the decoded JSON.',
    'Steps\\Web\\RandomTrait::randomAfterScenario' => 'Clears the resolved token values.',
    'Steps\\Web\\ResponsiveTrait::responsiveBeforeScenario' => 'Acts only on a "@breakpoint:" tag on the scenario or its feature.',
    'Steps\\Web\\XmlTrait::xmlAfterScenario' => 'Clears the loaded XML document.',
    'Steps\\Web\\XmlTrait::xmlBeforeScenario' => 'Clears the loaded XML document and the libxml error buffer.',
  ];

  /**
   * Tests that a scenario hook is guarded, or listed with its reason.
   *
   * @param string $trait
   *   Fully qualified trait name.
   * @param string $method
   *   The hook method the trait declares.
   */
  #[DataProvider('dataProviderScenarioHookIsGuarded')]
  public function testScenarioHookIsGuarded(string $trait, string $method): void {
    $hook = static::hookLabel($trait, $method);
    $guarded = str_contains(static::methodSource($trait, $method), static::GUARD);

    if (array_key_exists($hook, static::UNGUARDED_HOOKS)) {
      $this->assertFalse($guarded, sprintf('%s carries a skip guard, so remove it from UNGUARDED_HOOKS.', $hook));

      return;
    }

    $this->assertTrue($guarded, sprintf('%s acts without a skip guard. Open it with "if (%s$scope))", or list it in UNGUARDED_HOOKS with the reason it has nothing to switch off.', $hook, static::GUARD));
  }

  public static function dataProviderScenarioHookIsGuarded(): array {
    return static::discoverScenarioHooks();
  }

  public function testUnguardedHooksExist(): void {
    $stale = array_diff(array_keys(static::UNGUARDED_HOOKS), array_keys(static::discoverScenarioHooks()));

    $this->assertSame([], array_values($stale), 'UNGUARDED_HOOKS lists a hook no trait registers.');
  }

  /**
   * Tests that a trait reads the skip tag only through a guard naming itself.
   *
   * @param string $trait
   *   Fully qualified trait name.
   */
  #[DataProvider('dataProviderSkipGuardsNameTheirTrait')]
  public function testSkipGuardsNameTheirTrait(string $trait): void {
    $violations = [];
    $tokens = static::significantTokens((string) static::reflect($trait)->getFileName());

    foreach ($tokens as $index => $token) {
      if (!is_array($token)) {
        continue;
      }

      if ($token[0] === T_CONSTANT_ENCAPSED_STRING && str_contains($token[1], TagOverrides::SKIP_TAG_PREFIX)) {
        $violations[] = sprintf('Line %d reads a skip tag directly.', $token[2]);
      }

      $argument = $tokens[$index + 2] ?? NULL;

      if ($token[0] === T_STRING && $token[1] === 'skipTag' && (!is_array($argument) || $argument[0] !== T_TRAIT_C)) {
        $violations[] = sprintf('Line %d passes skipTag() a name other than __TRAIT__.', $token[2]);
      }
    }

    $this->assertSame([], $violations, 'A skip tag names a trait, so a hook reads it only through "skipTag(__TRAIT__, $scope)".');
  }

  public static function dataProviderSkipGuardsNameTheirTrait(): array {
    return static::discoverTraits();
  }

  /**
   * Return every scenario hook a trait declares in its own file.
   *
   * @return array<string, array{string, string}>
   *   Trait name and method name, keyed by the hook label.
   */
  protected static function discoverScenarioHooks(): array {
    $hooks = [];

    foreach (array_keys(static::discoverTraits()) as $trait) {
      $reflection = static::reflect($trait);

      foreach ($reflection->getMethods() as $method) {
        // A trait composing another trait reports the composed methods too,
        // so only a method declared in this file is the trait's own.
        if ($method->getFileName() !== $reflection->getFileName()) {
          continue;
        }

        if ($method->getAttributes(BeforeScenario::class) === [] && $method->getAttributes(AfterScenario::class) === []) {
          continue;
        }

        $hooks[static::hookLabel($trait, $method->getName())] = [$trait, $method->getName()];
      }
    }

    ksort($hooks);

    return $hooks;
  }

  /**
   * Label a hook by its trait, relative to the library namespace, and method.
   */
  protected static function hookLabel(string $trait, string $method): string {
    return substr($trait, strlen('DrevOps\\BehatSteps\\')) . '::' . $method;
  }

  /**
   * Return the source lines of one method.
   */
  protected static function methodSource(string $trait, string $method): string {
    $reflection = static::reflect($trait)->getMethod($method);
    $lines = file((string) $reflection->getFileName()) ?: [];
    $start = (int) $reflection->getStartLine();

    return implode('', array_slice($lines, $start - 1, (int) $reflection->getEndLine() - $start + 1));
  }

}
