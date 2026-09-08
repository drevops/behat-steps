<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\Generator;

use Behat\Behat\Context\ContextClass\ClassGenerator as BehatClassGenerator;
use Behat\Testwork\Suite\Suite;

/**
 * Generates a starting context class that extends 'RawContext'.
 *
 * Replaces Behat's own generator behind the
 * 'context.class_generator.simple' service.
 *
 * The '$contextClass' parameters stay untyped so the declaration matches both
 * the untyped Behat 3 interface and the 'string'-typed Behat 4 one.
 */
class ClassGenerator implements BehatClassGenerator {

  /**
   * Template for generated context class files.
   */
  protected static string $template = <<<'PHP'
<?php

{namespace}use DrevOps\BehatSteps\Behat\Context\RawContext;
use Behat\Gherkin\Node\PyStringNode;
use Behat\Gherkin\Node\TableNode;
use Behat\Behat\Tester\Exception\PendingException;

/**
 * Defines application features from the specific context.
 */
class {className} extends RawContext {

  /**
   * Initializes context.
   *
   * Every scenario gets its own context instance.
   * You can also pass arbitrary arguments to the
   * context constructor through behat.yml.
   */
  public function __construct() {
  }

}

PHP;

  /**
   * {@inheritdoc}
   */
  public function supportsSuiteAndClass(Suite $suite, mixed $contextClass): bool {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function generateClass(Suite $suite, mixed $contextClass): string {
    $fqn = (string) $contextClass;

    $namespace = '';
    $position = strrpos($fqn, '\\');

    if ($position !== FALSE) {
      $namespace = 'namespace ' . substr($fqn, 0, $position) . ";\n\n";
      $contextClass = substr($fqn, $position + 1);
    }

    return strtr(static::$template, [
      '{namespace}' => $namespace,
      '{className}' => $contextClass,
    ]);
  }

}
