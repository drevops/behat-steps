---
title: Migrating from 3.x
sidebar_position: 11
description: Move your project from Behat Steps 3.14 to 4.0 with the migration guide, by hand or with the migrate-behat-steps skill for AI coding agents.
---

# Migrating from 3.x

If your project runs Behat Steps 3.x, use this page to move it to 4.x: with an AI coding agent that applies the migration guide for you, or by hand. Both ways end with the same checks, which confirm that the migration is complete.

[MIGRATION.md](../../MIGRATION.md) lists the changes from 3.14 to 4.0 in the order you make them: Composer requirements, Behat configuration, context classes, feature files, then the PHP your project writes against the package. Its tables list every name a 3.14 project used that 4.0 changes or removes, and what replaces it. That covers names from this package and the 2 packages its 3.x Drupal traits built on, the Drupal Extension (`drupal/drupal-extension`) and the Drupal Driver (`drupal/drupal-driver`). A step the tables don't list keeps its text.

The 4.x releases after 4.0 keep the public API compatible, as [Public API and versioning](architecture/versioning.md) explains, so the same changes apply to any 4.x release. The update itself is `composer require --dev drevops/behat-steps:^4`, which installs the latest 4.x release.

Read the guide through the link on this page rather than from your project: until Composer installs 4.0, the copy in your project's `vendor/drevops/behat-steps/` is the 3.x guide.

## Before you begin

Make sure your project has the following:

- Behat Steps 3.14. `composer show drevops/behat-steps` prints the release your project has.
- A clean working tree, so the migration starts from a commit you can return to.
- A suite that passes on 3.14, so a failure after the migration points at a change it made.

If your project is on an earlier 3.x release, update it to 3.14:

```bash
composer require --dev drevops/behat-steps:~3.14.0
```

Then commit the update and check that your suite passes on 3.14.

## Migrate with an AI coding agent

An AI coding agent can apply the guide for you with the [`migrate-behat-steps`](../../.claude/skills/migrate-behat-steps/SKILL.md) skill. The agent plans every change the guide calls for and shows you a summary of the plan. It changes your code, configuration and feature files only after you approve the plan. Until then, it writes only the following:

- Its working files, under `.artifacts/behat-steps-migration/`.
- A line in `.git/info/exclude` that keeps those files out of version control, when your `.gitignore` doesn't already cover them.
- The skill itself, as `.claude/skills/migrate-behat-steps/SKILL.md`, when your agent saves skills.

It stops if your project is on a release older than 3.14.

Your agent needs to fetch a URL and run commands in your project. It runs Composer and Behat through your project's own commands when it has them, such as an Ahoy or Make target. The package ships without the repository's `.claude/` directory, so your agent fetches the skill from GitHub.

To migrate with your agent:

1. Create a branch from a clean working tree, so `git status` and `git diff` show the whole migration.
2. From your project's root, paste this into your agent:

   ```text
   Fetch
   https://raw.githubusercontent.com/drevops/behat-steps/main/.claude/skills/migrate-behat-steps/SKILL.md
   and follow it to migrate this project from drevops/behat-steps 3.x to 4.x.
   If you support skills, save it as .claude/skills/migrate-behat-steps/SKILL.md
   first, so the migration can run again.
   ```

   `main` in the URL is the repository's 4.x line. The agent shows the plan's summary and its open questions, and the full plan is in `.artifacts/behat-steps-migration/plan.md`.
3. Reply to the agent with answers to the open questions you can, then approve the plan in the same conversation. The agent runs the Composer commands, changes the files and checks the result with the following:
   - `composer validate`, and `php -l` on every PHP file that changed
   - Your project's static analysis, when it has any
   - `vendor/bin/behat --dry-run`, which reports any undefined step without running a scenario
   - Your full Behat suite
   - A read of every changed scenario, because Behat matches a step by its text and not its `Given`, `When` or `Then` keyword, so no run catches a step with the wrong keyword

   When a check fails, the agent traces the failure to the guide row behind it before it changes any code.
4. Read the agent's report in `.artifacts/behat-steps-migration/report.md`. It gives the result of each check and lists what's left for you: every `TODO(behat-steps 4.x)` comment it added for a question you left unanswered, every change to review and every open question.

Confirm that the migration is complete: you've resolved every item the report lists, and the checks in step 3 pass when you run them again. The agent doesn't commit, so review `git status` and `git diff` and commit the changes yourself. If your agent saved the skill, commit `.claude/skills/migrate-behat-steps/SKILL.md` to keep the migration repeatable, or delete it. `.artifacts/behat-steps-migration/` stays out of version control, so delete it when you no longer need the plan and the report.

## Migrate by hand

To migrate by hand:

1. With your project on 3.14, create a branch from a clean working tree, so `git status` and `git diff` show the whole migration.
2. Read [How to read this guide](../../MIGRATION.md#how-to-read-this-guide): every table follows the same conventions, and they decide how a row applies to your project.
3. Work through the guide's sections in order, starting with [Requirements](../../MIGRATION.md#requirements), which also covers removing the Drupal Extension from a Drupal project before Composer installs 4.0.

Confirm that the migration is complete with the same checks the agent runs, which step 3 of [Migrate with an AI coding agent](#migrate-with-an-ai-coding-agent) lists, through your project's own commands.

## Update your AI agent's reference

If your project's `CLAUDE.md` or `AGENTS.md` points its agent at `vendor/drevops/behat-steps/STEPS.md`, that path still works in 4.0. 4.0 adds 2 files the agent can use:

- `vendor/drevops/behat-steps/HELPERS.md` lists the helpers your own step definitions can call.
- `vendor/drevops/behat-steps/skills/create-behat-step/SKILL.md` is the `create-behat-step` skill, which writes a step of your own on those helpers.

The migration guide doesn't cover these files, so point the agent at them yourself. Add a line for each, or replace your snippet with the full one in [Writing tests with AI assistants](guides/ai-assistants.md).
