<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Fixtures\Generic;

/**
 * Contract carrying the docblocks the fixture inherits.
 */
interface InheritedContractInterface {

  /**
   * Read the contract value.
   */
  public function inheritedValue(): string;

  /**
   * Reach the machinery.
   *
   * @internal
   *   Called by the initializer.
   */
  public function inheritedInternal(): void;

}

/**
 * Parent carrying the docblock the fixture inherits.
 */
class InheritedParent {

  /**
   * Read the parent value.
   */
  public function inheritedParentValue(): string {
    return 'parent';
  }

}

/**
 * Fixture whose docblocks are inherited from its parent and its contract.
 */
class InheritedChild extends InheritedParent implements InheritedContractInterface {

  /**
   * {@inheritdoc}
   */
  public function inheritedValue(): string {
    return 'value';
  }

  /**
   * {@inheritdoc}
   */
  public function inheritedInternal(): void {}

  /**
   * {@inheritdoc}
   */
  public function inheritedParentValue(): string {
    return 'child';
  }

  /**
   * {@inheritdoc}
   */
  public function inheritedUnresolved(): string {
    return 'unresolved';
  }

}
