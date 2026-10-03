<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Behat\Config;

use DrevOps\BehatSteps\Behat\Config\Option;
use DrevOps\BehatSteps\Behat\Config\TagOverrides;
use DrevOps\BehatSteps\Behat\Config\TraitOptionResolver;
use DrevOps\BehatSteps\Behat\Manager\ScenarioTagRegistry;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * Tests the layering of the configuration and tag levels.
 */
#[CoversClass(TraitOptionResolver::class)]
class TraitOptionResolverTest extends UnitTestCase {

  /**
   * Context class the failure messages name.
   */
  protected const CONTEXT = 'Acme\\Tests\\SampleContext';

  public function testEveryOptionStartsAtItsDeclaredDefault(): void {
    $resolver = $this->createResolver();

    $this->assertTrue($resolver->bool('sample', 'enabled'));
    $this->assertSame('a default', $resolver->string('sample', 'label'));
    $this->assertSame(7, $resolver->int('sample', 'limit'));
    $this->assertSame(0.5, $resolver->float('sample', 'ratio'));
    $this->assertSame(['.sample'], $resolver->array('sample', 'selectors'));
    $this->assertNull($resolver->raw('sample', 'anything'));
  }

  public function testDeclaredOptionIsFound(): void {
    $resolver = $this->createResolver();

    $this->assertTrue($resolver->has('sample', 'label'));
    $this->assertFalse($resolver->has('sample', 'missing'));
    $this->assertFalse($resolver->has('missing', 'label'));
  }

  public function testAnUndeclaredOptionIsRejectedOnRead(): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('No trait in ' . self::CONTEXT . ' declares the option "sample.missing". Declared options: other_sample.selectors, sample.enabled, sample.label, sample.limit, sample.ratio, sample.selectors, sample.anything, sample_extra.enabled.');

    $this->createResolver()->raw('sample', 'missing');
  }

  public function testContextDeclaringNothingSaysSo(): void {
    $resolver = new TraitOptionResolver(self::CONTEXT, [], [], [], new ScenarioTagRegistry(), new TagOverrides());

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('declares the option "sample.enabled". Declared options: none.');

    $resolver->raw('sample', 'enabled');
  }

  /**
   * Tests the precedence of the layers an option resolves through.
   *
   * @param array<string, mixed> $steps
   *   The extension's steps section.
   * @param array<string, mixed> $config
   *   The context's config argument.
   * @param list<string> $tags
   *   The tags the scenario and its feature carry, narrower last.
   * @param bool $expected
   *   The value the chain is expected to resolve to.
   */
  #[DataProvider('dataProviderPrecedence')]
  public function testPrecedence(array $steps, array $config, array $tags, bool $expected): void {
    $this->assertSame($expected, $this->createResolver($config, $steps, $tags)->bool('sample', 'enabled'));
  }

  public static function dataProviderPrecedence(): \Iterator {
    yield 'the declaration default' => [[], [], [], TRUE];

    yield 'the steps section beats the default' => [
      ['sample' => ['enabled' => FALSE]],
      [],
      [],
      FALSE,
    ];

    yield 'the context argument beats the steps section' => [
      ['sample' => ['enabled' => FALSE]],
      ['sample' => ['enabled' => TRUE]],
      [],
      TRUE,
    ];

    yield 'a tag beats the context argument' => [
      [],
      ['sample' => ['enabled' => TRUE]],
      ['behat-steps-skip:SampleTrait'],
      FALSE,
    ];

    yield 'the narrower tag settles the value' => [
      [],
      [],
      ['sample-off', 'sample-on'],
      TRUE,
    ];

    yield 'a wider tag applies on its own' => [
      [],
      [],
      ['sample-off'],
      FALSE,
    ];
  }

  public function testTheTagsAreReadOnEveryAccess(): void {
    $registry = new ScenarioTagRegistry();
    $resolver = new TraitOptionResolver(self::CONTEXT, self::declarations(), [], [], $registry, new TagOverrides());

    $this->assertTrue($resolver->bool('sample', 'enabled'));

    $registry->setTags(['sample-off']);

    $this->assertFalse($resolver->bool('sample', 'enabled'));
  }

  public function testAnOptionWithNoTagBindingIgnoresTags(): void {
    $this->assertSame('a default', $this->createResolver([], [], ['sample-off'])->string('sample', 'label'));
  }

  /**
   * Tests that the context argument names what it accepts when it is wrong.
   *
   * @param array<string, mixed> $config
   *   The context's config argument.
   * @param string $expected_message
   *   The message the construction is expected to throw with.
   */
  #[DataProvider('dataProviderStrictValidation')]
  public function testStrictValidation(array $config, string $expected_message): void {
    $this->expectException(InvalidConfigurationException::class);
    $this->expectExceptionMessage($expected_message);

    $this->createResolver($config);
  }

  public static function dataProviderStrictValidation(): \Iterator {
    yield 'unknown group' => [
      ['nonexistent' => ['enabled' => FALSE]],
      'Unknown option group "nonexistent" for context "' . self::CONTEXT . '". This context accepts: other_sample, sample, sample_extra.',
    ];

    yield 'unknown option' => [
      ['sample' => ['nonexistent' => FALSE]],
      'Unknown option "sample.nonexistent" for context "' . self::CONTEXT . '". The "sample" group accepts: enabled, label, limit, ratio, selectors, anything.',
    ];

    yield 'a group that is not a map' => [
      ['sample' => 'off'],
      'The "sample" option group holds a map of options, but a string was given.',
    ];

    yield 'a value of the wrong type' => [
      ['sample' => ['enabled' => 'yes']],
      'The "sample.enabled" option expects a boolean, but a string was given.',
    ];
  }

  public function testContextDeclaringNothingNamesWhatItAccepts(): void {
    $this->expectException(InvalidConfigurationException::class);
    $this->expectExceptionMessage('Unknown option group "sample" for context "' . self::CONTEXT . '". This context accepts: nothing.');

    new TraitOptionResolver(self::CONTEXT, [], [], ['sample' => ['enabled' => FALSE]], new ScenarioTagRegistry(), new TagOverrides());
  }

  /**
   * Tests that the steps section tolerates what a context cannot serve.
   *
   * @param array<string, mixed> $steps
   *   The extension's steps section.
   */
  #[DataProvider('dataProviderPermissiveSteps')]
  public function testPermissiveSteps(array $steps): void {
    $this->assertSame('a default', $this->createResolver([], $steps)->string('sample', 'label'));
  }

  public static function dataProviderPermissiveSteps(): \Iterator {
    yield 'an unknown group is ignored' => [['nonexistent' => ['enabled' => FALSE]]];
    yield 'an unknown option is ignored' => [['sample' => ['nonexistent' => FALSE]]];
    yield 'an unknown group holding a scalar is ignored' => [['nonexistent' => 'off']];
  }

  public function testMalformedGroupInTheStepsSectionIsRejected(): void {
    $this->expectException(InvalidConfigurationException::class);
    $this->expectExceptionMessage('The "sample" option group holds a map of options, but a string was given.');

    $this->createResolver([], ['sample' => 'off']);
  }

  /**
   * Tests that a read naming the wrong type names both.
   *
   * @param string $reader
   *   The typed reader to call.
   * @param string $key
   *   The option to read.
   * @param string $expected_message
   *   The message the read is expected to throw with.
   */
  #[DataProvider('dataProviderTypedReadNamesBothTypes')]
  public function testTypedReadNamesBothTypes(string $reader, string $key, string $expected_message): void {
    $resolver = $this->createResolver();

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage($expected_message);

    $resolver->{$reader}('sample', $key);
  }

  public static function dataProviderTypedReadNamesBothTypes(): \Iterator {
    yield 'a string read as a boolean' => ['bool', 'label', 'The "sample.label" option resolved to string, but it was read as a boolean.'];
    yield 'a string read as an integer' => ['int', 'label', 'The "sample.label" option resolved to string, but it was read as an integer.'];
    yield 'an integer read as a float' => ['float', 'limit', 'The "sample.limit" option resolved to int, but it was read as a float.'];
    yield 'a boolean read as a string' => ['string', 'enabled', 'The "sample.enabled" option resolved to bool, but it was read as a string.'];
    yield 'a boolean read as a map' => ['array', 'enabled', 'The "sample.enabled" option resolved to bool, but it was read as a map.'];
  }

  /**
   * Tests that a trait name resolves to the switchable group it owns.
   *
   * @param string $trait
   *   The short trait name a skip tag carries.
   * @param string|null $expected
   *   The group expected to be owned by the trait.
   */
  #[DataProvider('dataProviderGroupFor')]
  public function testGroupFor(string $trait, ?string $expected): void {
    $this->assertSame($expected, $this->createResolver()->groupFor($trait));
  }

  public static function dataProviderGroupFor(): \Iterator {
    yield 'a trait name maps to its group' => ['SampleTrait', 'sample'];
    yield 'a trait whose name extends another maps to its own group' => ['SampleExtraTrait', 'sample_extra'];
    yield 'a trait whose group has no enabled option owns nothing' => ['OtherSampleTrait', NULL];
    yield 'a trait with no group owns nothing' => ['NonexistentTrait', NULL];
    yield 'a hook name owns nothing' => ['sampleBeforeScenario', NULL];
    yield 'a name without the trait suffix owns nothing' => ['Sample', NULL];
  }

  /**
   * Builds a resolver over the sample declarations.
   *
   * @param array<string, mixed> $config
   *   The context's config argument.
   * @param array<string, mixed> $steps
   *   The extension's steps section.
   * @param list<string> $tags
   *   The tags the scenario and its feature carry.
   */
  protected function createResolver(array $config = [], array $steps = [], array $tags = []): TraitOptionResolver {
    $registry = new ScenarioTagRegistry();
    $registry->setTags($tags);

    return new TraitOptionResolver(self::CONTEXT, self::declarations(), $steps, $config, $registry, new TagOverrides());
  }

  /**
   * Declares one option of each shape the resolution has to read.
   *
   * @return array<string, array<string, \DrevOps\BehatSteps\Behat\Config\Option>>
   *   Options keyed by group name and then by option name.
   */
  protected static function declarations(): array {
    return [
      'other_sample' => [
        'selectors' => new Option('selectors', default: ['.one', '.two'], description: 'A map option.'),
      ],
      'sample' => [
        'enabled' => new Option('enabled', default: TRUE, description: 'Whether the sample hook runs.', tags: ['sample-off' => FALSE, 'sample-on' => TRUE]),
        'label' => new Option('label', default: 'a default', description: 'A string option.'),
        'limit' => new Option('limit', default: 7, description: 'An integer option.'),
        'ratio' => new Option('ratio', default: 0.5, description: 'A float option.'),
        'selectors' => new Option('selectors', default: ['.sample'], description: 'An array option.'),
        'anything' => new Option('anything', default: NULL, description: 'An option whose declaration names no type.'),
      ],
      'sample_extra' => [
        'enabled' => new Option('enabled', default: TRUE, description: 'Whether the sample extra hook runs.'),
      ],
    ];
  }

}
