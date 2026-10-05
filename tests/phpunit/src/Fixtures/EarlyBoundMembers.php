<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Fixtures;

/**
 * Members that late static binding cannot change, on a class that is not final.
 */
class EarlyBoundMembers {

  final protected const string FIXED = 'fixed';

  /**
   * Returns the fixed value.
   */
  final protected static function fixed(): string {
    return self::FIXED;
  }

}
