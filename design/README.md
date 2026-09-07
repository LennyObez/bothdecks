# Design

> **Empty until M4.** What follows describes what will live here and why, not what is here now.

The single source of design tokens, and the generators that turn it into three platform themes: CSS custom
properties for the web, a Jetpack Compose theme for Android, and a SwiftUI theme for iOS. A value is defined
once and never restated per platform.

The identity is defined from first principles rather than inherited from the framework the server is built on.
The framework supplies the token *mechanism*; none of its palettes, typefaces or scales carry over.

Two properties become tests in M4, alongside the tokens they check:

- Every text-on-surface token pair meets the WCAG 2.2 AA contrast threshold.
- Every language the product ships in has a typeface covering its script, which rules out Latin-only families
  because the product ships in Greek and Bulgarian.

Populated in **M4**, from the design brief.
