<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Fixtures;

/**
 * Test fixture for method without step annotations.
 *
 * This is used to test line 203 in docs.php where sorting returns PHP_INT_MAX
 * for methods that don't have @Given/@When/@Then annotations.
 */
trait NoStepAnnotationTrait {

  /**
   * This method has a description but no step annotation.
   *
   * This should trigger the PHP_INT_MAX return in the sorting function.
   */
  public function nostepannotationMethodWithoutStepAnnotation(): void {}

}
