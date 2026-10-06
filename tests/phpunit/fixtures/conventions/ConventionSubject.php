<?php

/**
 * @file
 * A class holding 1 method of each kind the test conventions tell apart.
 *
 * The file sits outside 'tests/phpunit/src', so the convention checks do not
 * read it as part of the suite.
 */

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Fixtures\Conventions;

/**
 * Base the subject overrides a method of.
 */
abstract class ConventionSubjectBase {

  /**
   * A method the subject overrides.
   */
  protected function inherited(): void {}

}

/**
 * Trait the subject composes, declared in the same file as the subject.
 */
trait ConventionSubjectTrait {

  /**
   * A method the subject redeclares.
   */
  public function redeclared(): void {}

  /**
   * A method the subject takes as it is.
   */
  public function imported(): void {}

}

/**
 * The subject.
 */
class ConventionSubject extends ConventionSubjectBase implements \Countable {

  use ConventionSubjectTrait;

  /**
   * A constructor.
   */
  public function __construct() {}

  /**
   * A test.
   */
  public function testSomething(): void {}

  /**
   * A data provider.
   */
  public static function dataProviderSomething(): array {
    return [];
  }

  /**
   * A lifecycle method.
   */
  protected function setUp(): void {}

  /**
   * An override of a parent method.
   */
  protected function inherited(): void {}

  /**
   * An implementation of an interface method.
   */
  public function count(): int {
    return 0;
  }

  /**
   * An override of a trait method.
   */
  public function redeclared(): void {}

  /**
   * A helper named with a verb.
   */
  protected function createThing(): void {}

  /**
   * A helper named with a noun.
   */
  protected function thing(): void {}

  /**
   * A test-only method running a protected method.
   */
  public function callRun(): void {}

  /**
   * A public method with no prefix and nothing it overrides.
   */
  public function bare(): void {}

  /**
   * A public method carrying an attribute.
   */
  #[\ReturnTypeWillChange]
  public function attributed(): void {}

}
