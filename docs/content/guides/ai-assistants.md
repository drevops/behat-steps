---
title: Writing tests with AI assistants
sidebar_position: 7
description: Point an AI coding agent at the step and helper references that ship with the package, so it writes scenarios from steps that exist, and at the skill that writes steps of your own on the helpers.
---

# Writing tests with AI assistants

An AI coding agent writes better scenarios from the steps your suite registers than from steps it invents. Behat prints that list, and 2 references that ship with the Behat Steps package fill in the rest. When no step says what a scenario needs, a skill that ships beside them has the agent write a step of your own. This page gives you the snippet that points your agent at all of it. The files are plain Markdown, so an agent in your project reads them from `vendor/` at the version you installed:

| File | Holds |
| --- | --- |
| `vendor/drevops/behat-steps/STEPS.md` | Every step, with its description, its options and an example. The same content as [Web steps](../web-steps/README.md) and [Drupal steps](../drupal-steps/README.md), on 1 page |
| `vendor/drevops/behat-steps/HELPERS.md` | Every helper your own step definitions can call, with its signature. It's the only helper reference: this site doesn't list the helpers |
| `vendor/drevops/behat-steps/skills/create-behat-step/SKILL.md` | The `create-behat-step` skill, which writes a step of your own on those helpers, as [Write a step of your own](#write-a-step-of-your-own) describes |

## Point your agent at them

Copy this into your project's `CLAUDE.md` or `AGENTS.md` file:

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
To add a step of your own, follow: vendor/drevops/behat-steps/skills/create-behat-step/SKILL.md
```

`behat --definitions=i` lists every step your suite registers, Mink's and your project's included, so it's the list to write scenarios from. The 2 references add what the list leaves out: an example for each step this package ships, the options that change it and the helpers a step definition of your own can call. Both open with a link to the skill, so an agent that reads either one finds it too.

## Write a step of your own

The `create-behat-step` skill takes a description of what a step should do, in plain English, and writes the step in a trait of your own that your `FeatureContext` uses, the shape [Contexts](contexts.md#your-own-steps-in-traits-of-your-own) shows. It builds the step on the public helpers in `HELPERS.md`, read from your `vendor/`, so the step calls the helpers of the release you installed.

The agent works through these steps:

1. It finds your `FeatureContext`, your own step traits and the commands your project runs Behat and its linters with.
2. It looks for registered steps that already do the job, shows you any it finds and asks whether you still want a step in your own words.
3. It writes the step text, picks a helper for each part of the job and writes the step in your trait for that area of the site, or in a new one.
4. It adds the trait to `FeatureContext`, and a scenario that uses the step.
5. It checks the result with your linters, `behat --definitions`, `behat --dry-run` and a run of the new scenario, and runs a `Then` once more with a value it should reject. Then it tells you which helpers the step calls and the choices it made.

The step calls only public helpers and Mink's own API, never a protected method, a shipped step's method or Drupal's API, so it keeps working through minor releases and on any backend that provides what it needs. The agent doesn't commit.

With the snippet above, your agent follows the skill whenever a scenario needs a step that doesn't exist. To have your agent offer the skill by name, or to run it with a description yourself, install it as a skill of your agent.

### Install it as a skill

Link the skill into the skills directory your agent reads, from your project's root. For Claude Code, that's `.claude/skills/`:

```bash
mkdir -p .claude/skills
ln -s ../../vendor/drevops/behat-steps/skills/create-behat-step .claude/skills/create-behat-step
```

Many other agents, such as Codex, Cursor, GitHub Copilot and Gemini CLI, read `.agents/skills/` instead, so link the skill there the same way. Commit the link: it points into `vendor/`, so the skill follows every `composer update` to the release you installed. On a system without symbolic links, copy the directory instead, and copy it again after you update the package.

The [skills](https://github.com/vercel-labs/skills) command-line tool installs it for each agent you pick, from your installed copy:

```bash
npx skills add ./vendor/drevops/behat-steps --skill create-behat-step
```

Then ask your agent for a step in your own words, or run the skill with a description. In Claude Code:

```text
/create-behat-step a Then step that checks that the page with a title is authored by a user
```
