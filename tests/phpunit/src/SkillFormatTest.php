<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests;

use DrevOps\BehatSteps\DrupalContext;
use Drupal\Component\Serialization\Yaml;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests the agent skills the package ships under 'skills/'.
 *
 * A consuming project's agent reads each skill from 'vendor/', so its
 * frontmatter follows the Agent Skills specification every agent reads, and
 * every method it names still exists in the release it ships with.
 *
 * @see https://agentskills.io/specification
 */
#[CoversNothing]
class SkillFormatTest extends UnitTestCase {

  /**
   * The directory holding 1 directory per skill, relative to the root.
   */
  protected const SKILLS_DIRECTORY = 'skills';

  /**
   * The frontmatter keys the specification defines.
   */
  protected const FRONTMATTER_KEYS = ['name', 'description', 'license', 'compatibility', 'metadata', 'allowed-tools'];

  /**
   * The longest name the specification accepts.
   */
  protected const NAME_MAX_LENGTH = 64;

  /**
   * The longest description the specification accepts.
   */
  protected const DESCRIPTION_MAX_LENGTH = 1024;

  public function testTheSkillsDirectoryHoldsSkills(): void {
    $this->assertNotEmpty(static::discoverSkillFiles(), sprintf('%s/ holds no skill.', static::SKILLS_DIRECTORY));
  }

  #[DataProvider('dataProviderFrontmatterFollowsTheSpecification')]
  public function testFrontmatterFollowsTheSpecification(string $file): void {
    $frontmatter = $this->readFrontmatter($file);
    $directory = basename(dirname($file));

    $this->assertSame([], array_diff(array_keys($frontmatter), static::FRONTMATTER_KEYS), sprintf('%s declares only the frontmatter keys the specification defines.', $file));

    $name = $frontmatter['name'] ?? NULL;
    $this->assertIsString($name, sprintf('%s declares a name.', $file));
    $this->assertMatchesRegularExpression('/^[a-z0-9]+(-[a-z0-9]+)*$/', $name, sprintf('%s names itself in lowercase words joined by single hyphens.', $file));
    $this->assertLessThanOrEqual(static::NAME_MAX_LENGTH, strlen($name), sprintf('%s declares a name of %d characters at most.', $file, static::NAME_MAX_LENGTH));
    $this->assertSame($directory, $name, sprintf('%s declares the name of its directory.', $file));

    $description = $frontmatter['description'] ?? NULL;
    $this->assertIsString($description, sprintf('%s declares a description.', $file));
    $this->assertNotSame('', trim($description), sprintf('%s declares a description.', $file));
    $this->assertLessThanOrEqual(static::DESCRIPTION_MAX_LENGTH, mb_strlen($description), sprintf('%s declares a description of %d characters at most.', $file, static::DESCRIPTION_MAX_LENGTH));
  }

  public static function dataProviderFrontmatterFollowsTheSpecification(): array {
    return static::discoverSkillFiles();
  }

  #[DataProvider('dataProviderNamedMethodsExist')]
  public function testNamedMethodsExist(string $file): void {
    $content = (string) file_get_contents(dirname(__DIR__, 3) . '/' . $file);
    $declared = $this->collectDeclaredMethods($content);

    preg_match_all('/`(?:\$this->)?([a-z][A-Za-z0-9]*)\(\)`/', $content, $matches);

    $missing = array_values(array_filter(array_unique($matches[1]), static fn(string $method): bool => !method_exists(DrupalContext::class, $method) && !function_exists($method) && !in_array($method, $declared, TRUE)));

    $this->assertSame([], $missing, sprintf('%s names only methods that DrupalContext declares or inherits, PHP functions or methods its own code declares.', $file));
  }

  public static function dataProviderNamedMethodsExist(): array {
    return static::discoverSkillFiles();
  }

  /**
   * Discover the skill files under the skills directory.
   *
   * @return array<string, array{string}>
   *   Paths relative to the repository root, as data provider rows.
   */
  protected static function discoverSkillFiles(): array {
    $files = [];

    foreach (glob(dirname(__DIR__, 3) . '/' . static::SKILLS_DIRECTORY . '/*/SKILL.md') ?: [] as $path) {
      $relative = static::SKILLS_DIRECTORY . '/' . basename(dirname($path)) . '/SKILL.md';
      $files[$relative] = [$relative];
    }

    ksort($files);

    return $files;
  }

  /**
   * Read the frontmatter of a skill file.
   *
   * @param string $file
   *   The skill file, relative to the repository root.
   *
   * @return array<string, mixed>
   *   The frontmatter, keyed by name.
   */
  protected function readFrontmatter(string $file): array {
    $content = (string) file_get_contents(dirname(__DIR__, 3) . '/' . $file);

    $this->assertMatchesRegularExpression('/^---\n.*?\n---\n/s', $content, sprintf('%s opens with frontmatter between 2 "---" lines.', $file));
    preg_match('/^---\n(.*?)\n---\n/s', $content, $matches);

    $frontmatter = Yaml::decode($matches[1] ?? '');
    $this->assertIsArray($frontmatter, sprintf('%s holds frontmatter that parses to a map.', $file));

    /** @var array<string, mixed> $frontmatter */
    return $frontmatter;
  }

  /**
   * Collect the methods a skill's PHP code blocks declare.
   *
   * @param string $content
   *   The skill file's content.
   *
   * @return list<string>
   *   The declared method names.
   */
  protected function collectDeclaredMethods(string $content): array {
    preg_match_all('/^```php\n(.*?)^```$/ms', $content, $blocks);
    preg_match_all('/function\s+([A-Za-z_][A-Za-z0-9_]*)\s*\(/', implode("\n", $blocks[1]), $matches);

    return array_values(array_unique($matches[1]));
  }

}
