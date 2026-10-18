---
title: Writing tests with AI assistants
sidebar_position: 5
description: Point an AI coding agent at the step and helper references that ship with the package, so it writes scenarios from steps that exist.
---

# Writing tests with AI assistants

An AI coding agent writes better scenarios from the steps your suite registers than from steps it imagines. Behat prints that list, and 2 references that ship with the Behat Steps package fill in the rest. They're plain Markdown, so an agent in your project can read them from `vendor/` at the version you installed:

| File | Holds |
| --- | --- |
| `vendor/drevops/behat-steps/docs/content/steps.md` | Every step, with its description, its options and an example. The same page as [Steps](../steps.md) |
| `vendor/drevops/behat-steps/docs/content/helpers.md` | Every helper your own step definitions can call, with its signature. The same page as [Helpers](../helpers.md) |

## Point your agent at them

Copy this into your project's `CLAUDE.md` or `AGENTS.md` file:

```
## Writing Behat Tests

Available step definitions are listed in `.behat-steps.txt`.
Read this file before writing or modifying Behat tests.
Use only step patterns from this file. Do not invent steps.

If `.behat-steps.txt` does not exist or is outdated, regenerate it:

    ./vendor/bin/behat --definitions=i > .behat-steps.txt

Regenerate after adding new step traits or updating dependencies.

For detailed examples, see: vendor/drevops/behat-steps/docs/content/steps.md
For the helpers behind the steps, see: vendor/drevops/behat-steps/docs/content/helpers.md
```

`behat --definitions=i` lists every step your suite registers, Mink's and your project's included, so it's the list to write scenarios from. The 2 references add what the list leaves out: an example for each step this package ships, the options that change it, and the helpers a step definition of your own can call.
