# 2 styles, 2 jobs - the vocabulary and the toolbox

Why a generic step library and BDD's own teaching point in different directions, and how this stack serves both.

## The 2 styles

The same behavior can be scripted 2 ways. The first is the imperative style - the style a generic step vocabulary makes possible:

```gherkin
Scenario: Editor publishes a page
  Given I am logged in as a user with the "editor" role
  When I visit "/node/add/page"
  And I fill in "Title" with "About us"
  And I select "Published" from "Save as"
  And I press "Save"
  Then the path should be "/about-us"
  And the element ".messages--status" should contain "has been created"
```

The second is the declarative style - the style Behat's own quick start teaches ("When I add the 'Sith Lord Lightsaber' to the basket"):

```gherkin
Scenario: Editor publishes a page
  Given I am an editor
  When I publish a page titled "About us"
  Then the page "About us" should be publicly visible at "/about-us"
```

The declarative version has no shipped step behind it. The project writes each definition itself, as a few lines of PHP over the library's helpers:

```php
#[When('I publish a page titled :title')]
public function publishPage(string $title): void {
  $this->contentCreate('page', ['title' => $title, 'moderation_state' => 'published']);
}
```

That is what "domain steps" means: the Gherkin speaks the project's language, and the translation into clicks, fields and API calls happens once, inside a definition, instead of being spelled out in every scenario.

## Why BDD teaches the declarative style

3 arguments, all old and all well-tested. Audience: BDD's founding purpose is scenarios as a conversation artifact with stakeholders - "I fill in Title" is noise to a product owner, "I publish a page" is signal. Resilience: when the form is redesigned, an imperative suite changes in every scenario that touches the form, while a declarative suite changes in exactly 1 definition, because the scenario asserted the behavior, not the widget path. Intent: an imperative scenario can pass while the actual requirement fails, because it verifies mechanics, not meaning.

There is a famous precedent. Cucumber - Behat's Ruby ancestor - used to ship `web_steps.rb`, a generic click/fill/see vocabulary. In 2011 its maintainers deleted it from the project, arguing that generic steps train users into brittle, implementation-coupled suites. MinkContext is PHP's `web_steps.rb` that never got deleted, and this library is a larger, better-engineered descendant of the same pattern. The critique is real and has history behind it.

## Why the imperative style wins in this ecosystem anyway

The critique assumes a context that mostly does not hold for CMS delivery work:

- **The UI is the deliverable.** A Drupal site build is assembled configuration - content types, fields, forms, views, blocks. "The editor can fill this form and the page appears" is not incidental mechanics; it is the requirement. Testing the artifact in the artifact's own terms is legitimate verification.
- **There is usually no stakeholder audience.** These suites are written and read by developers and QA, so the readability-for-business argument loses most of its force.
- **Economics at fleet scale.** A domain vocabulary must be written per project, in PHP. A generic vocabulary is 0 PHP and instant coverage - and across dozens of sites, 1 shared vocabulary every developer already knows beats dozens of bespoke mini-DSLs. Cross-project consistency is itself a feature.

## 2 different jobs

These are 2 jobs, not 1 job done well or badly. Specification - BDD proper - uses domain language, faces stakeholders, and keeps its scenarios few and precious. Verification - functional regression - uses infrastructure language, faces developers, and wants its scenarios broad and cheap. Behat the tool supports both; Behat's tutorial teaches only the first; a generic step library equips only the second. Nothing is wrong except not knowing which job a given suite is doing.

## The vocabulary and the toolbox

The library serves both jobs at once through 1 design rule: every step body is a thin wrapper over a named protected helper. That makes the package 2 products in 1 - the vocabulary (the steps: 1 skin over the helpers) and the toolbox (the helpers themselves). A project that outgrows the raw vocabulary does not leave the library; it stops calling the toolbox from Gherkin and starts calling it from PHP.

3 consequences follow:

1. **Helpers are public API** - documented, semver-covered, named as carefully as the steps. The helper surface is half the product. This is also where the trait model earns its keep: the helpers sit on `$this` in the consumer's `FeatureContext`, so a domain step costs 3 lines; under a context model every domain step would start with service lookups.
2. **Suites split by job**: a spec suite holding the few domain-language scenarios, a regression suite holding the broad generic-vocabulary ones - which is also how Behat's own documentation says suites should be used.
3. **The docs teach a lifecycle, not a catalogue**: start with the vocabulary for instant coverage, then graduate the flows that matter to domain steps on the toolbox. The quick start shows both scenario styles side by side.
