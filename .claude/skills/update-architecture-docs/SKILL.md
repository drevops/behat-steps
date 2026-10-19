---
name: update-architecture-docs
description: Generate or update the architecture documentation in docs/content/architecture/ - the logical architecture, class inheritance and data flow diagrams, drawn in draw.io through the drawio-skill, and the walkthrough around them. Use on "update architecture docs", "generate architecture documentation", "regenerate architecture docs", "redraw the diagrams", or after any structural change to the codebase.
---

# Update architecture docs

`docs/content/architecture/README.md` explains how this project works in 3 views, each 1 diagram with a short walkthrough:

- **Logical architecture** (`logical-architecture.drawio`) - the layers and the components inside them, and which way every dependency points, from the consuming project down to the site under test, with the repository tooling beside them.
- **Class inheritance** (`class-inheritance.drawio`) - the real inheritance trees: the contexts, the backends and their capabilities, the field handlers, the creation aliases, the browser adapters, the creation hooks and the exceptions.
- **Data flow** (`data-flow.drawio`) - what runs when a suite starts, when a scenario starts, on each step and when a scenario ends.

The page doubles as the Architecture section of the documentation site, so keep it plain Markdown and link a page or a repository file by its relative path, as the rest of `docs/content/` does. It is a short walkthrough, not a reference: `STEPS.md`, `HELPERS.md` and the generated pages list the steps and helpers, and `CONTRIBUTING.md` holds the rules. Never repeat either here.

All content is derived from the actual source code: sources, entry scripts, manifests and service definitions. Never derive content from wishes, plans or design documents. If the documentation and the code disagree, the code wins.

## The drawio-skill

The diagrams are drawn with the [drawio-skill](https://github.com/Agents365-ai/drawio-skill) by Agents365-ai, MIT licensed. A problem with the drawing itself, such as a layout bug or a shape the skill gets wrong, belongs in that repository's issues, and fixes are contributed there.

The skill sets `disable-model-invocation: true`, so the Skill tool can't load it. [scripts/draw-diagram.php](../../../scripts/draw-diagram.php) runs it instead: it starts a separate, unattended Claude Code session whose prompt opens with the `/drawio-skill` command, which a typed command still loads. The session writes the `.drawio` file and validates it with the skill's tools, and the script then exports the SVG beside it and a review PNG to `.artifacts/tmp/diagrams/`. Never write a diagram's XML or export an SVG by hand; run the script.

### Prerequisites

Don't probe for them. Run the script, and it names whatever is missing along with its install command.

- The skill: `npx skills add Agents365-ai/drawio-skill -g`. The script looks for it in `.claude/skills/drawio-skill` of this repository, then of the home directory, where Claude Code loads skills from.
- draw.io desktop, which exports the diagrams: `brew install --cask drawio` on macOS, or a release from [jgraph/drawio-desktop](https://github.com/jgraph/drawio-desktop/releases). The script looks for `drawio` on the `PATH`, then for the macOS app, and `DRAWIO_BIN` names any other binary.
- Python 3, which runs the skill's validator.
- The `claude` command, logged in.

When one is missing, stop before drawing, say which diagrams were not redrawn, and give its install command. Prose edits may still proceed.

## Task A: first generation

1. Analyze the codebase from the sources under "Sources to trace from".
2. Write 1 brief per diagram to `.artifacts/tmp/briefs/<diagram>.md`: the title and a 1-line subtitle, every group and node with its id, text, role, position and size, every edge with its label and, where a straight line won't do, its route, and the legend with its position. Spell code names exactly as the source does, and state only counts the code confirms. A brief says what a diagram shows and where. [references/house-style.md](references/house-style.md) says how every diagram looks, so a brief never repeats it.

   Pin the layout on a grid, such as 240 x 70 boxes 280 px apart in columns and 120 px apart in rows. A session left to plan the layout of a large diagram reasons through every coordinate before it writes anything, and once that reasoning runs past the output token limit, Claude Code discards the response and the session starts the plan again. Given every position, the session transcribes the brief, validates it and fixes what its draft shows. A wide base class drawn as a bar across its children's columns lets every inheritance edge run straight up.
3. Draw the 3 diagrams in parallel, 1 background command each, from the repository root:

   ```bash
   php scripts/draw-diagram.php --brief=.claude/skills/update-architecture-docs/references/house-style.md --brief=.artifacts/tmp/briefs/<diagram>.md docs/content/architecture/<diagram>.drawio
   ```

   A session with a pinned brief runs for about 15 minutes. The command prints the session's progress, with an estimate of its reasoning every 10000 tokens, then its report and the paths of the SVG and the review PNG. A session that reasons past 50000 tokens before its first write is planning a layout the brief left open: stop it, and pin that part of the layout.
4. Look at every review PNG as "Verification" describes. Redraw a diagram that fails it with a brief that names what to fix.
5. Write `docs/content/architecture/README.md` as "The page" describes.

## Task B: update after a structural change

1. Identify the diagrams and the prose the change affects.
2. Re-trace them from the current code.
3. Write a brief for each affected diagram naming only what changes: the nodes, edges and labels to add, move or remove, with the position and size of every cell it adds or moves. Run the command from Task A with it. The diagram exists, so the session edits it in place and keeps the layout of everything the brief leaves alone.
4. Update the prose so the diagrams and the prose agree.

A change is structural when it moves, adds, or removes a component or alters a flow between components: a new layer or namespace, a new backend or capability interface, a change to what `BehatStepsExtension` wires up, a change to how a context composes traits, a change to an inheritance tree the class diagram shows, a change to the scenario lifecycle, or a change to what the repository tooling reads or writes: `docs.php`, the fixture site provisioning or the test harness. Adding a step to an existing trait, renaming step text, or fixing an assertion is not structural.

## The page

- Open with what the 3 views are, the maintenance note naming this skill and the drawio-skill, and pointers to the step, helper and configuration references.
- Give each view a section, in the order logical architecture, class inheritance, data flow, so their anchors stay `#logical-architecture`, `#class-inheritance` and `#data-flow`: other pages link them. Each section embeds its SVG, wrapped in a link to itself as `[![Alt text](diagram.svg)](diagram.svg)`, because the page draws a wide diagram at the width of its column and the link opens it at full size. A few short paragraphs or a list then walk through the diagram, naming what it shows without restating every label.
- Put 1-to-1 relationships in Markdown tables rather than in a diagram: an interface and the 1 service implementing it, or a class and the Behat, Mink or Symfony type it extends.
- End with the table of diagram sources and how to edit a source by hand.
- Every diagram is a draw.io source with its SVG. Never add a diagram in any other format, ASCII art included.

## Sources to trace from

The project is 3 layers, and the boundary between them is the architecture. Read them in this order:

- `src/Backend/` - the backend layer. `BackendInterface`, `BlackboxBackend`, the capability interfaces any backend may provide in `Backend/Capability/` and the random generator in `Backend/Random/` at its root; under `Backend/Drupal/` the Drupal and Drush backends, the capabilities only a Drupal backend provides, and the `Core/` field-handling bridge. It references nothing from Behat or Mink, and its root references nothing from Drupal.
- `src/Extension/` - the extension layer. `ServiceContainer/BehatStepsExtension.php` and the service definitions under `ServiceContainer/config/` and `Drupal/ServiceContainer/config/` for the wiring, `Context/` for the contracts the initializer injects through, `Listener/` for what runs as a scenario starts, `Registry/` for the backend, user and scenario tag registries, `Config/` and `Prerequisite/` for trait options and requirements, `Auth/` for the authenticators, `Browser/` for the browser capabilities and adapters, `Http/` for the HTTP clients, `Hook/` for the entity and user creation hooks; under `Extension/Drupal/` the Drupal backend provider and the node, term and language creation hooks.
- `src/WebRawContext.php`, `src/WebContext.php` and `src/DrupalContext.php` - the 3 contexts at the package root, the 1 place that reaches every layer.
- `src/Steps/Web/` and `src/Steps/Drupal/` - the vocabulary. Each trait's `@phpstan-require-extends` annotation says what it needs from its host, and its hooks show what it does as a scenario starts and ends.
- `src/Steps/Helper/` - the step-free traits a step trait and a context both compose, split into `Web/` and `Drupal/`. `Drupal/EntityLifecycleTrait.php` and `Drupal/AuthTrait.php` hold the creation and the cleanup the data flow shows.
- `src/Steps/Exception/AssertionException.php` and `src/Backend/Exception/` - what a failing step throws.
- `composer.json` - the published package surface and the PSR-4 map.
- `docs.php`, `docs/`, `scripts/provision.php`, `tests/` and `scripts/lint-layers.php` - the repository tooling the logical view shows beside the library.

## Verification

1. Run `php scripts/draw-diagram.php --check docs/content/architecture`. It fails a diagram with no SVG, an SVG exported from an older version of its source, a source with more than 1 page or no background, and an SVG with no source. `DrawDiagramTest` runs the same check in the unit suite, so CI fails a drifted render too.
2. Look at every review PNG in `.artifacts/tmp/diagrams/`. A diagram that validates can still be unreadable: look for overlapping shapes, clipped labels, edges crossing nodes, an edge pointing the opposite way to the prose, a missing legend, and a name the code doesn't have.
3. Confirm that every class, trait, method and count a diagram names exists in the code, and that the prose around each diagram states only what the diagram and the code show.
4. Run `npm run build --prefix docs` and `npm run spellcheck --prefix docs`. When either reports `command not found`, install the site's dependencies with `npm ci --ignore-scripts --prefix docs`, as CI does, and run it again. The build fails on a broken page link or anchor and on a repository link to a file that doesn't exist. A word the spell check rejects in the page goes into the `words` list in `docs/cspell.json`, because a `cspell:ignore` comment in the page wouldn't survive the next regeneration.
