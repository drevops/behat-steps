---
name: migrate-behat-steps
description: Migrate a project from drevops/behat-steps 3.x to 4.x by applying the package's MIGRATION.md to its Composer requirements, Behat configuration, context classes, the PHP that extends or calls the package, and its feature files, then report what still needs a human. Use when upgrading drevops/behat-steps from 3.x to 4.x, when replacing drupal/drupal-extension with behat-steps 4, when converting Behat steps to their 4.x wording, or when the behat-steps MIGRATION.md is mentioned.
---

# Migrate behat-steps 3.x to 4.x

This skill upgrades a project that uses `drevops/behat-steps` 3.x to 4.x. The package's `MIGRATION.md` is the data: every rename, removal and behavior change has a row in its tables. This skill is the procedure for applying those rows to a project. Never invent a mapping. A name the guide doesn't list didn't change.

The guide's baseline is 3.14. A project on an earlier 3.x release updates to the latest 3.14 first, gets its suite passing there, and only then runs this skill.

## Inputs

Detect these, then confirm them with the user in 1 message before changing anything:

1. **Targets**: the files to migrate. By default, that's every `*.feature` file; every PHP file that names `DrevOps\BehatSteps`, `Drupal\DrupalExtension`, `Drupal\Driver` or `Drupal\MinkExtension`; every Behat configuration file (`behat.yml`, `behat.yml.dist`, `behat.php`, `behat.dist.php` and the files they import); `composer.json`; and the CI workflows, `.env` files and scripts that set Behat environment variables. Skip `vendor/` and anything the user excludes.
2. **Composer commands**: the ones Step 3 will run. Composer changes the project's dependencies, so list them in the confirmation.
3. **Dry run**: when the user asks for one, change no file, run no Composer command, and only write the report.
4. **Verification commands**: how the project runs Behat and static analysis, such as `vendor/bin/behat`, `ahoy test-bdd` or `composer test-bdd`, and `vendor/bin/phpstan`. Read them from `composer.json` scripts, `.ahoy.yml`, a `Makefile` or the CI workflow when they exist.

Work on a branch with a clean working tree, so `git diff` shows the whole migration. Don't commit unless the user asks. Keep your working files in `.artifacts/behat-steps-migration/`, and keep that directory out of version control: when `.gitignore` doesn't already cover it, add it to `.git/info/exclude`.

## Step 1: Inventory

1. Read the installed version from `composer.lock`, or run `composer show drevops/behat-steps`. Below 3.14, stop and tell the user to update to the latest 3.14 first. Already on 4.x, carry on: every step below is safe to repeat.
2. List the targets from the Inputs and count them per type. Note which contexts each Behat suite registers, which classes those contexts extend, and which traits they compose.

## Step 2: Get the guide

The guide has to be the 4.x one, whose first line is `# Migrating from 3.x to 4.0`. Until Composer installs 4.x, the copy under `vendor/` is the 3.x guide, so:

- Before Step 3, fetch `https://raw.githubusercontent.com/drevops/behat-steps/4.x/MIGRATION.md` and save it as `.artifacts/behat-steps-migration/MIGRATION.md`. If the fetch fails, stop and ask the user for the file.
- From Step 3 on, read `vendor/drevops/behat-steps/MIGRATION.md` instead: it matches the version installed. `vendor/drevops/behat-steps/STEPS.md` lists every 4.x step, each on a `<summary><code>@Then ...</code></summary>` line followed by a Gherkin example, and `vendor/drevops/behat-steps/HELPERS.md` lists the public helpers.

Read the guide's `## How to read this guide` section before anything else, and follow its conventions literally. Read a table row this way:

- Split the row on every `|` that isn't preceded by a backslash, drop the empty first and last cells, and trim each cell. `\|` inside a cell is a literal `|`.
- The table's header row names the columns, so read it before using a row. In a mapping table the first column is `3.14`, or names the 3.14 thing, as `3.14 parent` does: it holds what a 3.14 project used, the `4.0` column holds what replaces it, and an optional `Notes` column carries the rest of the rule. A table whose first column names a subject instead, such as `Step`, `Trait` or `Method`, is keyed by that subject, and its `3.14` and `4.0` columns describe that same subject before and after.
- A table that says it isn't a mapping, as the one under `### Names that now belong to another method` does, lists names to check by hand, not rules to apply.
- Strip the backticks around a value. `Removed`, alone and without backticks in a `4.0` cell, means nothing replaces the name.
- A part of a name in angle brackets, such as `<Name>` or `<N>`, stands for the same value on both sides, so `Drupal\Driver\<Name>` to `DrevOps\BehatSteps\Backend\<Name>` maps every class the table doesn't list by name.
- A method reads `Owner::method()`, where the owner is the short name of the trait, class or interface that declares it.

## Step 3: Composer requirements

Apply `## Requirements`:

1. Check that the project can run 4.x: it needs PHP 8.3 or newer, Drupal 11 or 12 on a Drupal site, and Drush 13 or newer when a profile uses the Drush backend. If it can't, stop and report why.
2. Remove the packages 4.x replaces before updating, because 4.x conflicts with every version of both:

   ```bash
   composer remove --dev drupal/drupal-extension
   ```

   Add `drupal/drupal-driver` to the same command when `composer.json` requires it directly. If another package still requires either one, Composer's error names it: report that package and stop.
3. Require 4.x the way the Installation section of the package README shows, adding `behat/behat:^4` and the alpha `friends-of-behat/mink-extension` only when the user wants Behat 4:

   ```bash
   composer require --dev drevops/behat-steps:^4
   ```

4. Apply the rest of the package table under `## Requirements`. In particular, require `lullabot/mink-selenium2-driver` directly when the suite runs `@javascript` scenarios through Selenium, because it came with the Drupal Extension.

Once 4.x is installed, PHP that still names a Drupal Extension or Drupal Driver class fails to load until Steps 4 to 6 are done. That's expected partway through.

## Step 4: Behat configuration

Apply each subsection of `## Behat configuration` to every Behat configuration file, YAML and PHP alike:

- `### Extensions`: rename the extension keys. In `behat.php`, use the class names the guide's example shows.
- `### Extension settings`: move or rename each listed setting and delete `suppress_deprecations`. A dotted path such as `steps.wait.ajax_timeout` is a nested key.
- `### HTTP client options`: replace `guzzle_request_options` with `http_client_parameters`, translating each option with the table. Report an option the table doesn't list.
- `### Backends`: replace `default_driver`, `api_driver` and `drush_driver` as the table maps them, keeping the `drupal:` and `drush:` settings blocks the listed backends need. `blackbox` needs none. Copy the 2 consequences of removing `@api` that the section lists into the report. A suite or profile that filters on `@api`, such as `filters: { tags: '@api' }` or `'~@api'`, selects scenarios by a tag Step 8 removes, so report it too: the user can give those scenarios a profile with its own `backends` list instead.
- `### Contexts to register`: in each suite, replace the Drupal Extension's contexts with `DrevOps\BehatSteps\Behat\Context\DrupalContext`, or `WebContext` for a suite that doesn't test Drupal, and its `MinkContext` with Mink's `Behat\MinkExtension\Context\MinkContext`. Never register `WebContext` beside `DrupalContext`, and drop `RandomContext` and `MappingContext`. `DrupalContext` composes `WatchdogTrait`, so when the site has no `dblog` module or the profile lists no in-process backend, report the 2 ways out the guide gives instead of picking one.
- `### Environment variables`: rename the variable everywhere the project sets it.
- `### Trait options`: leave the project's `Get`-method overrides in place; Step 6 makes them `public`. List in the report the ones a configured option could replace.

## Step 5: Context classes

For each class a suite registers as a context, apply `## Context classes` and the class table under `## Classes and interfaces`:

- **Parent**: map the 3.14 parent with `### Base classes`. Of the web traits 3.14 shipped, only `JsonTrait`, `LinkTrait`, `PathTrait`, `ResponseTrait`, `ResponsiveTrait` and `XmlTrait` still compose onto Mink's `RawMinkContext`, so a context composing any other trait extends `WebRawContext` or a class below it.
- **Constructor**: when the class declares a constructor, add `array $config = []` and forward it with `parent::__construct($config)`, as `### Constructor` shows.
- **`use` statements**: move the traits to their 4.x namespaces with `### Trait namespaces`, and every other Drupal Extension, Drupal Driver and package class with the table under `## Classes and interfaces`.
- **Interfaces**: replace `DrupalAwareInterface` as `### Interfaces` describes, with the new types in the accessor signatures.
- **Composition**: apply `### Trait composition`. Compose `KeyboardTrait` where a context got its keyboard steps from `FieldTrait`, and `ContentTrait` where it got its content steps from `SearchApiTrait`. A context that extends a shipped context drops the `use` lines for the traits that context already composes. A context registered beside a shipped one composes none of the traits the shipped one does, because 2 registered contexts composing the same trait throw a `RedundantStepException`. Replace a removed trait (`HelperTrait`, `Drupal\HelperTrait`, `OverrideTrait`) with the helper traits that hold the members the context calls; Step 6 maps those members.

## Step 6: The project's own PHP

The guide covers this PHP after the feature files. Do it first: Step 7 asks Behat for the step patterns the suite registers, and Behat lists them only once every context loads.

Apply the sections from `## Classes and interfaces` to `## HTTP clients` to every PHP target:

1. **Classes**: update `use` statements, fully qualified names, docblocks and class-name strings with the table under `## Classes and interfaces`. A class of your own can't extend a class that table marks final, and PHPUnit can't double one: implement or double its interface, as `### Final classes` says, or report it.
2. **Methods**: build 1 rename map from every table under `### Renamed, moved and removed methods`, keyed by the 3.14 `Owner::method()`. Find every call (`->name(`, `::name(`), every override or implementation (`function name(`), and every callable or string that names a method, and resolve its owner from the receiver: `$this` in a context is the context and the traits it composes, while a variable holding a backend is a capability interface.
   - Apply the whole map in 1 pass, matching each name against the `3.14` column only. Some 4.0 names are 3.14 names of other methods, so renaming in sequence would rename some twice. Check every hit on a name under `### Names that now belong to another method` by hand.
   - For a `Removed` row, replace the call as the Notes say, or add `// TODO(behat-steps 4.x): <the Notes>` and record it.
   - When the Notes change a parameter, a return type or what a lookup does when nothing matches, update the call and every override to match.
3. **Loaded entities**: the 5 helpers under `### Methods that return loaded entities` return entities keyed by entity ID, where they returned IDs, and the call itself doesn't fail. Drop the project's own load of what they return, or call `queryEntityIds()` where only the IDs are needed, as the section shows.
4. **Signatures**: update every override of a method under `### Signature changes` to its 4.0 signature, and every call that passes a renamed parameter by name. Move every call that still passes a removed argument to the method the table names: PHP drops an extra argument without a word, so `fileCreateUnmanaged($uri, 'Hello')` writes `test` and never fails.
5. **Visibility**: an override of a method that's `public` in 4.x has to be `public` too, or the class doesn't load. Check every `protected function` in the project's contexts and traits against `### Visibility changes` and against the renamed rows whose Notes say "now `public`" or "now `public static`". A call from outside the context to a method that's `protected` now has to go.
6. **Override points**: an override left under its 3.14 name is never called, and nothing reports it. Rename every override of a row noted "Override point", and declare it the way the Notes say, `public` or `public static`.
7. **Properties and constants**: apply `## Properties and constants`. A constant your context declares under the name of 1 it now inherits has to declare the same type.
8. **Hooks**: apply `## Hooks`. Drop the argument from an entity creation hook attribute and move the filter into the method body, as its example shows, then list the hook in the report: a hook declared with an argument never ran in 3.14, so it now runs for the first time. Remove the hook attribute from an override of a method that the Notes say isn't a hook any more, and give every overridden hook its scope parameter.
9. **Traits of your own**: apply `## Traits of your own` to the skip guards, module checks and entity registration in the project's own traits.
10. **Exceptions**: update every `catch` block and every `expectException()` that names a changed type, with `## Exceptions`.
11. **Backends, the container, browser drivers and HTTP clients**: for a project with its own backend, Behat extension, service definitions, browser driver adapter or file download options, apply `## Backend contracts`, `## Service container`, `## Capabilities of the browser driver` and `## HTTP clients`. A container parameter left under its old name is never read and never reported, so search for every old name the `## Service container` tables list.

## Step 7: Step text in feature files

### Build the rules

Read every table under `## Step text`: the per-trait tables under `### Steps of this package` and the grouped tables under `### Drupal Extension steps`. Each row is 1 rule.

The **3.14 cell** holds 1 step in Behat's Turnip syntax, keyword first. Turn it into a matcher for the whole step text:

- Drop the keyword: Behat matches a step's text without it.
- `:name` matches 1 argument: a double- or single-quoted string, spaces and all, or 1 unquoted run of letters, digits, `_`, `.` and `,` with an optional leading `-`, such as `-200`.
- A word or phrase in parentheses is optional: `role(s)` matches `role` and `roles`, `( region)` matches with or without ` region`, and `(e)mail(s)` matches `mail`, `email`, `mails` and `emails`.
- `a/an` matches either word: a slash between 2 words offers both.
- A cell ending in `...` names a family and matches every step that starts with the text before the `...`.
- Everything else matches literally, case included.

The **4.0 cell** holds 1 of 3 things: 1 step, which is the replacement; several steps separated by `<br>`, a sequence that replaces the 1 old step; or `Removed`.

The **Notes cell**, where the table has one, is part of the rule: apply it. "Pass the id as the selector", "1 step per attachment" and "Give the table an `author` column" tell you how to build the replacement, and rule 4 below covers "Mink's step".

Order the rules so the most specific wins: rules without `...` before family rules, and within each group the longest 3.14 text first.

### Rewrite each step

Process each `.feature` file line by line. Leave these lines exactly as they are: comment lines (`#`), blank lines, tag lines (`@`), the `Feature:`, `Rule:`, `Background:`, `Scenario:`, `Scenario Outline:`, `Scenario Template:`, `Example:` and `Examples:` lines, table rows (`|`), and every line inside a docstring, which opens and closes on a line of `"""` or ```` ``` ````. A step line starts with `Given`, `When`, `Then`, `And`, `But` or `*` after its indentation: split it into the indentation, the keyword and the text.

For each step:

1. **Already 4.x**: once Steps 3 to 6 are done, `vendor/bin/behat --definitions=l`, with the suite's profile and suite options, lists every pattern the suite registers, Mink's and the project's own included. A step whose text matches 1 of them keeps its text. Some rows only rename a placeholder or change the keyword, as `Given I run cron` to `When I run cron` does, so when a rule also matches the step, apply that row's keyword (rule 6) and nothing else. When Behat can't list the patterns, use the ones in `STEPS.md` and Mink's `MinkContext` instead. This check comes first, so a second run of this step changes nothing.
2. **Match**: find the rules whose matcher matches the whole text. With none, leave the step and record it as unknown; Step 10 catches it if it's broken. When 2 rules match and disagree, leave the step, add `# TODO(behat-steps 4.x): ambiguous, matches <the 3.14 cells>` on the line above it, and record it.
3. **Carry the arguments**: capture each placeholder's value from the old text, quotes included. A placeholder named in both cells keeps its value. Pair the rest by role, never by position. The guide's renames include `:tag` to `:selector`, `:type` to a bundle placeholder such as `:content_type`, `:rowText` to `:partial_text`, `:to` to `:address` and `:urlFragment` to `:partial_url`, and `:text` to `:value` in the rows whose 4.0 step has no `:text`. The 2 steps can name their placeholders in a different order, as `Then I should see :text in the :tag element in the :region( region)` and `Then the element :selector in the region :region should have the value :value` do. A placeholder the 4.0 step doesn't name is dropped, so record its value in the report: the step no longer checks it. When the 4.0 step needs a value the 3.14 step didn't carry, such as an `:href`, a `:queue` or a radio button's `:selector`, take it from the scenario or the site when it's unambiguous; otherwise keep the old step, add a TODO and record it. A Scenario Outline's `<name>` is a value like any other: keep it exactly as written.
4. **Rewrite**: replace the text with the 4.0 step. A sequence becomes 1 line per step, in order, at the same indentation. For `Removed`, keep the old step and add `# TODO(behat-steps 4.x): removed, <the Notes>` on the line above it. 3 kinds of row change a value as well as the text:
   - A row noted "Mink's step" maps to 1 of Mink's regex patterns, which fix the quoting: write each value in double quotes, apart from a status code, which Mink takes as bare digits, as in `Then the response status code should be 200`.
   - The rows for `there should be a total of :count (e)mail(s) sent` and its longer forms map to steps that take only a number, so a `:count` of `no` becomes `0`, and `a` or `an` becomes `1`, as `### Numbers in step arguments` says.
   - The 2 rows that rename `within a viewport with a top offset of :number pixels` also negate the offset, so `200` becomes `-200`, as `### Viewport and scrolling` says. The negation rides on the rewrite of the 3.14 text, so it happens once.
5. **Tables and docstrings**: never edit a table row or a docstring line, with 2 exceptions. The message steps under `#### Messages` lose their header row, so delete a first row that only names the message type, such as `| error messages |`. And when a 4.0 step takes a table or a docstring of a different shape than the old step had, or takes 1 where the old step took none, build it from the 4.x step's example in `STEPS.md`, using the old step's values, such as a `title` column for `Given a/an :type with the title :title`. When you can't build it, keep the old step, add a TODO and record it.
6. **Keyword**: Behat ignores the keyword when it matches, so a wrong keyword passes every run and only this rule catches it.
   - A rewritten step takes the keyword its 4.0 cell carries: `Given I run cron` becomes `When I run cron`.
   - `And` and `But` take the type of the nearest `Given`, `When` or `Then` above them. When a rewrite changes that type for the `And` and `But` lines below it, give each of them the keyword its step is registered with, as the listing in rule 1 or `STEPS.md` shows, or the keyword it resolved to before the rewrite when its step isn't listed.
   - Wherever this rule sets a keyword, write `And` when the line above resolves to the same one.
   - Change keywords only. Never reorder or regroup steps to suit a keyword.

Run this step a second time when it's done: it must change nothing.

## Step 8: Tags

Apply `## Tags` to every tag line in the feature files, the lines of `@` tags above a `Feature:`, `Rule:`, `Scenario:`, `Scenario Outline:` or `Examples:` keyword:

- Rename each tag in the `3.14` column to its `4.0` tag, and delete each tag whose `4.0` cell is `Removed`. When 2 old tags became the same tag, keep 1: `@behat-steps-skip:emailBeforeScenario @behat-steps-skip:emailAfterScenario` becomes `@behat-steps-skip:EmailTrait`.
- A row whose `4.0` cell repeats the `3.14` tag, such as `@error` or `@email:TYPE`, kept the tag and changed what it means. Leave the tag, and copy the scenario's name and the row's Notes into the report.
- Any other `@behat-steps-skip:` tag fails the run unless it names a trait that a context of the suite composes, as `### Skip tags` shows. A tag naming a hook method of the project's own trait becomes `@behat-steps-skip:` plus that trait's name once Step 6 moves the guard to `skipTag(__TRAIT__)`. Report every other one.
- Check each tag on a `Feature:` line against the table under `### Tags on the `Feature:` line`: those tags now act on every scenario below them. Report each one with the guide's advice, and move a tag down onto its scenarios only when the user asks.

## Step 9: Behavior to review

`## Step behavior` lists the steps that kept their text, or took a 4.x one, and now do something else. Nothing above catches them, and most need the user's judgment. For each of its subsections, search the feature files for the steps it names and list every hit in the report with the guide's advice. Change nothing in this step: the 1 rule the guide states for keeping a scenario's meaning, negating the viewport offset, already ran in Step 7.

## Step 10: Verify

Skip this step on a dry run. Run each command on its own, with the commands the user confirmed:

1. `composer validate`, and `php -l` on every PHP file you changed.
2. Static analysis, when the project has it. Each 4.x trait names the context it needs in a `@phpstan-require-extends` annotation, so PHPStan reports a context on the wrong base class, and it reports a call that still passes a removed argument.
3. `vendor/bin/behat --dry-run`, with the project's profile and suite options. It reports every undefined step without running a scenario. Look each 1 up in the guide again, and report what's left.
4. The project's full Behat suite.
5. A passing suite proves nothing about keywords, because Behat ignores them. Read the diff of every scenario you changed, and confirm each block still reads precondition, action, then assertion.

When a command fails, find the guide row behind the failure before changing any code.

## Step 11: Report

Write the report to `.artifacts/behat-steps-migration/report.md`, even on a dry run, and show its summary to the user:

- **Summary**: the files scanned and the files changed, per type; the steps rewritten, already on 4.x, removed, ambiguous and unknown; the tags renamed and removed; the PHP renames; the Composer changes; and the result of each verification command.
- **Per file**: each change, with its line number, the text before, the text after, and the guide section of the rule that drove it.
- **Manual follow-up**: every TODO left in a file, every behavior change to review from Step 9, every value a rewrite dropped or couldn't work out, every hook that now runs for the first time, and every decision left to the user, such as the watchdog check on a site without `dblog`.

## Common mistakes

- **Reading the 3.x guide**: until Composer installs 4.x, `vendor/drevops/behat-steps/MIGRATION.md` is the 3.x guide. Check that the first line reads `# Migrating from 3.x to 4.0`.
- **Migrating a step twice**: the already-4.x check in Step 7 runs before any rule matches. Without it, a second run can rewrite a step that's already right, or negate a viewport offset back.
- **Renaming methods in sequence**: 1 pass, matched against the `3.14` column only.
- **Pairing placeholders by position**: pair them by name, then by role.
- **Quoting a Mink value the Turnip way**: Mink's patterns take a double-quoted value, or bare digits for a status code. A single-quoted value or a quoted status code leaves the step undefined.
- **Leaving the old keyword on a rephrased step**: Behat matches the text alone, so the wrong keyword survives every test run, every dry run and CI. Only the keyword rule in Step 7 catches it.
- **Treating `And` as fixed**: it inherits from the step above it, so a keyword change above it can change what it means.
- **Editing tables or docstrings**: a step line changes, while the rows under it stay as they are, apart from the 2 exceptions in Step 7.
- **Splitting a quoted value**: `"admin user"` is 1 argument.
- **Trusting a passing run for the PHP**: an override under an old name, a `protected` override of a now-`public` method, an extra argument and a container parameter under its old name fail quietly or not at all. Find them by reading the code against the guide.
