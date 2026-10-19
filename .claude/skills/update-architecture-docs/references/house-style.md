# House style

These rules apply to every diagram in `docs/content/architecture/`, so the diagrams read as a set. A diagram's own brief says what it shows; this file says how it looks.

## Canvas

- 1 page, with `background="#ffffff"` and `page="0"` on the `mxGraphModel`.
- Landscape, and no wider than 2400 px.
- A title at the top left in 22 px bold, "Behat Steps: <view>", and below it 1 line in 13 px `#546E7A` saying how to read the diagram.
- A legend in the bottom right corner: 1 swatch per color in use and 1 sample per edge kind in use, each with a short label.

## Text

- Helvetica everywhere: 13 px in a node, 15 px bold in a container header, 11 px on an edge.
- Code names exactly as the source spells them: classes, traits, interfaces and methods in their own case, namespaces with `\`.
- A node holds at most 3 lines: its name in bold, then at most 2 short lines saying what it does.
- No em dash and no en dash: use a hyphen, a colon or a new line.
- Every label fits inside its shape with at least 8 px of padding. Widen the shape rather than shrink the text.

## Colors

Each role has a fill and a stroke. A container takes its role's fill with its role's stroke. A node inside a container is white with its role's stroke, so it stands out from the container around it.

| Role | Fill | Stroke |
| --- | --- | --- |
| Backend layer, `src/Backend` | `#E8F5E9` | `#2E7D32` |
| Step vocabulary, `src/Steps` | `#E3F2FD` | `#1565C0` |
| Extension layer, `src/Extension` | `#F3E5F5` | `#6A1B9A` |
| Contexts at the package root | `#FFF8E1` | `#F9A825` |
| Consuming project and documentation | `#FFF3E0` | `#EF6C00` |
| External: Behat, Mink, Symfony, PHP, browsers, the site | `#ECEFF1` | `#546E7A`, dashed |
| Tests and exceptions | `#FFEBEE` | `#C62828` |

## Shapes

- A node is a rounded rectangle: `rounded=1;arcSize=12;whiteSpace=wrap;html=1;strokeWidth=1.5;`.
- A group is a swimlane container: `swimlane;startSize=30;rounded=1;arcSize=6;html=1;` with a bold header.
- A type in a class diagram is a rounded rectangle holding its name and no members. A stereotype goes on the first line in 11 px italics: `«interface»`, `«abstract»` or `«trait»`.

## Edges

- Orthogonal routing: `edgeStyle=orthogonalEdgeStyle;rounded=1;html=1;strokeColor=#455A64;strokeWidth=1.5;`.
- A call or a dependency: solid, with a filled arrow (`endArrow=block;endFill=1;`).
- Inheritance: solid, with a hollow triangle at the parent (`endArrow=block;endFill=0;endSize=12;`).
- An interface implementation: dashed, with a hollow triangle at the interface.
- Trait composition: dashed, with an open arrow at the trait (`endArrow=open;`), labeled `«use»`.
- Data coming back: dashed `#78909C`, with an open arrow.
- An edge label sits on white (`labelBackgroundColor=#ffffff;`) and never on another edge or shape.
- No edge crosses a node it doesn't connect, and edges leaving 1 side of a node are spread along that side.

## Layout

- At least 40 px between nodes and 60 px between containers.
- Nodes sit on a 10 px grid, and siblings share 1 size.
- Every cell has a readable, stable id such as `ctx-web-raw` or `be-drupal`. Only the 2 root cells use `0` and `1`.
