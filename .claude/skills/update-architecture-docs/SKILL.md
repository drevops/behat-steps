---
name: update-architecture-docs
description: Generate or update the architecture documentation and its PlantUML diagrams in docs/content/architecture/. Use on "update architecture docs", "generate architecture documentation", "regenerate architecture docs", "render diagrams", or after any structural change to the codebase.
---

# Update architecture docs

`docs/content/architecture/README.md` is a narrative walkthrough of how this project works: what the main components are and how the primary data and control flows run through them, with diagrams embedded at the points in the story they support. It is a walkthrough, not an index.

The walkthrough doubles as the Architecture section of the documentation site, so keep it plain Markdown and link a page or a repository file by its relative path, as the rest of `docs/content/` does.

`docs/content/architecture/` also holds the diagram sources as PlantUML (`.puml`) plus their rendered light `.svg`. Both are committed, so a reviewer sees the source diff and the rendered result.

`STEPS.md` and the pages under `docs/content/web-steps/` and `docs/content/drupal-steps/` are the index of the step vocabulary, and `docs.php` generates them. Never duplicate their per-step listing here: this document explains how a step reaches a browser or a Drupal API, not what each step does.

All content is derived from the actual source code: sources, entry scripts, manifests, and CI workflows. Never derive content from wishes, plans, or design documents. If the documentation and the code disagree, the code wins.

## Prerequisite

PlantUML must be on the `PATH`. Do not probe for it - run the render command and let it fail:

```bash
plantuml -tsvg docs/content/architecture/*.puml
```

If it reports that the command is missing, stop before rendering, say explicitly that the SVGs were not regenerated, and give the install instructions: `brew install plantuml` on macOS, `apt-get install plantuml` on Debian and Ubuntu. PlantUML is a Java application and needs a JRE or JDK 11 or later, which macOS does not ship; the Homebrew formula pulls OpenJDK in as a dependency, and `java -version` confirms what is present. Graphviz is required for class, component and activity diagrams, and Homebrew pulls that in too. Prose and `.puml` edits may still proceed.

## Task A: first generation

1. Analyze the codebase: entry points, sources, manifests, and workflows.
2. Identify the main components and the 1-3 primary data or control flows.
3. Draw the structure. Two diagrams are mandatory, and both are UML:
   - **A component diagram** (`architecture.puml`) - the packages and the runtime pieces around them, with the dependencies between them.
   - **A class diagram** (`class-*.puml`) - the real types: the trait inventory, the inheritance chain the context sits on, the `<<trait>>` stereotype, and the exceptions a failing step throws. Split it in two when one diagram would have to choose between breadth and member detail: an inventory diagram naming every type, and a detail diagram carrying members for a representative few.
4. Draw one sequence diagram per primary flow (`dataflow-<flow>.puml`).
5. Render every source to SVG, then confirm each `.svg` changed.
6. Write `docs/content/architecture/README.md` as a walkthrough that embeds each SVG where the prose discusses it, and keep the source index table near the top current. Wrap each embedded SVG in a link to itself, as `[![Alt text](diagram.svg)](diagram.svg)`, and link each SVG in the index table the same way, because the page draws a wide diagram at the width of its column and the link opens it at full size.

## Task B: update after a structural change

1. Identify the components and flows the change affected.
2. Re-trace the affected `.puml` sources from the current code.
3. Re-render every SVG in the same pass, so the visuals and the prose can never drift apart.
4. Update the surrounding prose so the visuals and the prose agree.
5. Add any new diagram to the index table in `docs/content/architecture/README.md`, linking its SVG there and where the prose embeds it, as Task A describes.

A change is structural when it moves, adds, or removes a component or alters a flow between components: a new layer or namespace, a new backend or capability interface, a change to how a context composes the scenario lifecycle, a change to what `BehatStepsExtension` wires up, a change to how `docs.php` discovers or renders steps, a change to how the fixture site is provisioned, a change to the nested-Behat harness, or a change to the CI matrix. Adding a step to an existing trait, renaming step text, or fixing an assertion is not structural.

## Sources to trace from

The project is 3 layers, and the boundary between them is the architecture. Read them in this order:

- `src/Backend/` - the backend layer. `BackendInterface`, `BlackboxBackend`, the capability interfaces any backend may provide in `Backend/Capability/` and the random generator in `Backend/Random/` at its root; under `Backend/Drupal/` the Drupal and Drush backends, the capabilities only a Drupal backend provides, and the `Core/` field-handling bridge. It references nothing from Behat or Mink, and its root references nothing from Drupal.
- `src/Extension/` - the extension layer. `ServiceContainer/BehatStepsExtension.php` for the wiring and `ServiceContainer/BackendProviderInterface.php` for what a backend provider contributes, `Context/` for the contracts the initializer injects through, `Registry/` for the backend, user and scenario tag registries, `Auth/` for the authenticators, `Browser/` for the browser capabilities and adapters, `Http/` for the HTTP clients, `Hook/` for the entity and user creation hooks; under `Extension/Drupal/` the Drupal backend provider and the node, term and language creation hooks. Its root references nothing from Drupal, and nothing under either `Drupal` sub-namespace.
- `src/WebRawContext.php`, `src/WebContext.php` and `src/DrupalContext.php` - the 3 contexts at the package root, the 1 place that reaches every layer.
- `src/Steps/Web/` and `src/Steps/Drupal/` - the vocabulary. Each trait's `@phpstan-require-extends` annotation says what it needs from its host.
- `src/Steps/Helper/` - the step-free traits a step trait and a context both compose, split into `Web/` and `Drupal/`.
- `src/Steps/Exception/AssertionException.php` - what a session-less trait throws.
- `composer.json` - the published package surface and the PSR-4 map.
- `docs.php` - the documentation generator; `STEPS_DIRECTORY` is what it scans.
- `behat.php` - the suites, contexts, profiles, and the `BehatStepsExtension` settings.
- `tests/behat/bootstrap/` - `FeatureContext` and the nested-Behat harness in `BehatCliTrait`.
- `scripts/lint-layers.php` and `scripts/lint-traits.php` - the enforced layer boundaries and trait composition rules. `scripts/provision.php` and `scripts/merge-coverage.php` - fixture-site provisioning and coverage merging.
- `docs/docusaurus.config.js`, `docs/sidebars.js`, `docs/src/remark/repositoryLinks.js`, `docs/src/theme/`, `docs/src/components/`, `docs/package.json`, `docs/cspell.json`, `docs/playwright.config.js`, `docs/tests/` and `.gitattributes` - the documentation site, how it links repository files, its checks, and what the Composer package ships.
- `.ahoy.yml`, `.github/workflows/test.yml` and `.github/workflows/release-docs.yml` - the developer, CI and publishing entry points.

## Diagram conventions

Keep the header block identical across every source so the diagrams read as a set, and put the traced-from annotation in `'` comments at the top:

```plantuml
@startuml
' drevops/behat-steps - <what this diagram shows>.
' Traced from <the files it was read from>.
' Regenerate every SVG with:  plantuml -tsvg docs/content/architecture/*.puml
!theme plain
skinparam backgroundColor white
skinparam defaultFontName Helvetica
skinparam shadowing false
title <Component architecture | Class structure: ... | Data flow: ...>
@enduml
```

- Package colours are keyed by role: backend layer green (`#E8F5E9`), step vocabulary blue (`#E3F2FD`), extension layer and the helper traits purple (`#F3E5F5`), consuming project and documentation orange (`#FFF3E0`), runtime and targets slate (`#ECEFF1`), test harness and thrown exceptions red (`#FFEBEE`).
- Sequence diagrams use solid arrows (`->`) for the forward path and dashed (`-->`) for returns.
- Class diagrams set `skinparam classAttributeIconSize 0` and stereotype every PHP trait `<<trait>>`.
- A class diagram with many sibling types lays out unreadably wide by default. Chain them into columns with `-[hidden]down-` links and check the rendered aspect ratio before committing.
- **Escape a leading pair of `-` in a label.** PlantUML's Creole renders `--` as strikethrough, so a CLI flag is written `~--fail-on-change`.
- **A `#` at the start of a label line becomes a numbered list.** Reword rather than opening a label with a PHP attribute like `#[AfterScenario]`.

## Verification

Before finishing, confirm that every path named in a traced-from comment exists, that the prose around each diagram states only what those files show, and that every `.puml` has a matching `.svg` listed in the index table.

Then look at every diagram - one that renders without error can still be unreadably wide, and a Creole slip only shows up in the render:

```bash
PLANTUML_LIMIT_SIZE=8192 plantuml -tpng -o ../../../.artifacts/tmp docs/content/architecture/*.puml
```

PlantUML resolves a relative `-o` path against each **input** file, not the working directory, so `../../../` climbs out of `docs/content/architecture/` to the repository root. It creates the directory if it is missing. The default size limit clips a diagram wider than 4096 px, so `PLANTUML_LIMIT_SIZE=8192` raises it. Open each generated PNG in an image viewer before committing.

Finally, run `npm run build --prefix docs` and `npm run spellcheck --prefix docs`. When either reports `command not found`, install the site's dependencies with `npm ci --ignore-scripts --prefix docs`, as CI does, and run it again. The build fails on a broken page link or anchor and on a repository link to a file that doesn't exist. A word the spell check rejects in the walkthrough goes into the `words` list in `docs/cspell.json`, because a `cspell:ignore` comment in the walkthrough wouldn't survive the next regeneration.
