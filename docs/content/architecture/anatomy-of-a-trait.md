---
title: Anatomy of a trait
sidebar_position: 6
description: A made-up trait with options, hooks, steps and helpers, commented line by line, for writing a trait in your own project or contributing one to Behat Steps.
---

# Anatomy of a trait

Every step in Behat Steps lives in a trait, and so can yours. This page takes 1 made-up trait, `ConsentBannerTrait`, which deals with a site's cookie consent banner, and walks through it from top to bottom. It's written the way the library's own traits are, so it shows what to copy whether you're writing a trait for your project or contributing a trait to the library.

The trait has every part a trait can have: a property, 2 hooks, a `Given`, a `When` and 2 `Then` steps, public helpers, protected methods and 4 options.

## The whole trait

Here's the whole trait, ready to save in your project:

```php
<?php

declare(strict_types=1);

use Behat\Behat\Hook\Scope\AfterStepScope;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Hook\AfterStep;
use Behat\Hook\BeforeScenario;
use Behat\Mink\Element\NodeElement;
use Behat\Mink\Exception\ElementNotFoundException;
use Behat\Mink\Exception\ExpectationException;
use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use DrevOps\BehatSteps\Extension\Config\Option;

/**
 * Accept the cookie consent banner, and check whether the page shows it.
 *
 * @title Consent banner
 *
 * @phpstan-require-extends \DrevOps\BehatSteps\WebRawContext
 */
trait ConsentBannerTrait {

  // 🧠 PROPERTIES come first. This one remembers, for the running scenario,
  // whether to accept the banner after every step.
  protected bool $consentBannerAutoAccept = FALSE;

  // ⏱️ HOOKS come next. Behat runs them by itself, around every scenario
  // and every step, without a step asking for them.

  /**
   * Decide whether this scenario accepts the banner by itself.
   */
  #[BeforeScenario]
  public function consentBannerBeforeScenario(BeforeScenarioScope $scope): void {
    // 🔌 skipTag() says "skip" when the 'enabled' option is off, or the
    // scenario is tagged @behat-steps-skip:ConsentBannerTrait.
    $this->consentBannerAutoAccept = !$this->skipTag(__TRAIT__, $scope) && $this->consentBannerGetAutoAccept();
  }

  /**
   * Accept the banner after every step, when the scenario asked for it.
   */
  #[AfterStep]
  public function consentBannerAfterStep(AfterStepScope $scope): void {
    // 🚪 No page open yet? Then there's no banner to accept.
    if (!$this->consentBannerAutoAccept || !$this->getSession()->isStarted()) {
      return;
    }

    $this->consentBannerAcceptIfShown();
  }

  // 🟢 GIVEN steps put the site into the state a scenario needs.

  /**
   * Make sure the consent banner is out of the way.
   *
   * @code
   * Given the consent banner has been accepted
   * @endcode
   *
   * @actor:system
   */
  #[Given('the consent banner has been accepted')]
  public function consentBannerEnsureAccepted(): void {
    // 👍 Accepted already? Fine: a Given only makes sure.
    $this->consentBannerAcceptIfShown();
  }

  // 🔵 WHEN steps do the thing the scenario tests.

  /**
   * Accept the consent banner on the current page.
   *
   * @code
   * When I accept the consent banner
   * @endcode
   *
   * @actor:person
   */
  #[When('I accept the consent banner')]
  public function consentBannerAccept(): void {
    // 💥 Unlike the Given, this fails when there's no banner to accept.
    $this->consentBannerPressAccept($this->consentBannerGet());
  }

  // 🟣 THEN steps check the result. Their method names carry "Assert".

  /**
   * Assert that the page shows the consent banner.
   *
   * @code
   * Then the consent banner should be on the page
   * @endcode
   *
   * @actor:system
   */
  #[Then('the consent banner should be on the page')]
  public function consentBannerAssertExists(): void {
    if (!$this->consentBannerFind() instanceof NodeElement) {
      throw new ExpectationException('The consent banner is not on the page, but it should be.', $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that the page doesn't show the consent banner.
   *
   * @code
   * Then the consent banner should not be on the page
   * @endcode
   *
   * @actor:system
   */
  #[Then('the consent banner should not be on the page')]
  public function consentBannerAssertNotExists(): void {
    if ($this->consentBannerFind() instanceof NodeElement) {
      throw new ExpectationException('The consent banner is on the page, but it should not be.', $this->getSession()->getDriver());
    }
  }

  // 🧰 PUBLIC HELPERS come last. Your own steps can call them, and they
  // won't change in a way that breaks you before a major release.

  /**
   * Find the consent banner on the page.
   *
   * @return \Behat\Mink\Element\NodeElement|null
   *   The banner, or NULL when the page doesn't show it.
   */
  public function consentBannerFind(): ?NodeElement {
    // 🔍 "Find" returns NULL when nothing matches...
    return $this->getSession()->getPage()->find('css', $this->consentBannerGetSelector());
  }

  /**
   * Get the consent banner, or fail when the page doesn't show it.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   *   When the page shows no consent banner.
   */
  public function consentBannerGet(): NodeElement {
    // 🎯 ...and "Get" fails instead, so the name tells you which to expect.
    $banner = $this->consentBannerFind();

    if (!$banner instanceof NodeElement) {
      throw new ElementNotFoundException($this->getSession()->getDriver(), 'consent banner', 'css', $this->consentBannerGetSelector());
    }

    return $banner;
  }

  /**
   * Accept the consent banner when the page shows it.
   */
  public function consentBannerAcceptIfShown(): void {
    $banner = $this->consentBannerFind();

    if ($banner instanceof NodeElement) {
      $this->consentBannerPressAccept($banner);
    }
  }

  /**
   * Press the banner's accept button, or follow its accept link.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   *   When the banner holds no button or link with the accept text.
   */
  public function consentBannerPressAccept(NodeElement $banner): void {
    $control = $this->consentBannerFindAcceptControl($banner);

    if (!$control instanceof NodeElement) {
      throw new ElementNotFoundException($this->getSession()->getDriver(), 'accept button or link', 'text', $this->consentBannerGetAcceptText());
    }

    $control->click();
  }

  // ⚙️ Each option is read in 1 public "Get" method, so a project that needs
  // a value no option gives can override just that method.

  /**
   * Return the CSS selector of the consent banner.
   */
  public function consentBannerGetSelector(): string {
    return $this->getOptionString('consent_banner', 'selector');
  }

  /**
   * Return the text of the button or link that accepts the banner.
   */
  public function consentBannerGetAcceptText(): string {
    return $this->getOptionString('consent_banner', 'accept_text');
  }

  /**
   * Return whether the scenario accepts the banner after every step.
   */
  public function consentBannerGetAutoAccept(): bool {
    return $this->getOptionBool('consent_banner', 'auto_accept');
  }

  // 🔒 PROTECTED methods are the trait's own business. Any release may
  // change them, so steps of yours never call them.

  /**
   * Find the button or link inside the banner that accepts it.
   */
  protected function consentBannerFindAcceptControl(NodeElement $banner): ?NodeElement {
    return $banner->findButton($this->consentBannerGetAcceptText()) ?? $banner->findLink($this->consentBannerGetAcceptText());
  }

  /**
   * Declare the options this trait reads.
   *
   * @return array<int, \DrevOps\BehatSteps\Extension\Config\Option>
   *   The options this trait declares.
   */
  protected function consentBannerConfigSchema(): array {
    // 🎛️ The method's prefix names the group: "consent_banner". Each default
    // also sets the option's type: TRUE makes 'enabled' a boolean.
    return [
      new Option('enabled', default: TRUE, description: 'Run the hooks that accept the banner after every step.'),
      new Option('selector', default: '.consent-banner', description: 'CSS selector of the consent banner.'),
      new Option('accept_text', default: 'Accept', description: 'Text of the button or link that accepts the banner.'),
      // 🏷️ A scenario tagged @consent-accepted sets this 1 to TRUE.
      new Option('auto_accept', default: FALSE, description: 'Accept the banner after every step of the scenario.', tags: ['consent-accepted' => TRUE]),
    ];
  }

}
```

## The trait, part by part

### The docblock

The summary says what the trait is for. `@title` names the trait's page on this site, and `@phpstan-require-extends` names the class the trait needs: `WebRawContext`, which provides `skipTag()`, `getOptionString()` and everything else the trait calls without declaring. Your editor and PHPStan read that line to find where those methods come from.

### Properties

A trait keeps its own state in properties, and every name starts with the trait's name, here `consentBanner`, so 2 traits mixed into 1 context never collide. Behat builds a fresh context for every scenario, so a property starts over with each scenario too.

### Hooks

A hook is a method Behat runs by itself, at a point in the run its attribute names: `#[BeforeSuite]`, `#[AfterSuite]`, `#[BeforeFeature]`, `#[AfterFeature]`, `#[BeforeScenario]`, `#[AfterScenario]`, `#[BeforeStep]` or `#[AfterStep]`. Its name is the trait's prefix and the event, such as `consentBannerAfterStep()`.

A step hook has no access to the scenario's tags, so the `BeforeScenario` hook makes the decision once and stores it in the property, and the `AfterStep` hook reads it. That decision starts with `skipTag(__TRAIT__, $scope)`, the same check the shipped traits' hooks run, so you can switch the trait off with its `enabled` option for a whole profile, or with `@behat-steps-skip:ConsentBannerTrait` for 1 scenario.

### Steps

Each step is a method with a `#[Given]`, `#[When]` or `#[Then]` attribute holding its text, and it stays thin: it calls a helper or 2 and leaves the real work to them. That's what lets your own steps reach everything a shipped step can.

- The `Given` makes sure of a state, so it passes when the banner is already gone.
- The `When` does the thing the scenario tests, so it fails when there's no banner to accept.
- Each `Then` checks something and throws Mink's `ExpectationException` when the check fails, with a message that says what it found and closes with "but it should be" or "but it should not be".

The docblock of each step carries an example between `@code` and `@endcode`, and `@actor:person` or `@actor:system` says whether the step is something a person does. The library's docs generator reads both, so a shipped trait's page shows every example.

### Helpers

The public methods after the steps are the trait's helpers. The verb says how each behaves: `consentBannerFind()` returns `NULL` when the page has no banner, and `consentBannerGet()` throws instead. The `Get` methods at the end read the options, 1 method per option, so a project can override 1 of them in its own context when no option reaches far enough.

The protected methods at the bottom are the trait's own business. They aren't part of the public API, so a release can rename or remove them, and your own steps should never call them.

### Options

`consentBannerConfigSchema()` declares the trait's options, and the method's name is the whole registration: the prefix `consentBanner` becomes the group `consent_banner`. Each `Option` has a name, a default that also sets its type and a description, and it can name tags that set it for 1 scenario, as `auto_accept` does with `@consent-accepted`. [How a trait gets options without a constructor](extension-layer.md#how-a-trait-gets-options-without-a-constructor) explains how the context finds and resolves them.

## Use it in your project

Save it as `tests/behat/bootstrap/ConsentBannerTrait.php`, beside your `FeatureContext`, and have `FeatureContext` use it, as [Contexts](../guides/contexts.md#your-own-steps-in-traits-of-your-own) shows. If your banner doesn't use the defaults, set its options under `steps` in `behat.php`:

```php
->withExtension(new Extension(BehatStepsExtension::class, [
  'steps' => [
    'consent_banner' => ['selector' => '#cookie-notice', 'accept_text' => 'Got it'],
  ],
]))
```

Then use its steps in a feature file:

```gherkin
Feature: Consent banner

  Scenario: Visitor accepts the consent banner
    When I visit "/"
    Then the consent banner should be on the page
    When I accept the consent banner
    Then the consent banner should not be on the page

  @consent-accepted
  Scenario: The banner stays out of the way
    When I visit "/"
    Then the consent banner should not be on the page
```

In the second scenario, the `@consent-accepted` tag turns `auto_accept` on, so the `AfterStep` hook accepts the banner right after the visit.

## Contribute a trait like it

A trait you contribute to Behat Steps follows the same shape, with a few more obligations:

- It lives in `src/Steps/Web` or `src/Steps/Drupal`, in the namespace that matches, and `WebContext` or `DrupalContext` composes it.
- Its step text follows [the steps format](../../../CONTRIBUTING.md#steps-format), and its method names follow [the naming conventions](../../../CONTRIBUTING.md#method-naming-conventions). Tests enforce both.
- Every step has a scenario in the trait's feature file under `tests/behat/features`, including 1 that switches the trait off with its skip tag.
- `ahoy update-docs` turns its docblocks into the trait's page and its entries in `STEPS.md` and `HELPERS.md`.

[Contributing code](../contributing/code.md) walks through the whole change, from setting up the fixture site to opening the pull request.
