---
title: Writing tests with AI assistants
sidebar_position: 8
description: Point an AI coding agent at the step and helper references that ship with the package, so it writes scenarios from steps that exist, and at the skill that writes steps of your own on the helpers.
---

# Writing tests with AI assistants

An AI coding agent writes better scenarios from the steps your suite registers than from steps it invents. Behat prints that list. The Behat Steps package ships 2 references that add examples, options and helpers to it. When no step says what a scenario needs, the agent follows a skill that ships beside them to write a step of your own. This page holds the snippet that points your agent at all of it. The files are plain Markdown, so an agent in your project reads them from `vendor/` at the version you installed:

| File | Holds |
| --- | --- |
| `vendor/drevops/behat-steps/STEPS.md` | Every step, with its description, its options and an example. The same content as [Web steps](../web-steps/README.md) and [Drupal steps](../drupal-steps/README.md), on 1 page |
| `vendor/drevops/behat-steps/HELPERS.md` | Every helper your own step definitions can call, with its signature. It's the only helper reference: this site doesn't list the helpers |
| `vendor/drevops/behat-steps/skills/create-behat-step/SKILL.md` | The `create-behat-step` skill, which your agent follows to write a step of your own on those helpers, as [Write a step of your own](#write-a-step-of-your-own) describes |

## Point your agent at them

Copy the following into your project's `CLAUDE.md` or `AGENTS.md` file:

```markdown
## Writing Behat Tests

Available step definitions are listed in `.behat-steps.txt`.
Read this file before writing or modifying Behat tests.
Use only step patterns from this file. Do not invent steps.

If `.behat-steps.txt` does not exist or is outdated, regenerate it:

    ./vendor/bin/behat --definitions=i > .behat-steps.txt

Regenerate after adding new step traits or updating dependencies.

For detailed examples, see: vendor/drevops/behat-steps/STEPS.md
For the helpers behind the steps, see: vendor/drevops/behat-steps/HELPERS.md
To add a step of your own, follow:
vendor/drevops/behat-steps/skills/create-behat-step/SKILL.md
```

`behat --definitions=i` lists every step your suite registers, Mink's and your project's included, so it's the list to write scenarios from. The 2 references add an example for each step this package ships and the options that change that step. They also list the helpers a step definition of your own can call. Both open with a link to the skill, so an agent that reads either one finds the skill too.

## Write a step of your own

With the `create-behat-step` skill, your agent turns a plain-English description of a step into code. The agent writes the step in a trait of your own that your `FeatureContext` uses, the shape [Contexts](contexts.md#your-own-steps-in-traits-of-your-own) shows. It builds the step on the public helpers that the `HELPERS.md` in your `vendor/` lists, so the step calls the helpers of the release you installed.

The agent works through these steps:

1. It finds your `FeatureContext`, your own step traits and the commands your project runs Behat and its linters with.
2. It looks for registered steps that already do the job, shows you any it finds and asks whether you still want a step in your own words.
3. It writes the step text and picks a helper for each part of the job. It writes the step in your trait for that area of the site, or in a new trait.
4. It adds the trait to `FeatureContext`, and a scenario that uses the step.
5. It checks the result with your linters, `behat --definitions`, `behat --dry-run` and a run of the new scenario. For a `Then` step, it runs the scenario again with a value the step should reject.
6. It reports which helpers the step calls and the choices it made.

The step calls only public helpers and Mink's own API, never a protected method, a shipped step's method or Drupal's API. So it keeps working through minor releases and on any backend that provides what it needs. The agent commits nothing.

With the snippet in your `CLAUDE.md` or `AGENTS.md`, your agent follows the skill whenever a scenario needs a step that doesn't exist. To have your agent offer the skill by name, or to run it with a description yourself, install it as a skill of your agent.

### Install it as a skill

From your project's root, link the skill into the skills directory your agent reads. For Claude Code, that's `.claude/skills/`:

```bash
mkdir -p .claude/skills
ln -s ../../vendor/drevops/behat-steps/skills/create-behat-step \
  .claude/skills/create-behat-step
```

For another agent, link the skill into the skills directory that agent reads, the same way. Commit the link: it points into `vendor/`, so after every `composer update` it resolves to the skill of the release you installed. On a system without symbolic links, copy the directory in place of the link. Copy it again after every package update.

Then ask your agent for a step in your own words, or run the skill with a description. In Claude Code:

```text
/create-behat-step a Then step that checks that the page with a title is authored by a user
```
