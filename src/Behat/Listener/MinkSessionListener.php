<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\Listener;

use Behat\Behat\EventDispatcher\Event\BeforeScenarioTested;
use Behat\Behat\EventDispatcher\Event\ExampleTested;
use Behat\Behat\EventDispatcher\Event\ScenarioTested;
use Behat\Mink\Mink;
use Behat\Testwork\EventDispatcher\Event\ExerciseCompleted;
use Behat\Testwork\ServiceContainer\Exception\ProcessingException;
use Behat\Testwork\Suite\Exception\SuiteConfigurationException;
use Behat\Testwork\Suite\Suite;
use DrevOps\BehatSteps\Behat\Tag;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Selects the Mink session each scenario or example runs against.
 *
 * Replaces Mink's own listener, which compares '@javascript' and
 * '@mink:<name>' against bare tag names that the 'gherkin-32' parsing mode
 * does not produce, so a scenario would fall back to the default session
 * instead of the one its tag names. Reading the tags through 'Tag' is the only
 * behavioural difference.
 *
 * @see \DrevOps\BehatSteps\Behat\Tag
 * @see \DrevOps\BehatSteps\Behat\Mink\ServiceContainer\MinkExtension
 */
class MinkSessionListener implements EventSubscriberInterface {

  /**
   * Constructs a MinkSessionListener.
   *
   * @param \Behat\Mink\Mink $mink
   *   The Mink instance holding the registered sessions.
   * @param string $defaultSession
   *   Name of the session a scenario runs against without a selecting tag.
   * @param string|null $javascriptSession
   *   Name of the session '@javascript' selects, or NULL when none is enabled.
   * @param array<int, string> $availableJavascriptSessions
   *   Names of the sessions that drive a real browser.
   */
  public function __construct(
    protected readonly Mink $mink,
    protected readonly string $defaultSession,
    protected readonly ?string $javascriptSession,
    protected readonly array $availableJavascriptSessions = [],
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      ScenarioTested::BEFORE => ['prepareDefaultMinkSession', 10],
      ExampleTested::BEFORE => ['prepareDefaultMinkSession', 10],
      ExerciseCompleted::AFTER => ['tearDownMinkSessions', -10],
    ];
  }

  /**
   * Sets the default session for the scenario or example about to run.
   *
   * '@javascript' selects the configured javascript session and
   * '@mink:<name>' selects the session called '<name>'. '@insulated' stops the
   * started sessions instead of resetting them.
   *
   * @throws \Behat\Testwork\ServiceContainer\Exception\ProcessingException
   *   When '@javascript' is used and no javascript session is enabled.
   */
  public function prepareDefaultMinkSession(BeforeScenarioTested $event): void {
    $session = NULL;
    $tags = Tag::all($event);

    foreach ($tags as $tag) {
      if ($tag === 'javascript') {
        $session = $this->getJavascriptSession($event->getSuite());
      }
      elseif (preg_match('/^mink:(.+)/', $tag, $matches)) {
        $session = $matches[1];
      }
    }

    $session ??= $this->getDefaultSession($event->getSuite());

    if (in_array('insulated', $tags, TRUE)) {
      $this->mink->stopSessions();
    }
    else {
      $this->mink->resetSessions();
    }

    $this->mink->setDefaultSessionName($session);
  }

  /**
   * Stops every started session.
   */
  public function tearDownMinkSessions(): void {
    $this->mink->stopSessions();
  }

  /**
   * Resolves the session a scenario runs against without a selecting tag.
   *
   * @param \Behat\Testwork\Suite\Suite $suite
   *   The suite the scenario belongs to.
   *
   * @return string
   *   The session name.
   *
   * @throws \Behat\Testwork\Suite\Exception\SuiteConfigurationException
   *   When the suite's 'mink_session' setting is not a string.
   */
  protected function getDefaultSession(Suite $suite): string {
    if (!$suite->hasSetting('mink_session')) {
      return $this->defaultSession;
    }

    $session = $suite->getSetting('mink_session');

    if (!is_string($session)) {
      throw new SuiteConfigurationException(sprintf('`mink_session` setting of the "%s" suite is expected to be a string, %s given.', $suite->getName(), gettype($session)), $suite->getName());
    }

    return $session;
  }

  /**
   * Resolves the session the '@javascript' tag selects.
   *
   * @param \Behat\Testwork\Suite\Suite $suite
   *   The suite the scenario belongs to.
   *
   * @return string
   *   The session name.
   *
   * @throws \Behat\Testwork\ServiceContainer\Exception\ProcessingException
   *   When no javascript session is enabled.
   * @throws \Behat\Testwork\Suite\Exception\SuiteConfigurationException
   *   When the suite's 'mink_javascript_session' setting is not a string or
   *   does not name a javascript session.
   */
  protected function getJavascriptSession(Suite $suite): string {
    if (!$suite->hasSetting('mink_javascript_session')) {
      if ($this->javascriptSession === NULL) {
        throw new ProcessingException('The @javascript tag cannot be used without enabling a javascript session');
      }

      return $this->javascriptSession;
    }

    $session = $suite->getSetting('mink_javascript_session');

    if (!is_string($session)) {
      throw new SuiteConfigurationException(sprintf('`mink_javascript_session` setting of the "%s" suite is expected to be a string, %s given.', $suite->getName(), gettype($session)), $suite->getName());
    }

    if (!in_array($session, $this->availableJavascriptSessions, TRUE)) {
      throw new SuiteConfigurationException(sprintf('`mink_javascript_session` setting of the "%s" suite is not a javascript session. %s given but expected one of %s.', $suite->getName(), $session, implode(', ', $this->availableJavascriptSessions)), $suite->getName());
    }

    return $session;
  }

}
