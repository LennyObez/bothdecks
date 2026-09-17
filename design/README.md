# Design

One source of truth for colour, type, space, motion and haptics, and one generator that turns it into the
three platform themes. A value is defined once and never restated per platform.

The identity is defined from first principles rather than inherited from the framework the server is built
on. The framework supplies the token *mechanism*; none of its palettes, typefaces or scales carry over.

---

## The source

[`tokens/tokens.json`](tokens/tokens.json), in the [W3C Design Tokens](https://www.designtokens.org/) format.

It is the only file in the repository allowed to contain a colour value. A semantic role does not repeat a
number, it points at the palette entry that holds it:

```json
"theme": { "light": { "brand": { "fill": { "$type": "color", "$value": "{color.brand.600}" } } } }
```

That indirection is the whole point. Changing a palette entry changes every role that names it, on all three
platforms, in one edit, and a role that carries a colour of its own instead of a reference fails the gate. A
role may also point at another role, which is how the source says "deliberately the same as this one": the
chain is followed and has to end at the palette.

Three conventions the format itself does not carry:

- **What the format does not define is namespaced, not invented.** A spring is six numbers and the format has
  no composite for it, so the source writes each as its own typed token under `motion.spring.<name>` and the
  generator folds them back into one spring. Haptics and the reduced-motion map are not values at all, so
  they live in root extensions, `$extensions."bothdecks.haptic"` and `$extensions."bothdecks.reducedMotion"`.
  [`tools/src/Manifest.php`](tools/src/Manifest.php) says where each group is read from, and the gate refuses
  an extension key that is not namespaced.
- **A style states its line height as a multiple of its size**, not as a length, so that it keeps scaling when
  a reader enlarges the text. Where the source also states the resolved pixel value, the generator holds the
  two to each other rather than trusting either.
- **A font family declares the files that carry it**, under `$extensions."bothdecks.face"`: a list of
  descriptors, each with at least a `file` and a `weight` range. An empty list is accepted and means the
  stack is deliberately left to the platform. Silence is not accepted, because the only other way to obtain
  a face is to fetch it from somebody else's server.

## The targets

Everything under [`tokens/generated/`](tokens/generated), rebuilt from the source and never edited by hand.

| File | For | What it carries |
|---|---|---|
| `colors.css` | web | the palette, then the light and dark roles |
| `foundations.css` | web | `@font-face` blocks, the type scale and its per-surface floors, space, radius, border widths, icon sizes, elevation, durations, easing curves, springs, the deck's drag constants, and the reduced-motion substitutions |
| `BothDecksTheme.kt` | Android | the same, as a Compose theme, plus the haptic map |
| `BothDecksTheme.swift` | iOS | the same, as a SwiftUI theme, plus the haptic map |

The dark theme is selected two ways: by `prefers-color-scheme`, so a visitor who has expressed no preference
gets the theme their system asks for, and by a `data-theme` attribute, which wins over the system in both
directions.

Haptics are the one group that deliberately reaches only two of the three targets: the values are iOS and
Android platform constants, and the web has no surface that could play them. The exclusion is declared in the
manifest with its reason, and the reason is printed on every run, which is a different thing from a group
that quietly arrives nowhere.

## The command

```
php design/tools/generate-tokens.php
```

That is the whole setup. The generator is written in PHP because the repository already requires PHP of every
contributor (the server is PHP 8.5 and every pipeline installs it), and it uses no package manager, no lock
file and no install step, so it runs on a clean checkout. Nothing in it reaches the network, so the gate
measures the file rather than the machine it happens to run on.

Two more forms:

```
php design/tools/generate-tokens.php --check   # fail if the committed artefacts differ from the source
php design/tools/check-tokens.php              # the full gate
```

Running the generator twice produces byte-identical files. The artefacts carry no date, no version and no
ordering that depends on anything but the source, which is what lets `--check` be a pipeline step rather than
an article of faith.

## The rule

**No colour literal appears outside `tokens/`.** A screen names a role; the role names a palette entry; the
palette entry holds the number.

The reason is not tidiness. A value that exists in two places is a value that will exist in two *different*
places, and the difference will be discovered by a user rather than by a reviewer. The same argument applies
to every other kind of value in the source, a duration, a curve, a border width, which is why check 1 compares
the whole artefact against what the source produces rather than looking at colour alone.

What enforces the rule, exactly:

- Inside `tokens/generated/`, check 3 reads every committed artefact from disk and refuses any colour the
  source does not define. That covers the stylesheets, the Compose theme and the SwiftUI theme.
- Everywhere else under `design/`, check 4 refuses a colour literal in a hand-written file. It reads the
  hexadecimal shorthands, the eight-digit `0xAARRGGBB` form the native themes use, and the functional
  notations. A line that legitimately shows one of these forms says so on the line, with
  `bd-colour-literal-ok` and a reason, and every exemption is listed in the gate's output.
- Templates, components and prototypes outside `design/` are the same rule and are not covered by this gate.
  Extending the scan to them is a change to check 4's root, and until it is made the claim here is about
  `design/`.

## The gate

`php design/tools/check-tokens.php` runs twelve checks, each of which fails on its own, for everyone, without
anybody remembering to look:

1. **The committed artefacts are the ones the source produces.** Editing a generated file by hand is caught
   here, which is what makes the header on those files true.
2. **The generator is idempotent.** Two runs, identical bytes.
3. **Every colour in an artefact exists in the source.** Read from disk, file by file, over everything in
   `tokens/generated/`. This is the check that turns "one palette" from an intention into a property, and it
   is the reason it reads what is committed rather than what the generator just produced: the second question
   answers itself, and answers it with a pass while a foreign colour sits in the file.
4. **No colour literal outside `tokens/`**, in any of the forms a colour is written in, with any exemption
   named on the line that needs it.
5. **Every theme role resolves to a palette entry**, directly or through another role, rather than carrying a
   colour of its own.
6. **Contrast**, on every pair the role names themselves imply. `x.onFill` is by definition drawn on
   `x.fill`, and a focus ring is by definition drawn on whatever is behind the control. The pairs are derived
   from the naming rather than chosen, because a hand-picked table is a table with a hole in it.
7. **Surfaces and hairlines are distinguishable.** The pairs and the minimum ratio come from the source's own
   `surfaceSeparation` declaration, so the rule is stated once instead of once per tool. Three ways two
   surfaces are told apart, and each pair declares which: by colour alone, which must clear the minimum; by a
   named shadow, which must draw something in every theme; or by a named border, which must clear the 3:1 a
   non-text edge owes a reader against both surfaces. A well sits a hair off the page and its edge does the
   telling, which is a stricter thing to ask than a faint difference of fill.
8. **No type style is smaller than 12px**, the floor below which nothing goes on any surface.
9. **Body and label line heights clear 1.35.** Display styles are reported rather than asserted: their line
   height is whatever the measurement requires to clear the Greek and Bulgarian extremes, which is a measured
   number and not a ratio chosen by taste.
10. **The source conforms to the format it names.** Which `$`-prefixed members each node may carry, that every
    token is typed, and that anything the format does not define is namespaced. It runs on the file, with no
    network and no optional package, because a conformance step that can be skipped reports a pass for having
    been unable to run.
11. **The design-file contract matches the source.** [`tokens/figma-expected.json`](tokens/figma-expected.json)
    is the exact set of variables, collections and modes the design file must carry, and it is refused unless
    the source would produce it byte for byte. The live file is held against that contract, name by name and
    mode by mode, which is how a change in the source reaches the design and a change in the design is noticed.
12. **Every colour renders the components it declares.** Each colour carries its OKLCH components and a
    fallback hex, and the hex is recomputed from the components with no tolerance at all: a tolerance would
    only be room for a fallback to be edited without its components following.

The generator itself refuses rather than warns. A token that matches no group in the manifest, a group that
produces nothing in a target it is declared to reach, two tokens that flatten to one custom property, a
reference to a token nobody defined, a spring whose stated damping ratio its own physics does not produce, a
reduced-motion map that leaves a duration unanswered or makes one longer, a font family with no declared
face, an emitted file that fetches anything from another host: each stops the run, and nothing is written.

## The typefaces

Two families, both under the SIL Open Font License 1.1 and both without a Reserved Font Name, which is what
permits shipping a subset: a subset is a modification, and a modification may not keep a reserved name. The
licence text travels beside each binary in [`tokens/fonts/`](tokens/fonts), as the licence requires.

| Role | Family | File | What was read out of the binary |
|---|---|---|---|
| display | Vollkorn | `vollkorn-variable.woff` | 2 303 glyphs, 1 242 code points; `cyrl` with a `BGR` language system whose `locl` substitutes 32 glyphs; modern Greek 71 of 71 with outlines; Latin Extended-A 128 of 128; the eight Maltese letters; `tnum` with every digit and U+2007 FIGURE SPACE on one 550-unit advance |
| text | Commissioner | `commissioner-variable.woff` | 1 123 glyphs, 983 code points; `cyrl` with `BGR` and 22 `locl` substitutions; modern Greek 71 of 71; Latin Extended-A 124 of 128, lacking only the IJ ligature, U+0149 and the long s, which no shipped language needs |

The families are declared in the source under `font.family`, each with the files that carry it, and the
generator writes the `@font-face` blocks into `foundations.css`. Nothing else declares a face: a second
stylesheet would be a second place for a path to go stale. Two guarantees in the server's suite read the
binaries themselves: every character a shipped language draws is in a shipped face, and every face sets
Bulgarian in Bulgarian letterforms.

Four things the binaries decided, so that nobody decides them again by taste:

- **The display face was chosen for Bulgarian.** Several Bulgarian lower-case letters have shapes that differ
  from the Russian ones at the same code points, and the code points do not say which is drawn. What decides
  is a `cyrl` script with a `BGR` language system listing `locl`. Vollkorn's substitutes 32 glyphs, the
  largest Bulgarian set of any serif measured for this decision.
- **`locl` fires only under a language tag.** In both faces the substitutions hang off the named language
  system and are absent from the default one, so a shaper given no language runs `dflt` and substitutes
  nothing. Choosing the face is necessary and not sufficient: the rendered text has to carry a language, as
  `lang` on the document root and on any quoted foreign string on the web, a locale list on the text on
  Android, a language attribute on the attributed string on iOS.
- **The text face needs its weight range.** Commissioner's binary defaults to weight 100. A variable face
  declared without a `font-weight` range is treated as one static face at its default instance, so body copy
  asked for at 400 would render hairline. The range in the declaration is what makes the axis addressable.
  Its `slnt` axis is a mechanical slant and not an italic design, so it is never mapped to `font-style`.
- **Line height is measured, not chosen.** A line box has to be at least as tall as the ink the run can
  paint, over every glyph a script can select, Bulgarian alternates included. The binding constraint is
  Latin Extended-A rather than Greek or Cyrillic: the tallest glyph in both faces is the Slovak l with acute
  and the deepest are the Latvian letters with comma below. A display ratio of 1.30 clears the ink at every
  size in the scale for Vollkorn; Commissioner needs the 1.35 floor that check 9 holds body and label styles
  to. The two faces have different vertical metrics, so a display line and a text line at the same size do
  not share a baseline by default; that is left uncorrected here on purpose, because an override in the
  stylesheet would make the browser disagree with the native platforms and with the design file.

One more thing the binaries settled, about numbers. Vollkorn carries U+202F NARROW NO-BREAK SPACE and
U+2011 NON-BREAKING HYPHEN; Commissioner carries neither. A salary grouped with U+202F sets whole in a display
style and breaks to a fallback face mid-number in body text. U+00A0 is in both faces, so it is the separator a
number formatter emits.

The `.woff` files are the complete faces. Cutting them into per-script subsets is a build step for the
milestone that first serves a page, and it has two conditions: the subsetter must keep `locl` and the `cyrl`
script, which the common tools drop without a warning, and the output must pass the two guarantees above
before it ships, because a subset that lost `locl` reintroduces the Russian letterforms the display face was
chosen to avoid.

## Adding a token

1. Add it to `tokens/tokens.json`, under an existing group.
2. Run the generator. If the token belongs to a group the manifest does not know, the run stops and names it;
   add the group to [`tools/src/Manifest.php`](tools/src/Manifest.php) with the targets it must reach.
3. Run the gate.
4. Commit the source and the artefacts together. They are one change.

## What the manifest declares and the source does not carry yet

The manifest names every group before the source defines it, so a group that has not arrived is reported on
every run rather than being noticed by its absence. Today that list holds one entry:

- **Safe-area insets.** They are read from the platform at run time on all three targets and are deliberately
  not constants, so what would go here is the shape of the group rather than values. Until it exists, a layout
  that needs an inset has nothing in the system to name.

The product name and the taxonomy version are reported alongside it. Neither is a token: both are carried in
`$extensions."bothdecks.product"` for the design file to bind to, and both reach the interface from the
server's configuration, which is what ADR-0004 requires.
