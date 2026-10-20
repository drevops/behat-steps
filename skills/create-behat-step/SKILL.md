---
name: create-behat-step
description: Create a Behat step of a project's own on the public helpers of drevops/behat-steps 4.x, as a method in a trait of the project's that its FeatureContext uses. Takes a plain-English description of what the step should do. Use when asked to add, write or create a custom Behat step, step definition or domain step, or when a scenario needs a step that no registered step provides, in a project that requires drevops/behat-steps.
---

# Create a Behat step

Turn a description of a step into a step definition of the project's own: a method in a trait of the project's, which the project's `FeatureContext` uses. The method calls the public helpers of `drevops/behat-steps`, and as little else as it can.

The description comes with the request, as the argument this skill was invoked with or in the user's message. When there's none, ask for it, and start once you know what the step should do.

The installed package is the source of truth, so read it from `vendor/drevops/behat-steps/` rather than from memory:

- `HELPERS.md` lists every public helper, with its signature and summary. Only these are API.
- `STEPS.md` lists every shipped step, with an example and a link to its source.
- `src/Steps/` holds the shipped steps. A step's body is a thin wrapper over helpers, so the shipped step closest to a job shows which helpers do it.

Change no file before section 5, and commit nothing.

## 1. Inventory

Collect, skipping `vendor/` apart from the package:

- The installed `drevops/behat-steps` version, from `composer.lock`. When it isn't 4.x, stop and tell the user this skill is for 4.x.
- The Behat configuration: `behat.php`, `behat.dist.php`, `behat.yml`, `behat.yml.dist` and the files they import. Note each suite's contexts and paths, and the profile's `autoload` setting.
- The project's context: the registered class that extends `DrevOps\BehatSteps\DrupalContext`, `DrevOps\BehatSteps\WebContext` or `DrevOps\BehatSteps\WebRawContext`, usually `FeatureContext` in `tests/behat/bootstrap/`. Note its file, its namespace, the class it extends and the traits it uses. A context extending `DrupalContext` has the Drupal helpers as well as the web ones.
  - When no registered class extends 1 of the 3, stop: the project registers a shipped context directly, or none. Point the user to https://behat-steps.drevops.com/guides/contexts, which shows the `FeatureContext` to create.
  - When more than 1 does, ask which suite the step is for.
- The project's own step traits: the traits the context uses and the `*Trait.php` files beside it. Note what each covers and how its steps read.
- The commands the project runs Behat, PHP lint, static analysis and its coding standard with, from `composer.json` scripts, `.ahoy.yml`, a `Makefile` or the CI workflow. Run every command below through them, with the arguments shown.

## 2. Check that no step does it already

Search the steps that already exist for the words the description uses:

1. `vendor/bin/behat --definitions "<word>"` prints every registered step whose text contains the word, Mink's and the project's own included. The match is case-sensitive, so search in lowercase.
2. `vendor/drevops/behat-steps/STEPS.md` lists the shipped steps with an example each.

When a registered step, or a few together, already do what the description asks, show them to the user with their examples, and go on only if the user still wants a step in the project's own words, such as 1 line in place of a table. Never write a step with the text of a registered one.

## 3. Write the step text

- `Given` names the state the scenario needs: `Given a page titled :title exists`. `When` names the action or event under test: `When I edit the page titled :title`. `Then` checks with `should` or `should not`: `Then the page titled :title should be authored by the user :name`.
- A value the scenario passes is a placeholder, `:snake_case`, named for what it holds, and the scenario writes it in double quotes. A step that takes a table or a multi-line text ends with a colon, and its text introduces the table: `the following pages exist:`.
- Write words and placeholders only: no regular expression and no optional part. 2 variations of a step are 2 steps.
- Use the project's words, and read like its existing steps.
- When the description needs more than 1 step, such as a `should` check and its `should not` twin, write each as a step of its own.

## 4. Pick the helpers

1. Split the description into the actions and checks it takes, such as creating a page, logging in, opening a page, finding an element or comparing a value.
2. For each one, find the helper in `HELPERS.md`. Read its source in `vendor/drevops/behat-steps/src/` for the shape of its arguments and what it throws. When a shipped step does the same job, open its method in `src/Steps/` and call the helpers it calls.
3. Prefer the helper that does the most of the job: `userCreateAndLogin()` over a user created and then logged in. Prefer a helper to Mink: `elementGet()` fails with the package's exception and message, and a helper that creates an entity registers it for deletion when the scenario ends.
4. Use Mink's own API, such as `$this->getSession()`, `$this->assertSession()` and `$this->visitPath()`, only for a part no helper covers, and tell the user which part that is.

The step never calls any of these, even where PHP lets it:

- A `protected` method. PHP flattens a trait into its context, so a protected method is callable, but any release can change or remove it. `stringParseInteger()` and `stringParseNumber()` are protected, so section 5 shows how to parse a number without them.
- A shipped step's method, such as `pathVisit()`. Semantic versioning covers a step's text, not its method's name, so call the helpers the step calls.
- A class or interface from `src/Backend` or `src/Extension` that no signature in `HELPERS.md` names.
- Drupal's API, such as `\Drupal::entityTypeManager()`. A helper reaches the site through whichever backend the scenario uses, Drush included, while a direct call works only in-process.

When neither a helper nor Mink can do a part, stop and tell the user what's missing, rather than reach for any of these.

## 5. Write the step

Put the step in a trait of the project's:

- Take the project's trait for the area the step belongs to. Otherwise create 1 named for that area of the site, such as `PublishingTrait`, in the directory and the namespace of the context. Never reuse the name of a shipped trait, which `HELPERS.md` and `STEPS.md` list.
- Give a new trait a docblock with a 1-line summary and `@phpstan-require-extends`, naming the class the context extends, so PHPStan and editors know where the helpers come from.
- Start every member name with the trait's prefix: its name without `Trait`, first letter in lowercase, so `PublishingTrait` gives `publishingCreatePage()`. A method with the name of a method the context inherits replaces that method for every shipped step that calls it, so check each new name with `grep -rn "function <name>(" vendor/drevops/behat-steps/src vendor/friends-of-behat/mink-extension/src` and expect no match.
- Name a `Then` step's method with `Assert` after the prefix, as in `publishingAssertPageAuthoredBy()`, and a step that opens a page with `Visit`, as in `publishingVisitEditPage()`.

Write each step method with:

- A `#[Given]`, `#[When]` or `#[Then]` attribute from `Behat\Step`, holding the step text.
- A docblock with a 1-line summary, which `vendor/bin/behat --definitions i` prints.
- A `string` parameter for each placeholder, named as the placeholder is, and a trailing `Behat\Gherkin\Node\TableNode` or `Behat\Gherkin\Node\PyStringNode` for a step ending with a colon. Behat passes every placeholder as a string, so no parameter is an `int`, nullable or given a default.
- A body that parses its arguments, calls helpers and guards on what they return, in a few statements. When it grows, move the work into a public method of the trait, named with the prefix and a verb, so the next step can call it too.

A step that takes a number parses it, and fails on anything else as the package does:

```php
$number = filter_var($count, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);

if ($number === FALSE) {
  throw new \RuntimeException(sprintf('The count must be an integer of 0 or greater, but "%s" was given.', $count));
}
```

A step that fails throws what the package throws for the same failure, so its failures read like the shipped steps' failures:

| Failure | Throw |
| --- | --- |
| A check on the page or the site fails | `Behat\Mink\Exception\ExpectationException`, with `$this->getSession()->getDriver()` as its second argument |
| An element the step looks for is missing | `Behat\Mink\Exception\ElementNotFoundException`, which a helper's `Get` lookup, such as `elementGet()`, already throws |
| A check fails in a trait whose steps never touch the page | `DrevOps\BehatSteps\Steps\Exception\AssertionException` |
| A step argument is invalid, or something the step needs is missing | `\RuntimeException` |

A failure message names what failed with the noun its step uses, puts every value in double quotes and ends with a period: `The page "About us" is not authored by the user "jane", but it should be.`

Here's a trait built this way, for a Drupal site:

```php
<?php

declare(strict_types=1);

use Behat\Mink\Exception\ExpectationException;
use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;

/**
 * Steps for publishing pages.
 *
 * @phpstan-require-extends \DrevOps\BehatSteps\DrupalContext
 */
trait PublishingTrait {

  /**
   * Create a page with a title.
   */
  #[Given('a page titled :title exists')]
  public function publishingCreatePage(string $title): void {
    $this->contentCreate('page', ['title' => $title]);
  }

  /**
   * Log in as a new user with the editor role.
   */
  #[Given('I am logged in as an editor')]
  public function publishingLoginAsEditor(): void {
    $this->userCreateAndLogin('editor');
  }

  /**
   * Open the edit form of the page with a title.
   */
  #[When('I edit the page titled :title')]
  public function publishingVisitEditPage(string $title): void {
    $this->contentVisitActionPageWithTitle('page', $title, 'edit-form');
  }

  /**
   * Assert that the page with a title is authored by a user.
   */
  #[Then('the page titled :title should be authored by the user :name')]
  public function publishingAssertPageAuthoredBy(string $title, string $name): void {
    $uid = $this->queryGetEntityId($this->userGetByName($name), 'uid');

    if ($this->queryNodeIds('page', ['title' => $title, 'uid' => $uid]) === []) {
      throw new ExpectationException(sprintf('The page "%s" is not authored by the user "%s", but it should be.', $title, $name), $this->getSession()->getDriver());
    }
  }

}
```

The page and the user these helpers create are deleted when the scenario ends, as they are for the shipped steps.

## 6. Use the trait in FeatureContext

1. Add `use <Name>Trait;` to the context class, beside the traits it already uses, unless it's there already. A trait in another namespace takes an import too.
2. Leave the Behat configuration as it is. The profile's `autoload` setting, or the project's Composer autoloading for a namespaced context, loads the trait by its name the way it loads the context, so check that the trait's file sits where that autoloading looks.
3. Never register another context built on `WebContext` or `DrupalContext` beside the project's, and never add a shipped trait to the context: Behat registers each of their steps twice and stops.

## 7. Add a scenario

A step that no scenario runs is never tested, so add 1 scenario that uses it, unless the user says not to. Put it in the feature file that covers the step's area, or in a new file beside the others named for that area, written in the project's style with the steps it already has.

## 8. Verify

Run each command on its own:

1. `php -l` on each PHP file you changed.
2. The project's static analysis and coding standard, when it has any, on the files you changed.
3. `vendor/bin/behat --definitions l`, which lists the new step once. 2 steps with the same text make it fail with a redundant step error.
4. `vendor/bin/behat --dry-run <feature file>`, which reports a step line that matches no step or more than 1 without running the scenario.
5. The new scenario against the site, as `vendor/bin/behat <feature file>:<line>`. When the site can't be reached from where you run, say so, and give the user the command.

When a command fails, fix the cause in the step, the trait or the scenario, and run the commands again from the first.

## 9. Report

Tell the user:

- The step text, with an example line for a scenario.
- The trait and the method, and whether the trait is new.
- The helpers the step calls, and every part it does without a helper, with the reason.
- The result of each command in section 8.

## Common mistakes

- **Calling a protected method because `$this` reaches it.** Only `HELPERS.md` lists API.
- **Calling a shipped step's method.** Call the helpers its body calls.
- **Reusing a shipped name.** A trait named like a shipped one carries its prefix, and a method named like an inherited one replaces it for every shipped step that calls it, without an error.
- **Duplicating step text.** Behat stops before the first scenario when 2 steps share their text.
- **An `int`, nullable or defaulted parameter.** Every placeholder arrives as a string.
- **Calling Drupal directly.** The step then works only in-process.
- **Registering the trait in the Behat configuration, or another context beside the project's.** The context's `use` line is all it takes.
