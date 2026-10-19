---
title: Architecture
description: How Behat Steps works inside, in 3 views, each a diagram with a short walkthrough. The logical architecture shows the parts, the class inheritance what extends what, and the data flow what runs when.
---

# Architecture

These pages show how Behat Steps works inside, in 3 views. Each view is 1 diagram with a short walkthrough:

- [Logical architecture](logical-architecture.md) shows the parts and which way they depend on each other.
- [Class inheritance](class-inheritance.md) shows what extends what.
- [Data flow](data-flow.md) shows what runs when.

You don't need any of this to write scenarios. It's here for when you extend the library, or want to know why a step behaves the way it does.

An AI agent maintains these pages and their diagrams through the `update-architecture-docs` skill in [.claude/skills/update-architecture-docs/SKILL.md](../../../.claude/skills/update-architecture-docs/SKILL.md), which draws them with the [drawio-skill](https://github.com/Agents365-ai/drawio-skill). Everything here is traced from the source code, and where the 2 disagree, the code wins.

Looking for a reference instead? [Web steps](../web-steps/README.md) and [Drupal steps](../drupal-steps/README.md) list the steps, [Web helpers](../web-helpers/README.md) and [Drupal helpers](../drupal-helpers/README.md) list the helpers behind them, and [Configuration](../guides/configuration.md) lists the options and tags.

## Diagram sources

Each diagram is a draw.io source beside the SVG its page embeds. Both are committed, so a reviewer sees the source diff and the rendered result.

| Diagram | Source | Render |
| --- | --- | --- |
| [Logical architecture](logical-architecture.md) | `logical-architecture.drawio` | [`logical-architecture.svg`](logical-architecture.svg) |
| [Class inheritance](class-inheritance.md) | `class-inheritance.drawio` | [`class-inheritance.svg`](class-inheritance.svg) |
| [Data flow](data-flow.md) | `data-flow.drawio` | [`data-flow.svg`](data-flow.svg) |

To change a diagram by hand, open its source in [draw.io](https://www.drawio.com/), save it, then export every SVG again from the repository root:

```bash
php scripts/draw-diagram.php --export docs/content/architecture
```

Each SVG records the hash of the source it came from, and `php scripts/draw-diagram.php --check docs/content/architecture`, which the unit suite runs too, fails an SVG exported from an older source.

After a structural change, such as a new layer or namespace, a new backend or capability, or a change to an inheritance tree or to the scenario lifecycle, ask the AI agent to "update architecture docs". It re-traces the affected diagrams from the current code and redraws them through the `update-architecture-docs` skill.
