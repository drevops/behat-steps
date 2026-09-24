<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Fixtures\Web;

use Behat\Mink\Element\NodeElement;
use DrevOps\BehatSteps\Attribute\Steps;

/**
 * Sample trait carrying every signature shape the renderer handles.
 */
#[Steps]
trait HelperSignatureTrait {

  /**
   * Take and return a union.
   */
  public function helperSignatureUnion(int|string $value): int|string {
    return $value;
  }

  /**
   * Take and return an intersection.
   */
  public function helperSignatureIntersection(\Countable&\Stringable $value): \Countable&\Stringable {
    return $value;
  }

  /**
   * Take and return a nullable class.
   */
  protected function helperSignatureNullable(?NodeElement $element = NULL): ?NodeElement {
    return $element;
  }

  /**
   * Take an untyped argument and return nothing declared.
   *
   * @param mixed $value
   *   The value.
   */
  protected function helperSignatureUntyped($value) {
    return $value;
  }

  /**
   * Take a variadic argument.
   */
  protected function helperSignatureVariadic(string ...$parts): void {}

  /**
   * Take every kind of default value.
   *
   * @param string $text
   *   A string default.
   * @param array<int, string> $empty
   *   An empty array default.
   * @param array<string, string> $filled
   *   A non-empty array default.
   * @param bool $flag
   *   A boolean default.
   * @param int $count
   *   An integer default.
   * @param string|null $missing
   *   A null default.
   */
  protected static function helperSignatureDefaults(string $text = 'one', array $empty = [], array $filled = ['key' => 'value'], bool $flag = FALSE, int $count = 3, ?string $missing = NULL): void {}

  /**
   * Take and return mixed.
   */
  protected function helperSignatureMixed(mixed $value): mixed {
    return $value;
  }

}
