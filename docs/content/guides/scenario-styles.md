---
title: Scenario styles
sidebar_position: 3
description: The imperative and declarative scenario styles, the job each one does, and how to graduate from the shipped steps to domain steps built on the same helpers.
---

<!-- cspell:ignore Lightsaber Sith -->

# Scenario styles - the vocabulary and the toolbox

A generic step library and BDD's own teaching point in different directions, and this stack serves both.

## The 2 styles

You can script the same behavior 2 ways. The first is the imperative style, which a generic step vocabulary makes possible:

```gherkin
Scenario: Editor publishes a page
  Given I am logged in as a user with the role "editor"
  When I visit "/node/add/page"
  And I fill in "Title" with "About us"
  And I select "Published" from "Save as"
  And I press "Save"
  Then the path should be "/about-us"
  And I should see the success message "has been created"
```

3 of those lines, `I fill in`, `I select` and `I press`, are Mink's own steps from `MinkContext`, which a suite registers beside this library's context.

The second is the declarative style, which Behat's own quick start teaches ("When I add the 'Sith Lord Lightsaber' to the basket"):

```gherkin
Scenario: Editor publishes a page
  Given I am an editor
  When I publish a page titled "About us"
  Then the page "About us" should be publicly visible at "/about-us"
```

The declarative version has no shipped step behind it. You write each definition yourself, as a few lines of PHP over the library's helpers:

```php
#[When('I publish a page titled :title')]
public function publishPage(string $title): void {
  $this->entityLifecycleCreateNode(new EntityStub('node', 'page', [
    'title' => $title,
    'moderation_state' => 'published',
  ]));
}
```

That's what "domain steps" means: the Gherkin speaks the project's language, and the translation into clicks, fields and API calls happens once, inside a definition, instead of being spelled out in every scenario.

## Why BDD teaches the declarative style

The case rests on 3 arguments, all of them old and well tested. The first is audience: BDD's founding purpose is to use scenarios as a conversation artifact with stakeholders, and to a product owner, "I fill in Title" is noise and "I publish a page" is signal. The second is resilience: when the form is redesigned, an imperative suite changes in every scenario that touches the form. A declarative suite changes in exactly 1 definition, because its scenarios assert the behavior and leave the widget path to that definition. The third is intent: an imperative scenario verifies only the mechanics, so it can pass while the requirement behind them fails.

The critique of the imperative style is real, and it has a well-known precedent. `cucumber-rails`, the Rails integration for Cucumber (Behat's Ruby ancestor), used to ship `web_steps.rb`, a generic click/fill/see vocabulary. In 2011 its maintainers deleted it from the project, arguing that generic steps train users into brittle, implementation-coupled suites. MinkContext is PHP's `web_steps.rb` that never got deleted, and this library is a larger, better-engineered descendant of the same pattern.

## Why the imperative style wins in this ecosystem anyway

The critique assumes a context that mostly doesn't hold for CMS delivery work:

- **The UI is the deliverable.** A Drupal site build is assembled configuration, such as content types, fields, forms, views and blocks. "The editor can fill this form and the page appears" isn't incidental mechanics here; it's the requirement, and testing the artifact in its own terms is legitimate verification.
- **There's usually no stakeholder audience.** Developers and QA write and read these suites, so the readability-for-business argument loses most of its force.
- **Economics at fleet scale.** Each project has to write its own domain vocabulary, in PHP, while a generic vocabulary takes 0 lines of PHP and gives instant coverage. Across dozens of sites, 1 shared vocabulary that every developer already knows beats dozens of bespoke mini-DSLs, and that cross-project consistency is a feature in itself.

## 2 different jobs

Specification and verification are separate jobs, and neither is a worse version of the other. Specification (BDD proper) uses domain language, faces stakeholders, and keeps its scenarios few and precious. Verification (functional regression) uses infrastructure language, faces developers, and wants its scenarios broad and cheap. Behat the tool supports both. Its tutorial teaches only the first, and a generic step library equips only the second. The only mistake is not knowing which job a given suite is doing.

## The vocabulary and the toolbox

The library serves both jobs at once through 1 design rule: every step body is a thin wrapper over named helpers. That makes the package 2 products in 1: the vocabulary (the steps, 1 skin over the helpers) and the toolbox (the helpers themselves). A project that outgrows the raw vocabulary stays with the library. It stops calling the toolbox from Gherkin and starts calling it from PHP.

3 consequences follow:

1. **Helpers are public API.** They're documented in [Web helpers](../web-helpers/README.md) and [Drupal helpers](../drupal-helpers/README.md), covered by semantic versioning and named as carefully as the steps. The helper surface is half the product. This is also where the trait model earns its keep: the helpers sit on `$this` in your `FeatureContext`, so a domain step costs 3 lines; under a context model, every domain step would start with service lookups.
2. **Suites split by job.** A spec suite holds the few domain-language scenarios, and a regression suite holds the broad generic-vocabulary ones. Behat's own documentation presents suites for this kind of split: each one tests a different kind of feature with a different kind of context, in 1 run. [Configuration](configuration.md#1-suites-and-context-arguments) gives the layout.
3. **The docs teach a lifecycle.** Start with the vocabulary for instant coverage, then graduate the flows that matter to domain steps on the toolbox. The [quick start](../quick-start.md) shows both scenario styles side by side.
