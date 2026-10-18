---
name: migrate-behat-steps
description: Migrate a project from drevops/behat-steps 3.x to 4.x by applying the package's MIGRATION.md. Plans every change the guide calls for without touching the project, asks before applying the plan, then applies it, verifies and reports. Use when upgrading drevops/behat-steps from 3.x to 4.x, when replacing drupal/drupal-extension with behat-steps 4, when converting Behat steps to their 4.x wording, or when the behat-steps MIGRATION.md is mentioned.
---

# Migrate behat-steps 3.x to 4.x

The package's `MIGRATION.md` is the single source of truth for this migration: every change, every mapping and every convention for reading its tables lives there. This skill is only the procedure for applying it, and it states no fact about either version of its own. Where the guide is silent, record an open question rather than a guess: a name the guide doesn't list didn't change.

Nothing in the project changes until the user approves the plan.

## 1. Get the guide

Fetch `https://raw.githubusercontent.com/drevops/behat-steps/main/MIGRATION.md` and save it as `.artifacts/behat-steps-migration/MIGRATION.md`. Check that its first line reads `# Migrating from 3.x to 4.0`: the copy in `vendor/drevops/behat-steps/` is the 3.x guide until Composer installs 4.x. Fetch the files it links to, such as `docs/content/steps.md`, from the same place when you need them. If a fetch fails, stop and ask the user for the file.

Read the guide's opening paragraph and `## How to read this guide` first, and follow its conventions literally.

Keep `.artifacts/behat-steps-migration/` out of version control: when `.gitignore` doesn't cover it, add it to `.git/info/exclude`.

## 2. Inventory

Work on a branch with a clean working tree, so `git diff` shows the whole migration. Collect, skipping `vendor/`:

- the installed `drevops/behat-steps` version, from `composer.lock`. When it's older than the release the guide starts from, stop and tell the user what the guide's opening paragraph says to do;
- `composer.json`, every Behat configuration file (`behat.yml`, `behat.yml.dist`, `behat.php`, `behat.dist.php` and the files they import), and the CI workflows, `.env` files and scripts that set Behat environment variables;
- every PHP file that names a class, trait or interface from a `3.14` column of the guide;
- every `*.feature` file;
- the commands the project runs Behat and static analysis with, from `composer.json` scripts, `.ahoy.yml`, a `Makefile` or the CI workflow.

## 3. Plan

Walk the guide section by section, in its own order, and record every change each section calls for in this project. Change no file and run no Composer command while planning.

Record each change with its file and line, the text before, the text after, and the guide section and row behind it. Record as an open question anything the guide leaves to the project or that can't be worked out from it: a choice between options the guide offers, a value a new step needs that the old one didn't carry, a step that 2 rows match and map differently.

The guide says what changes. These rules say how to apply it:

- **1 pass per table.** Match every name against the `3.14` column only, never against a name an earlier row produced.
- **Step lines.** Match a step against a `3.14` cell the way Behat would: the keyword doesn't take part, and a quoted value is 1 argument, spaces and all.
- **4.x text first.** Before matching a step against the guide's rows, check it against the steps 4.x registers: those in `docs/content/steps.md`, those in Mink's `MinkContext` in `vendor/`, and the project's own. A step that already matches keeps its text and only takes the keyword of a row that also matches it, so a second run changes nothing.
- **Keywords.** Take a rewritten step's keyword from its `4.0` cell. `And` and `But` take the type of the step above them, so when a rewrite changes that type for the lines below it, give each of those lines the keyword its own step is registered with, or `And` where that matches the line above. Never reorder steps.
- **Tables and docstrings** under a step stay as they are, unless the guide says how they change or the new step takes another shape. Build that from the new step's example in `docs/content/steps.md`.
- **Step behavior.** Where a part of `## Step behavior` says how to change a step, make that change only as part of rewriting the step's `3.14` text, so a second run can't apply it again. Record everything else in that section as an item to review.

Write the plan to `.artifacts/behat-steps-migration/plan.md`: a summary with the number of changes per guide section, the Composer commands, the open questions and the review items, followed by every change, per file.

## 4. Ask

Show the user the plan's summary and its open questions, then ask whether to apply the plan. Apply nothing without a yes. When the user answers an open question, update the plan before applying it.

## 5. Apply

1. Run the plan's Composer commands, 1 at a time. When Composer refuses one, report its error and stop.
2. Make every file change in the plan. Where an open question is still unanswered, leave the line as it is and add a `TODO(behat-steps 4.x)` comment above it, in the file's comment syntax, quoting the guide.
3. Run step 3 once more over the result. It must find nothing left to change.

Don't commit unless the user asks.

## 6. Verify

Run each command on its own, with the project's own commands from the inventory:

1. `composer validate`, and `php -l` on every PHP file that changed.
2. The project's static analysis, when it has any.
3. `vendor/bin/behat --dry-run`, with the project's profile and suite options. It reports every undefined step without running a scenario.
4. The project's full Behat suite.
5. A read of every changed scenario. Behat ignores keywords, so no run catches a wrong one.

When a command fails, find the guide row behind the failure before changing any code.

## 7. Report

Turn the plan into `.artifacts/behat-steps-migration/report.md` and show its summary: what changed per file, the result of each verification command, and what's left for the user, which is every TODO, every review item and every open question.

## Common mistakes

- **Reading the 3.x guide.** Until Composer installs 4.x, `vendor/drevops/behat-steps/MIGRATION.md` is the 3.x guide.
- **Filling a gap from memory.** When the guide doesn't say it, it's an open question.
- **Changing a step twice.** Check for 4.x text first, and tie every change from `## Step behavior` to a rewrite.
- **Renaming in sequence.** 1 pass per table.
- **Pairing values by position.** Follow the guide's placeholder convention.
- **Trusting a passing run.** Behat ignores keywords, and some PHP changes fail quietly, as the guide notes where they do. Read the diff against the guide.
