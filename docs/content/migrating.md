---
title: Migrating from 3.x
sidebar_position: 8
description: Move a project from Behat Steps 3.14 to 4.0 with the migration guide, by hand or with the migrate-behat-steps skill for AI coding agents.
---

# Migrating from 3.x

[MIGRATION.md](../../MIGRATION.md) takes a project from 3.14 to 4.0 in the order an upgrade runs into each change: Composer requirements, Behat configuration, context classes, feature files, then the PHP your project writes against the package. Its tables list every name a 3.14 project used that 4.0 changes or removes, and what replaces it, whether the name came from this package or from the Drupal Extension and Drupal Driver that its Drupal traits built on. If the tables don't list a step, its text didn't change.

If your project is on an earlier 3.x release, update it to 3.14 first.

## Migrate with an AI coding agent

An AI coding agent can apply the guide for you with the [`migrate-behat-steps`](../../.claude/skills/migrate-behat-steps/SKILL.md) skill: it reads the guide, plans every change the guide calls for in your project, and asks before it changes anything. Once you say yes, it applies the plan and runs your checks, then reports what's left for you.

Composer installs this package without the repository's `.claude/` directory, so the skill isn't in `vendor/`. Paste this into your agent from your project's root, on a clean branch, so `git diff` shows the whole migration:

```text
Fetch
https://raw.githubusercontent.com/drevops/behat-steps/main/.claude/skills/migrate-behat-steps/SKILL.md
and follow it to migrate this project from drevops/behat-steps 3.x to 4.x.
If you support skills, save it as .claude/skills/migrate-behat-steps/SKILL.md
first, so the migration can run again.
```

## Migrate by hand

Read [How to read this guide](../../MIGRATION.md#how-to-read-this-guide) first: every table follows the same conventions, and they decide how a row applies to your project. Then work through the sections in order, starting with [Requirements](../../MIGRATION.md#requirements), where you remove the Drupal Extension before Composer installs 4.0.
