<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Behat\Config;

use DrevOps\BehatSteps\Behat\Config\Option;
use DrevOps\BehatSteps\Behat\Config\TagOverrides;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests the tag layers of one option.
 */
#[CoversClass(TagOverrides::class)]
class TagOverridesTest extends UnitTestCase {

  /**
   * Tests which tag settles the value of an option that declares bindings.
   *
   * @param list<string> $tags
   *   The tags the scenario and its feature carry, narrower last.
   * @param mixed $expected
   *   The value the tags are expected to settle on.
   */
  #[DataProvider('dataProviderBoundTags')]
  public function testBoundTags(array $tags, mixed $expected): void {
    $option = new Option('fail_on_errors', default: TRUE, description: 'An option.', tags: ['errors-off' => FALSE, 'errors-on' => TRUE]);

    $this->assertSame($expected, (new TagOverrides())->apply('sample', $option, TRUE, $tags));
  }

  public static function dataProviderBoundTags(): \Iterator {
    yield 'no tag leaves the value alone' => [[], TRUE];
    yield 'an unrelated tag leaves the value alone' => [['javascript'], TRUE];
    yield 'a bound tag sets the value' => [['errors-off'], FALSE];
    yield 'the last bound tag wins' => [['errors-off', 'errors-on'], TRUE];
    yield 'the last bound tag wins in the other order' => [['errors-on', 'errors-off'], FALSE];
  }

  public function testAnOptionWithNoBindingIgnoresEveryTag(): void {
    $option = new Option('label', default: 'a default', description: 'An option.');

    $this->assertSame('a default', (new TagOverrides())->apply('sample', $option, 'a default', ['anything']));
  }

  /**
   * Tests that the skip tag of the owning trait switches 'enabled' off.
   *
   * @param string $group
   *   The group the option belongs to.
   * @param list<string> $tags
   *   The tags the scenario and its feature carry.
   * @param bool $expected
   *   The value the tags are expected to settle on.
   */
  #[DataProvider('dataProviderSkipTagBinding')]
  public function testSkipTagBinding(string $group, array $tags, bool $expected): void {
    $option = new Option('enabled', default: TRUE, description: 'An option.');

    $this->assertSame($expected, (new TagOverrides())->apply($group, $option, TRUE, $tags));
  }

  public static function dataProviderSkipTagBinding(): \Iterator {
    yield 'the trait skip tag switches it off' => ['big_pipe', ['behat-steps-skip:BigPipeTrait'], FALSE];
    yield 'another trait skip tag leaves it alone' => ['big_pipe', ['behat-steps-skip:CacheTrait'], TRUE];
    yield 'a hook skip tag leaves it alone' => ['big_pipe', ['behat-steps-skip:bigPipeBeforeStep'], TRUE];
    yield 'a single word group names its trait' => ['cache', ['behat-steps-skip:CacheTrait'], FALSE];
  }

  public function testTheDeclaredBindingsSurviveTheSkipTagBinding(): void {
    $option = new Option('enabled', default: TRUE, description: 'An option.', tags: ['sample-off' => FALSE]);
    $overrides = new TagOverrides();

    $this->assertFalse($overrides->apply('sample', $option, TRUE, ['sample-off']));
    $this->assertFalse($overrides->apply('sample', $option, TRUE, ['behat-steps-skip:SampleTrait']));
  }

}
