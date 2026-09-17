# Roadmap

Eleven milestones. Each one produces something usable and ends with a demonstration; none ends on an invisible
layer. Each becomes a GitHub milestone, and each task below becomes an issue.

The order is not negotiable in one respect: **M4 is the first real product**. Everything before it exists to
make M4 possible, and everything after it improves a loop that already works.

---

## M0: Foundation

*Done when a change passes every gate and deploys to staging.*

- [x] Repository, structure, licence, publication rule
- [x] Architecture decision records for the decisions already taken
- [x] Server skeleton on the framework, pinned to an exact commit
- [x] Application identity as a single configuration value
- [x] First product guarantee, proven to fail on a real violation
- [x] An integration test that dispatches a real request, because a green unit suite proves nothing about
      whether the application can answer one
- [x] Gate sequence: manifest, formatting, static analysis at max level, every suite, mutation testing,
      dependency audit, with every step observed passing on a runner rather than only locally
- [x] Continuous integration with path filters, a repository-wide job for the guarantees, and a check that
      discovers directories from git rather than from a list it could outgrow
- [x] Repository published, with a ruleset requiring signed commits, a linear history, a pull request and the
      four checks above
- [x] Project board with the shared field set, eleven milestones, and the declared labels applied
- [x] Design token source, its generator and the twelve checks that hold the three themes and the design
      file to it, brought forward from M4 because nothing in it depends on the milestones between
- [x] The decisions of 9 September recorded: hosting ([ADR-0009](adr/0009-hosting-in-the-paris-region.md)),
      presence in the recruiter deck as opt-in ([ADR-0010](adr/0010-presence-in-the-recruiter-deck-is-opt-in.md)),
      the ranking as an automated decision ([ADR-0011](adr/0011-the-ranking-is-treated-as-an-automated-decision.md)),
      the Flemish Region first ([ADR-0012](adr/0012-launch-in-the-flemish-region-first.md))
- [ ] Staging environment on the virtual machine in the Paris region, deployed from the `staging` branch by
      the committed script, holding synthetic data only and reachable only behind authentication
      ([ADR-0009](adr/0009-hosting-in-the-paris-region.md), [deployment](deployment.md)). Open until
      `/health` has been seen answering over TLS.

## M1: Taxonomy

*Done when a keyword in one language resolves to the same code as its equivalent in another, and a neighbouring
occupation surfaces. Both measured, not asserted.*

- [x] Wire a structural boundary checker into the gate sequence, now that more than one module exists to
      keep apart: `deptrac.yaml`, every dependency declared, a guarantee test keeping it in step with `src/`
- [x] Choose the reference classification; record licence, language coverage, formats and release cadence:
      ESCO v1.2.1, in [`taxonomy.md`](taxonomy.md)
- [x] Schema: versions, occupations, skills, relations, labels per language, embeddings
- [x] Ingestion with digest and dated version: `taxonomy:snapshot` and `taxonomy:import`
- [x] Version migration producing a report of disappeared and new codes, changed labels and changed skill
      relations: `taxonomy:report`. Merged codes are read from the disappeared and the new; the source
      publishes no merge relation the report could follow
- [x] Resolution cascade: exact label, approximate, vector, then ask the user
- [x] Every resolution records its path, score and taxonomy version
- [x] Product-owned namespace for skills absent from the reference, with a promotion procedure: the table
      and the procedure; the commands arrive with the first module that needs a skill the source lacks
- [x] Bubble computation from the four weighted signals, stored and versioned; the mobility signal takes
      weight zero, declared, until the product has histories to observe
- [x] Administration screen to inspect a bubble, its signals and its corrections; corrections are made at
      the console with a reason and an author until accounts exist
- [x] Measures: coverage, mapping precision per language, share needing confirmation: `taxonomy:report` and
      `taxonomy:evaluate`, figures in [`taxonomy.md`](taxonomy.md)

The first half of the criterion is measured: a label in any of the 24 languages leads to its concept, over
every label of the fixture, and precision per language is reported on the whole release. The second half
is measured as far as it can be without matches: neighbouring occupations surface, and every bubble's
members and signals are stored and reported. How often a match comes from a neighbour rather than the exact
occupation, which is what says whether a bubble is useful, needs matches and is measured from M4.

## M2: Candidate profile

*Done when a real CV produces a correct profile in under two minutes.*

- [ ] Upload: size limit, malware scan, sanitisation, real type detection
- [ ] Text extraction, with optical recognition for scanned documents
- [ ] Segmentation before any model call
- [ ] Identifier removal that fails closed, with manual entry as the fallback path
- [ ] Structured extraction against a schema, on an EU-hosted model
- [ ] Attachment to taxonomy codes
- [ ] Correction screen showing origin and confidence per field
- [ ] Source document purged after extraction unless retention is requested
- [ ] Guarantee: no field that invites discrimination is ever written
- [ ] Blind profile, and the preview of what a recruiter sees
- [ ] Photos the candidate features, hidden until a match
- [ ] Per-language reference set, and the measured accuracy that selects the model

## M3: Job offers

*Done when a pasted link is a live offer in under five minutes.*

- [ ] Extractor chain: structured page data, per-domain adapters, sharing metadata, model fallback
- [ ] Confidence score per field, and the fields needing attention
- [ ] Manual entry, always available
- [ ] Company verification: domain match, tax identifier, national registry
- [ ] Recorded, dated confirmation that the recruiter may publish
- [ ] Provenance recorded per offer
- [ ] One request per submitted URL, robots exclusion respected, per-domain rate limit
- [ ] Deduplication across sources, expiry detection, salary normalisation
- [ ] Public offer page, statically rendered and incrementally regenerated
- [ ] Translated URLs and alternate-language links for the 24 locales

## M4: The loop

*Done when a candidate and a recruiter go end to end. **First real product.***

- [ ] Both decks, the candidate's and the recruiter's, shipped together
      ([ADR-0006](adr/0006-reciprocal-decks-and-asymmetric-history.md))
- [ ] Presence in the recruiter deck as a recorded, revocable consent, off by default, asked after the first
      session beside the blind profile ([ADR-0010](adr/0010-presence-in-the-recruiter-deck-is-opt-in.md))
- [ ] Guarantee: a profile that has not opted in never appears in a recruiter deck query
- [ ] Deck composition: hard constraints, then score, then bounded reciprocal-interest boost
- [ ] Explanation on both sides, derived from the scoring features
- [ ] One recommendation log line per displayed card, with the fields
      [ADR-0011](adr/0011-the-ranking-is-treated-as-an-automated-decision.md) names
- [ ] The three rights of an automated decision: a named person behind every decision that follows a rank, a
      point of view a candidate can add, and a contest that reaches a person who answers in writing
- [ ] Open regions as configuration, with sign-up limited to the Flemish Region at launch and refused elsewhere
      in words ([ADR-0012](adr/0012-launch-in-the-flemish-region-first.md))
- [ ] Dutch interface and mediation path complete for the Flemish Region, with the test that refuses a Dutch
      catalogue missing a key
- [ ] Swipe card as a CSS transition, with the reduced-motion variant
- [ ] Buttons performing exactly what the gesture performs
- [ ] Keyboard operation and screen-reader announcements for the deck
- [ ] Undo for the last action
- [ ] End-of-deck state that ends the session rather than inviting more
- [ ] Match creation on mutual interest, in either direction
- [ ] Conversation after match
- [ ] Screening sequence: opening message, closed questions, three-way routing
- [ ] Guarantee: the routing outcome type cannot express a rejection
- [ ] Guarantee: no left swipe is exposed by any route, view or contract
- [ ] Right-swipe history with state, and match list
- [ ] Design tokens consumed by the web from the generated theme, with no colour written outside the source
- [ ] OpenAPI specification frozen, with the three checks that protect it

## M5: Recruiter console

*Done when a full hire runs inside the tool.*

- [ ] Recruiter deck, with interested candidates surfaced first and marked
- [ ] Requisition pipeline
- [ ] Scorecard with criteria weighted before candidates are seen
- [ ] Side-by-side comparison
- [ ] Shortlist and column board, fully operable without drag and drop
- [ ] Export to an external system, with per-requisition candidate consent
- [ ] Guarantee: no identity leaves without a recorded consent row
- [ ] Behavioural indicators, with a minimum sample size and a contest path
- [ ] Feedback prompts that feed those indicators
- [ ] Company page and question answering
- [ ] Subscription and billing

## M6: Mobile applications

*Done when both applications are in public testing.*

- [ ] Shared module: models, validation, generated client, offline cache, session
- [ ] Convention plugins and dependency catalogue
- [ ] Android application on the four tabs
- [ ] iOS application on the four tabs
- [ ] Native swipe gesture with its non-gesture alternative on both platforms
- [ ] Themes generated from the shared token source
- [ ] Localisation into 24 languages through each platform's native mechanism
- [ ] Push notifications, localised server-side at send time
- [ ] Device and token lifecycle
- [ ] Server-side verification of store purchases
- [ ] Screenshot and interface tests
- [ ] Store metadata in 24 languages, and the release pipelines

## M7: Proof of job search

*Done when a caseworker accepts the document, verified with real caseworkers.*

- [ ] Activity log, one row per action
- [ ] Accessible PDF: summary, detailed log, an explanation of what the platform is
- [ ] Tabular and structured exports
- [ ] Digest, verification code, and the public verification page
- [ ] Country packs: language, headings, fields, reference period, wording
- [ ] Research pass on the requirements of each public employment service
- [ ] Guarantee: an export covers exactly the actions in its period

## M8: Product life

*Done when the guardrails are tested, not merely written.*

- [ ] Match celebration, profile completeness, weekly digest, finite daily deck
- [ ] Guarantee: no streak punishes an absence, no leaderboard, no endless scroll
- [ ] Company questions and answers, with the right of reply, moderated before publication
- [ ] Classification before publication, human review queue, appeal
- [ ] Reporting, blocking, link and image scanning in conversations
- [ ] Fraud signals on offers and employers
- [ ] Notification catalogue with per-category control, quiet hours and frequency caps
- [ ] Transparency reporting data model

## M9: Learned matching

*Done when the learned ranker beats the deterministic one on a measure fixed in advance.*

- [ ] Training pipeline from the recommendation and swipe logs, with purpose tagging
- [ ] Hybrid retrieval and learned re-ranking
- [ ] Offline evaluation, then online with guardrail metrics
- [ ] Reciprocity and exposure fairness
- [ ] Exploration for cold start
- [ ] Model registry, versioned, with a card per version
- [ ] Decision logging, retention and tamper evidence
- [ ] Human oversight interface for the recruiter
- [ ] Drift monitoring and rollback
- [ ] Guarantee: no protected attribute or known proxy reaches the features

## M10: Opening

*Done when an external audit passes.*

- [ ] Compliance dossier completed against primary sources
- [ ] Accessibility audit against WCAG 2.2 AA, on every surface. The legal baseline in the Union references an
      earlier version of the guideline; 2.2 AA is a commitment the product makes beyond it, and the
      accessibility statement says so rather than presenting it as what the law asks
- [ ] Penetration test and remediation
- [ ] Load test at the target scale
- [ ] Disaster recovery exercise: restore, measure, record
- [ ] Short video pitches, if the modality is retained
- [ ] Legal texts in 24 languages
- [ ] Commercial name, after trademark clearance
- [ ] Public launch
