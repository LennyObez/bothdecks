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
- [ ] Staging environment and a deployment that runs from a tag, on the existing virtual machine in the Paris
      region, holding synthetic data only ([ADR-0008](adr/0008-staging-runs-on-existing-infrastructure.md))

## M1: Taxonomy

*Done when a keyword in one language resolves to the same code as its equivalent in another, and a neighbouring
occupation surfaces. Both measured, not asserted.*

- [ ] Wire the framework's structural boundary checker into the gate sequence, now that more than one module
      exists to keep apart
- [ ] Choose the reference classification; record licence, language coverage, formats and release cadence
- [ ] Schema: versions, occupations, skills, relations, labels per language, embeddings
- [ ] Ingestion with digest and dated version
- [ ] Version migration producing a report of disappeared, merged and new codes
- [ ] Resolution cascade: exact label, approximate, vector, then ask the user
- [ ] Every resolution records its path, score and taxonomy version
- [ ] Product-owned namespace for skills absent from the reference, with a promotion procedure
- [ ] Bubble computation from the four weighted signals, stored and versioned
- [ ] Administration screen to inspect and correct a bubble
- [ ] Measures: coverage, mapping precision per language, share needing confirmation

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

- [ ] Deck composition: hard constraints, then score, then bounded reciprocal-interest boost
- [ ] Explanation on both sides, derived from the scoring features
- [ ] One recommendation log line per displayed card
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
- [ ] Design tokens generated from one source
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
- [ ] Accessibility audit against WCAG 2.2 AA, on every surface
- [ ] Penetration test and remediation
- [ ] Load test at the target scale
- [ ] Disaster recovery exercise: restore, measure, record
- [ ] Short video pitches, if the modality is retained
- [ ] Legal texts in 24 languages
- [ ] Commercial name, after trademark clearance
- [ ] Public launch
