<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Behat\Listener;

use Behat\Behat\EventDispatcher\Event\BeforeScenarioTested;
use Behat\Gherkin\Node\FeatureNode;
use Behat\Gherkin\Node\ScenarioNode;
use Behat\Mink\Mink;
use Behat\Testwork\Environment\Environment;
use Behat\Testwork\ServiceContainer\Exception\ProcessingException;
use Behat\Testwork\Suite\Exception\SuiteConfigurationException;
use Behat\Testwork\Suite\Suite;
use DrevOps\BehatSteps\Behat\Listener\MinkSessionListener;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Tests how a scenario's tags select the Mink session it runs against.
 */
#[CoversClass(MinkSessionListener::class)]
class MinkSessionListenerTest extends TestCase {

  /**
   * Tests which session a set of feature and scenario tags selects.
   *
   * @param list<string> $feature_tags
   *   Tags declared on the feature.
   * @param list<string> $scenario_tags
   *   Tags declared on the scenario.
   * @param string $expected
   *   The session name expected to be selected.
   */
  #[DataProvider('dataProviderSessionSelection')]
  public function testSessionSelection(array $feature_tags, array $scenario_tags, string $expected): void {
    $mink = $this->createMock(Mink::class);
    $mink->expects($this->once())->method('setDefaultSessionName')->with($expected);

    $listener = new MinkSessionListener($mink, 'browserkit', 'selenium2', ['selenium2', 'chrome']);
    $listener->prepareDefaultMinkSession($this->createEvent($feature_tags, $scenario_tags));
  }

  public static function dataProviderSessionSelection(): \Iterator {
    yield 'no tags falls back to the default session' => [[], [], 'browserkit'];
    yield 'a legacy javascript tag selects the javascript session' => [[], ['javascript'], 'selenium2'];
    yield 'a gherkin-32 javascript tag selects the javascript session' => [[], ['@javascript'], 'selenium2'];
    yield 'a feature tag selects the javascript session' => [['@javascript'], [], 'selenium2'];
    yield 'a legacy mink tag names the session' => [[], ['mink:chrome'], 'chrome'];
    yield 'a gherkin-32 mink tag names the session' => [[], ['@mink:chrome'], 'chrome'];
    yield 'a tag that selects nothing is ignored' => [[], ['@wip'], 'browserkit'];
    yield 'the last selecting tag wins' => [['@javascript'], ['@mink:chrome'], 'chrome'];
  }

  /**
   * Tests which reset a scenario's tags ask of the started sessions.
   *
   * @param list<string> $scenario_tags
   *   Tags declared on the scenario.
   * @param string $expected
   *   The Mink method expected to be called.
   */
  #[DataProvider('dataProviderSessionReset')]
  public function testSessionReset(array $scenario_tags, string $expected): void {
    $mink = $this->createMock(Mink::class);
    $mink->expects($this->once())->method($expected);

    $listener = new MinkSessionListener($mink, 'browserkit', 'selenium2', ['selenium2']);
    $listener->prepareDefaultMinkSession($this->createEvent([], $scenario_tags));
  }

  public static function dataProviderSessionReset(): \Iterator {
    yield 'an untagged scenario resets the sessions' => [[], 'resetSessions'];
    yield 'a legacy insulated tag stops the sessions' => [['insulated'], 'stopSessions'];
    yield 'a gherkin-32 insulated tag stops the sessions' => [['@insulated'], 'stopSessions'];
  }

  public function testJavascriptTagWithoutJavascriptSessionIsReported(): void {
    $listener = new MinkSessionListener($this->createMock(Mink::class), 'browserkit', NULL, []);

    $this->expectException(ProcessingException::class);
    $this->expectExceptionMessage('The @javascript tag cannot be used without enabling a javascript session');

    $listener->prepareDefaultMinkSession($this->createEvent([], ['@javascript']));
  }

  public function testTheSuiteOverridesTheDefaultSession(): void {
    $mink = $this->createMock(Mink::class);
    $mink->expects($this->once())->method('setDefaultSessionName')->with('goutte');

    $listener = new MinkSessionListener($mink, 'browserkit', 'selenium2', ['selenium2']);
    $listener->prepareDefaultMinkSession($this->createEvent([], [], ['mink_session' => 'goutte']));
  }

  public function testTheSuiteOverridesTheJavascriptSession(): void {
    $mink = $this->createMock(Mink::class);
    $mink->expects($this->once())->method('setDefaultSessionName')->with('chrome');

    $listener = new MinkSessionListener($mink, 'browserkit', 'selenium2', ['selenium2', 'chrome']);
    $listener->prepareDefaultMinkSession($this->createEvent([], ['@javascript'], ['mink_javascript_session' => 'chrome']));
  }

  /**
   * Tests that a malformed suite session setting is rejected.
   *
   * @param array<string, mixed> $settings
   *   Settings declared on the suite.
   * @param list<string> $scenario_tags
   *   Tags declared on the scenario.
   * @param string $expected_message
   *   The exception message expected.
   */
  #[DataProvider('dataProviderInvalidSuiteSettingsAreReported')]
  public function testInvalidSuiteSettingsAreReported(array $settings, array $scenario_tags, string $expected_message): void {
    $listener = new MinkSessionListener($this->createMock(Mink::class), 'browserkit', 'selenium2', ['selenium2']);

    $this->expectException(SuiteConfigurationException::class);
    $this->expectExceptionMessage($expected_message);

    $listener->prepareDefaultMinkSession($this->createEvent([], $scenario_tags, $settings));
  }

  public static function dataProviderInvalidSuiteSettingsAreReported(): \Iterator {
    yield 'a non-string default session' => [
      ['mink_session' => ['goutte']],
      [],
      '`mink_session` setting of the "default" suite is expected to be a string, array given.',
    ];
    yield 'a non-string javascript session' => [
      ['mink_javascript_session' => 1],
      ['@javascript'],
      '`mink_javascript_session` setting of the "default" suite is expected to be a string, integer given.',
    ];
    yield 'a javascript session that drives no browser' => [
      ['mink_javascript_session' => 'browserkit'],
      ['@javascript'],
      '`mink_javascript_session` setting of the "default" suite is not a javascript session. browserkit given but expected one of selenium2.',
    ];
  }

  /**
   * Builds the event Behat dispatches before a scenario or an example.
   *
   * @param list<string> $feature_tags
   *   Tags declared on the feature.
   * @param list<string> $scenario_tags
   *   Tags declared on the scenario.
   * @param array<string, mixed> $settings
   *   Settings declared on the suite the scenario belongs to.
   */
  protected function createEvent(array $feature_tags, array $scenario_tags, array $settings = []): BeforeScenarioTested {
    $scenario = new ScenarioNode('Scenario', $scenario_tags, [], 'Scenario', 2);
    $feature = new FeatureNode('Feature', NULL, $feature_tags, NULL, [$scenario], 'Feature', 'en', NULL, 1);

    $environment = $this->createMock(Environment::class);
    $environment->method('getSuite')->willReturn($this->createSuite($settings));

    return new BeforeScenarioTested($environment, $feature, $scenario);
  }

  /**
   * Builds the suite the scenario belongs to.
   *
   * @param array<string, mixed> $settings
   *   Settings declared on the suite.
   *
   * @return \Behat\Testwork\Suite\Suite&\PHPUnit\Framework\MockObject\MockObject
   *   The suite.
   */
  protected function createSuite(array $settings): Suite&MockObject {
    $suite = $this->createMock(Suite::class);
    $suite->method('getName')->willReturn('default');
    $suite->method('hasSetting')->willReturnCallback(static fn(string $key): bool => array_key_exists($key, $settings));
    $suite->method('getSetting')->willReturnCallback(static fn(string $key): mixed => $settings[$key] ?? NULL);

    return $suite;
  }

}
