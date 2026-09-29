<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Behat\Config;

use DrevOps\BehatSteps\Behat\Config\GroupName;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests the group naming the runtime and the generator share.
 */
#[CoversClass(GroupName::class)]
class GroupNameTest extends UnitTestCase {

  /**
   * Tests that a method prefix converts to a group name and back.
   *
   * @param string $prefix
   *   The prefix the declaring method carries.
   * @param string $group
   *   The group name it converts to.
   */
  #[DataProvider('dataProviderMethodPrefix')]
  public function testMethodPrefix(string $prefix, string $group): void {
    $this->assertSame($group, GroupName::fromMethodPrefix($prefix));
    $this->assertSame($prefix, GroupName::toMethodPrefix($group));
  }

  public static function dataProviderMethodPrefix(): \Iterator {
    yield 'one word' => ['cache', 'cache'];
    yield 'two words' => ['bigPipe', 'big_pipe'];
    yield 'three words' => ['configOverrideExtra', 'config_override_extra'];
    yield 'a trailing digit' => ['axe2', 'axe2'];
  }

  /**
   * Tests that a trait name converts to a group name and back.
   *
   * @param string $trait_name
   *   The short trait name.
   * @param string $group
   *   The group name it converts to.
   */
  #[DataProvider('dataProviderTraitName')]
  public function testTraitName(string $trait_name, string $group): void {
    $this->assertSame($group, GroupName::fromTraitName($trait_name));
    $this->assertSame($trait_name, GroupName::toTraitName($group));
  }

  public static function dataProviderTraitName(): \Iterator {
    yield 'one word' => ['CacheTrait', 'cache'];
    yield 'two words' => ['BigPipeTrait', 'big_pipe'];
    yield 'three words' => ['FileDownloadExtraTrait', 'file_download_extra'];
  }

  public function testNameWithoutTheTraitSuffixIsReadAsIs(): void {
    $this->assertSame('big_pipe', GroupName::fromTraitName('BigPipe'));
  }

  /**
   * Tests that a run of capitals reads as one word.
   *
   * A trait carrying an acronym has to derive the same group from its name as
   * from the prefix its declaring method carries, or the option that switches
   * it off cannot be reached by the trait name.
   */
  public function testAcronymDerivesOneGroupFromBothNames(): void {
    $this->assertSame('api_client', GroupName::fromTraitName('APIClientTrait'));
    $this->assertSame('api_client', GroupName::fromMethodPrefix('apiClient'));
    $this->assertSame('http_cache', GroupName::fromMethodPrefix('HTTPCache'));
  }

  /**
   * Tests that a trait name names the method it declares its options in.
   *
   * @param string $trait_name
   *   The short trait name.
   * @param string $method
   *   The method name it converts to.
   */
  #[DataProvider('dataProviderSchemaMethod')]
  public function testSchemaMethod(string $trait_name, string $method): void {
    $this->assertSame($method, GroupName::schemaMethod($trait_name));
  }

  public static function dataProviderSchemaMethod(): \Iterator {
    yield 'one word' => ['CacheTrait', 'cacheConfigSchema'];
    yield 'two words' => ['BigPipeTrait', 'bigPipeConfigSchema'];
    yield 'three words' => ['FileDownloadExtraTrait', 'fileDownloadExtraConfigSchema'];
  }

}
