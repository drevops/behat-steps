---
title: Scenario styles
sidebar_position: 6
description: The imperative and declarative scenario styles, the job each one does and how to move from the shipped steps to domain steps built on the same helpers.
---

# Scenario styles

You can write a Behat scenario in 2 styles. A generic step library like this one is built for the imperative style, and BDD's own teaching favors the declarative one. This page explains the job each style does and how Behat Steps supports both.

## The 2 styles

The imperative style spells out each interaction, and a generic step library makes it possible:

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

The declarative style names the behavior instead:

```gherkin
Scenario: Editor publishes a page
  Given I am an editor
  When I publish a page titled "About us"
  Then the page "About us" should be publicly visible at "/about-us"
```

No shipped step stands behind the declarative version. You write each definition yourself, as a few lines of PHP over the library's helpers. It goes in a trait of your own that your `FeatureContext` uses, as [Contexts](contexts.md#your-own-steps-in-traits-of-your-own) shows:

```php
#[When('I publish a page titled :title')]
public function publishingPublishPage(string $title): void {
  $this->entityLifecycleCreateNode(new EntityStub('node', 'page', [
    'title' => $title,
    'moderation_state' => 'published',
  ]));
}
```

That's what "domain steps" means: the Gherkin speaks your project's language. The translation into clicks, fields and API calls happens once, inside a definition, and every scenario reuses it.

## Why BDD teaches the declarative style

3 arguments back it, all of them old and well tested:

- Audience. BDD's founding purpose is to use scenarios as a conversation with stakeholders. To a product owner, "I fill in Title" is noise while "I publish a page" is the point.
- Resilience. When the form is redesigned, an imperative suite changes in every scenario that touches the form. A declarative suite changes in exactly 1 definition, because its scenarios assert the behavior and leave the path through the widgets to that definition.
- Intent. An imperative scenario verifies only the mechanics, so it can pass while the requirement behind them fails.

## Why the imperative style wins in this ecosystem anyway

The critique assumes a context that mostly doesn't hold for CMS delivery work:

- The UI is the deliverable. A Drupal site build is assembled configuration, such as content types, fields, forms, views and blocks. "The editor can fill this form and the page appears" is the requirement itself, so testing the site in its own terms is legitimate verification.
- There's usually no stakeholder audience. Developers and QA write and read these suites, so the readability-for-business argument loses most of its force.
- The economics change at fleet scale. Each project has to write its own domain vocabulary in PHP, while a generic vocabulary takes 0 lines of PHP and gives you coverage straight away. Across dozens of sites, 1 shared vocabulary that every developer already knows beats dozens of bespoke mini-languages.

## 2 different jobs

Specification and verification are 2 different jobs, and both are worth doing. Specification, BDD proper, uses domain language, faces stakeholders and keeps its scenarios few and precious. Verification, functional regression testing, uses infrastructure language, faces developers and wants its scenarios broad and cheap.

Behat supports both, and generic steps alone serve only the second. So decide which job each of your suites does before you pick its style.

## Steps and the helpers behind them

The library serves both jobs through 1 design rule: every step body is a thin wrapper over named helpers. So the package gives you 2 things: the steps and the helpers they're built on. When your project outgrows the shipped steps, you don't leave the library. You stop calling the helpers from Gherkin and start calling them from PHP.

3 consequences follow:

- Helpers are public API. They're listed in [HELPERS.md](../../../HELPERS.md), covered by semantic versioning and named as carefully as the steps. Traits help here too: the helpers sit on `$this` in your `FeatureContext` and in every trait it uses. So a domain step costs a few lines, where a design built on separate contexts would start every domain step with service lookups.
- Suites split by job. A spec suite holds the few domain-language scenarios, and a regression suite holds the broad ones written in the shipped steps. [Configuration](configuration.md#1-suites-and-context-arguments) gives the layout.
- The docs teach a path. Start with the shipped steps for coverage on day one, then move the flows that matter to domain steps built on the helpers. The [quick start](../quick-start.md) starts you on the shipped steps and ends with a step of your own.
