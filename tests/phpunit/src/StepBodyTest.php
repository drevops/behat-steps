<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests;

use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use PhpParser\Node;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\Match_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Throw_;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Do_;
use PhpParser\Node\Stmt\Else_;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\For_;
use PhpParser\Node\Stmt\Foreach_;
use PhpParser\Node\Stmt\If_;
use PhpParser\Node\Stmt\Nop;
use PhpParser\Node\Stmt\Switch_;
use PhpParser\Node\Stmt\TryCatch;
use PhpParser\Node\Stmt\While_;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Asserts that every step body is a thin wrapper over named helpers.
 *
 * A step parses its arguments, calls helpers, and guards and throws on what
 * they return, so a project's own step definitions reach the same behavior
 * through the helpers. CONTRIBUTING.md states the rule.
 */
#[CoversNothing]
class StepBodyTest extends UnitTestCase {

  /**
   * The most statements a step body runs, its guards aside.
   */
  protected const MAX_STATEMENTS = 4;

  /**
   * The attributes that register a method as a step.
   */
  protected const STEP_ATTRIBUTES = [Given::class, When::class, Then::class];

  /**
   * Tests that no step of a trait calls another step of it.
   *
   * @param string $file
   *   The absolute path to a step trait.
   */
  #[DataProvider('dataProviderStepsCallNoStep')]
  public function testStepsCallNoStep(string $file): void {
    $violations = static::findStepCalls((string) file_get_contents($file));

    $this->assertSame([], $violations, sprintf('Move the logic both steps share into a helper they both call: %s.', implode('; ', $violations)));
  }

  public static function dataProviderStepsCallNoStep(): array {
    return static::discoverStepFiles();
  }

  /**
   * Tests that every step body only parses, calls helpers and guards.
   *
   * @param string $file
   *   The absolute path to a step trait.
   */
  #[DataProvider('dataProviderStepBodiesAreThin')]
  public function testStepBodiesAreThin(string $file): void {
    $violations = static::findThickStepBodies((string) file_get_contents($file));

    $this->assertSame([], $violations, sprintf('Move the logic of these steps into named helpers, so a step only parses its arguments, calls helpers and guards on what they return: %s.', implode('; ', $violations)));
  }

  public static function dataProviderStepBodiesAreThin(): array {
    return static::discoverStepFiles();
  }

  /**
   * Tests that a step calling another step is found, and nothing else is.
   *
   * @param string $methods
   *   The methods of a trait, as PHP source.
   * @param array<int, string> $expected
   *   The violations the trait holds.
   */
  #[DataProvider('dataProviderStepCallsAreDetected')]
  public function testStepCallsAreDetected(string $methods, array $expected): void {
    $this->assertSame($expected, static::findStepCalls(static::buildTraitSource($methods)));
  }

  public static function dataProviderStepCallsAreDetected(): array {
    $step_a = "  #[Then('a')]\n  public function acmeAssertA(): void {\n    %s\n  }\n";
    $step_b = "  #[Then('b')]\n  public function acmeAssertB(): void {}\n";
    $helper = "  public function acmeGetB(): void {\n    %s\n  }\n";

    return [
      'step calling a helper' => [sprintf($step_a, '$this->acmeGetB();') . $step_b . sprintf($helper, ''), []],
      'step calling a step' => [sprintf($step_a, '$this->acmeAssertB();') . $step_b, ['acmeAssertA() calls acmeAssertB()']],
      'step calling a step twice' => [sprintf($step_a, '$this->acmeAssertB(); $this->acmeAssertB();') . $step_b, ['acmeAssertA() calls acmeAssertB()']],
      'step calling itself' => [sprintf($step_a, '$this->acmeAssertA();'), []],
      'helper calling a step' => [$step_b . sprintf($helper, '$this->acmeAssertB();'), []],
      'call on another object' => [sprintf($step_a, '$other->acmeAssertB();') . $step_b, []],
    ];
  }

  /**
   * Tests that a thick step body is found, and nothing else is.
   *
   * @param string $body
   *   The body of a step method, as PHP source.
   * @param array<int, string> $expected
   *   The violations the step holds.
   */
  #[DataProvider('dataProviderThickStepBodiesAreDetected')]
  public function testThickStepBodiesAreDetected(string $body, array $expected): void {
    $method = sprintf("  #[Then('a step')]\n  public function acmeAssertStep(TableNode \$table): void {\n%s\n  }\n", $body);

    $this->assertSame($expected, static::findThickStepBodies(static::buildTraitSource($method)));
  }

  public static function dataProviderThickStepBodiesAreDetected(): array {
    $guard = 'if ($a) { throw new \RuntimeException(\'a\'); }';

    return [
      'a call' => ['$this->acmeGet();', []],
      '4 statements' => [str_repeat('$this->acmeGet();', 4), []],
      '5 statements' => [str_repeat('$this->acmeGet();', 5), ['acmeAssertStep() holds 5 statements besides its guards']],
      'guards do not count' => [$guard . str_repeat('$this->acmeGet();', 4) . $guard, []],
      'a guard with an else counts' => ['if ($a) { throw new \RuntimeException(\'a\'); } else { $b = 1; }' . str_repeat('$this->acmeGet();', 4), ['acmeAssertStep() holds 5 statements besides its guards']],
      'a condition that returns counts' => ['if ($a) { return; }' . str_repeat('$this->acmeGet();', 4), ['acmeAssertStep() holds 5 statements besides its guards']],
      'a trailing comment does not count' => [str_repeat('$this->acmeGet();', 4) . "\n    // A trailing comment.", []],
      'a trailing comment in a loop does not count' => ["foreach (\$table->getHash() as \$row) {\n      \$this->acmeCreate(\$row);\n      // A trailing comment.\n    }", []],
      'switch' => ['switch ($a) { default: $this->acmeGet(); }', ['acmeAssertStep() holds a switch']],
      'match' => ['$a = match ($b) { default => 1 };', ['acmeAssertStep() holds a match']],
      'try' => ['try { $this->acmeGet(); } catch (\Exception) {}', ['acmeAssertStep() holds a try']],
      'closure' => ['$a = function () {};', ['acmeAssertStep() holds a closure']],
      'arrow function' => ['$a = array_map(fn($b) => $b, []);', ['acmeAssertStep() holds a closure']],
      'loop dispatching 1 call' => ['foreach ($table->getHash() as $row) { $this->acmeCreate($row); }', []],
      'loop dispatching after a guard' => ['foreach ($table->getHash() as $row) { ' . $guard . ' $this->acmeCreate($row); }', []],
      'loop of guards' => ['foreach ($table->getHash() as $row) { ' . $guard . ' }', []],
      'while dispatching 1 call' => ['while ($a = $this->acmeFind()) { $a->delete(); }', []],
      'loop of 2 calls' => ['foreach ($table->getHash() as $row) { $this->acmeCreate($row); $this->acmeCreate($row); }', ['acmeAssertStep() holds a loop doing more than guarding and 1 call']],
      'guard after the call' => ['foreach ($table->getHash() as $row) { $this->acmeCreate($row); ' . $guard . ' }', ['acmeAssertStep() holds a loop doing more than guarding and 1 call']],
      'nested loop' => ['foreach ($a as $b) { foreach ($b as $c) { $this->acmeCreate($c); } }', ['acmeAssertStep() holds a loop doing more than guarding and 1 call']],
      'for' => ['for ($i = 0; $i < 3; $i++) { $this->acmeGet(); $this->acmeGet(); }', ['acmeAssertStep() holds a loop doing more than guarding and 1 call']],
      'do' => ['do { $this->acmeGet(); $this->acmeGet(); } while ($a);', ['acmeAssertStep() holds a loop doing more than guarding and 1 call']],
      '2 reasons' => ['switch ($a) { default: $this->acmeGet(); } $b = fn() => 1;', ['acmeAssertStep() holds a switch, a closure']],
    ];
  }

  public function testHelpersAreNotRead(): void {
    $source = static::buildTraitSource("  public function acmeGetStep(): void {\n    switch (\$a) { default: \$this->acmeGet(); }\n  }\n");

    $this->assertSame([], static::findThickStepBodies($source));
  }

  /**
   * Find the steps that call another step of their trait.
   *
   * @param string $source
   *   The PHP source of a trait.
   *
   * @return array<int, string>
   *   The violations, each naming a step and the step it calls.
   */
  protected static function findStepCalls(string $source): array {
    $steps = static::readStepMethods($source);
    $finder = new NodeFinder();

    $violations = [];
    foreach ($steps as $name => $method) {
      foreach ($finder->findInstanceOf($method->stmts ?? [], MethodCall::class) as $call) {
        if (!$call->var instanceof Variable || $call->var->name !== 'this' || !$call->name instanceof Identifier) {
          continue;
        }

        $callee = $call->name->toString();

        if ($callee !== $name && isset($steps[$callee])) {
          $violations[] = sprintf('%s() calls %s()', $name, $callee);
        }
      }
    }

    return array_values(array_unique($violations));
  }

  /**
   * Find the steps whose body holds logic of its own.
   *
   * @param string $source
   *   The PHP source of a trait.
   *
   * @return array<int, string>
   *   The violations, each naming a step and what its body holds.
   */
  protected static function findThickStepBodies(string $source): array {
    $finder = new NodeFinder();

    $violations = [];
    foreach (static::readStepMethods($source) as $name => $method) {
      $statements = $method->stmts ?? [];

      $reasons = [];
      foreach ($finder->find($statements, static fn(Node $node): bool => $node instanceof Switch_ || $node instanceof Match_ || $node instanceof TryCatch || $node instanceof Closure || $node instanceof ArrowFunction) as $node) {
        $reasons[] = static::describeConstruct($node);
      }

      foreach ($finder->find($statements, static fn(Node $node): bool => $node instanceof Foreach_ || $node instanceof For_ || $node instanceof While_ || $node instanceof Do_) as $loop) {
        if (($loop instanceof Foreach_ || $loop instanceof For_ || $loop instanceof While_ || $loop instanceof Do_) && !static::isDispatchLoop($loop->stmts)) {
          $reasons[] = 'a loop doing more than guarding and 1 call';
        }
      }

      $count = count(array_filter($statements, static fn(Node $statement): bool => !$statement instanceof Nop && !static::isGuard($statement)));

      if ($count > static::MAX_STATEMENTS) {
        $reasons[] = sprintf('%d statements besides its guards', $count);
      }

      if ($reasons !== []) {
        $violations[] = sprintf('%s() holds %s', $name, implode(', ', array_unique($reasons)));
      }
    }

    return $violations;
  }

  /**
   * Read the step methods a trait declares.
   *
   * @param string $source
   *   The PHP source of a trait.
   *
   * @return array<string, \PhpParser\Node\Stmt\ClassMethod>
   *   The step methods, keyed by name.
   */
  protected static function readStepMethods(string $source): array {
    $statements = (new ParserFactory())->createForNewestSupportedVersion()->parse($source) ?? [];
    $statements = (new NodeTraverser(new NameResolver()))->traverse($statements);

    $steps = [];
    foreach ((new NodeFinder())->findInstanceOf($statements, ClassMethod::class) as $method) {
      foreach ($method->attrGroups as $group) {
        foreach ($group->attrs as $attribute) {
          if (in_array($attribute->name->toString(), static::STEP_ATTRIBUTES, TRUE)) {
            $steps[$method->name->toString()] = $method;
          }
        }
      }
    }

    return $steps;
  }

  /**
   * Check whether a loop body only guards, then makes at most 1 call.
   *
   * @param array<\PhpParser\Node\Stmt> $statements
   *   The statements of the loop body.
   */
  protected static function isDispatchLoop(array $statements): bool {
    $statements = array_values(array_filter($statements, static fn(Node $statement): bool => !$statement instanceof Nop));
    $last = array_pop($statements);

    if ($last !== NULL && !$last instanceof Expression && !static::isGuard($last)) {
      return FALSE;
    }

    foreach ($statements as $statement) {
      if (!static::isGuard($statement)) {
        return FALSE;
      }
    }

    return TRUE;
  }

  /**
   * Check whether a statement is a guard: a condition that only throws.
   */
  protected static function isGuard(Node $statement): bool {
    return $statement instanceof If_
      && $statement->elseifs === []
      && !$statement->else instanceof Else_
      && count($statement->stmts) === 1
      && $statement->stmts[0] instanceof Expression
      && $statement->stmts[0]->expr instanceof Throw_;
  }

  /**
   * Describe the construct a step body holds.
   */
  protected static function describeConstruct(Node $node): string {
    return match (TRUE) {
      $node instanceof Switch_ => 'a switch',
      $node instanceof Match_ => 'a match',
      $node instanceof TryCatch => 'a try',
      default => 'a closure',
    };
  }

  /**
   * Build the source of a trait holding the given methods.
   *
   * @param string $methods
   *   The methods, as PHP source.
   */
  protected static function buildTraitSource(string $methods): string {
    return "<?php\n\nuse Behat\\Gherkin\\Node\\TableNode;\nuse Behat\\Step\\Then;\n\ntrait AcmeTrait {\n\n" . $methods . "\n}\n";
  }

  /**
   * Discover the trait files under `src/Steps`.
   *
   * @return array<string, array{string}>
   *   Absolute file paths, keyed by the path relative to `src/Steps`.
   */
  protected static function discoverStepFiles(): array {
    $root = dirname(__DIR__, 3) . '/src/Steps';
    $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));

    $paths = [];
    foreach ($files as $file) {
      if (!$file instanceof \SplFileInfo || $file->getExtension() !== 'php') {
        continue;
      }

      $paths[substr($file->getPathname(), strlen($root) + 1)] = [$file->getPathname()];
    }

    ksort($paths);

    return $paths;
  }

}
