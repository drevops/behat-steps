---
title: Migrating from 3.x
sidebar_position: 11
description: Move your project from Behat Steps 3.14 to 4.0 with the migration guide, by hand or with the migrate-behat-steps skill for AI coding agents.
---

# Migrating from 3.x

[MIGRATION.md](../../MIGRATION.md) takes your project from 3.14 to 4.0 in the order an upgrade runs into each change: Composer requirements, Behat configuration, context classes, feature files, then the PHP your project writes against the package. Its tables list every name a 3.14 project used that 4.0 changes or removes, and what replaces it, whether the name came from this package or from the Drupal Extension (`drupal/drupal-extension`) and Drupal Driver (`drupal/drupal-driver`) that its Drupal traits built on. If the tables don't list a step, its text didn't change.

Read the guide through the link on this page rather than from your project: until Composer installs 4.0, the copy in your project's `vendor/drevops/behat-steps/` is the 3.x guide.

`composer show drevops/behat-steps` prints the release your project has. If it's an earlier 3.x release, update it to 3.14 first:

```bash
composer require --dev drevops/behat-steps:~3.14.0
```

## Migrate with an AI coding agent

An AI coding agent can apply the guide for you with the [`migrate-behat-steps`](../../.claude/skills/migrate-behat-steps/SKILL.md) skill. It plans every change the guide calls for, shows you the plan and changes none of your project's files until you approve it. Before that, it writes only its working files under `.artifacts/behat-steps-migration/`, which it keeps out of version control through `.git/info/exclude` when your `.gitignore` doesn't already cover them, and the skill itself, when your agent saves skills. It stops if your project is on a release older than 3.14.

Your agent needs to fetch a URL and run commands in your project. It runs Composer and Behat through your project's own commands when it has them, such as an Ahoy or Make target. Composer installs this package without the repository's `.claude/` directory, so the skill isn't in `vendor/`, and your agent fetches it instead.

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

   The agent shows the plan's summary and its open questions. The full plan is in `.artifacts/behat-steps-migration/plan.md`.
3. Reply to the agent with answers to the open questions you can, then approve the plan in the same conversation. The agent runs the Composer commands, changes the files and checks the result with the following:
   - `composer validate`, and `php -l` on every PHP file that changed
   - Your project's static analysis, when it has any
   - `vendor/bin/behat --dry-run`, which reports any undefined step without running a scenario
   - Your full Behat suite
   - A read of every changed scenario, because Behat ignores keywords and no run catches a wrong one

   When a check fails, the agent traces the failure to the guide row behind it before it changes any code.
4. Read the agent's report in `.artifacts/behat-steps-migration/report.md`. It gives the result of each check and lists what's left for you: every `TODO(behat-steps 4.x)` comment it added for a question you left unanswered, every change to review and every open question.

Confirm that the migration is complete: you've resolved every item the report lists, and your suite passes. The agent doesn't commit, so review `git status` and `git diff` and commit the changes yourself. If your agent saved the skill, commit `.claude/skills/migrate-behat-steps/SKILL.md` to keep the migration repeatable, or delete it. `.artifacts/behat-steps-migration/` stays out of version control, so delete it when you no longer need the plan and the report.

## Migrate by hand

To migrate by hand:

1. Create a branch from a clean working tree, so `git status` and `git diff` show the whole migration.
2. Read [How to read this guide](../../MIGRATION.md#how-to-read-this-guide): every table follows the same conventions, and they decide how a row applies to your project.
3. Work through the guide's sections in order, starting with [Requirements](../../MIGRATION.md#requirements), which also covers removing the Drupal Extension from a Drupal project before Composer installs 4.0.

Confirm that the migration is complete with the same checks the agent runs, which step 3 of [Migrate with an AI coding agent](#migrate-with-an-ai-coding-agent) lists, through your project's own commands.

## Update your AI agent's reference

If your project's `CLAUDE.md` or `AGENTS.md` points its agent at `vendor/drevops/behat-steps/STEPS.md`, as the 3.x README suggested, that path still works in 4.0. 4.0 adds `vendor/drevops/behat-steps/HELPERS.md`, the helpers your own step definitions can call, and the `create-behat-step` skill, which writes a step of your own on them, so point the agent at both: add a line for each, or replace your snippet with the full one in [Writing tests with AI assistants](guides/ai-assistants.md). The migration guide doesn't cover these files, so update them yourself.
