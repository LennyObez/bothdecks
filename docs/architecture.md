# Architecture

One backend serves three clients over one contract. This document describes the modules, what owns what, and
how a request flows.

> **This is the target architecture, not an inventory of what exists.** At the current milestone the server
> holds one shared module with the application identity and a liveness endpoint. Each module below arrives with
> the milestone that needs it, listed in [`roadmap.md`](roadmap.md). Sections written in the present tense
> describe how the system is designed to work, and the code catches up milestone by milestone; where a
> mechanism is already in place, it says so.

## Shape

The server is a modular monolith. Modules are vertical slices: each owns its tables, exposes a narrow contract,
and may not reach into another module's internals. Boundaries are checked mechanically, not by review.

The website is rendered by the same application. Public pages are statically rendered and incrementally
regenerated so they are indexable and fast; the application surfaces are server-rendered with server-driven
reactive components, so there is no single-page application and no separate front-end build in the critical
path.

The mobile applications share a Kotlin Multiplatform module holding models, validation, the generated API
client, offline caching and session logic. Their interfaces are native (Jetpack Compose and SwiftUI) because
the swipe gesture and the accessibility behaviour deserve each platform's own primitives.

## Modules

| Module | Owns |
|---|---|
| `Identity` | Accounts, sessions, devices, passkeys, roles |
| `Taxonomy` | Occupation and skill concepts, labels per language, relations, versions, bubbles, resolution |
| `CandidateProfile` | Profile, uploaded document, skills, languages, preferences, photos |
| `Employer` | Company, verification, public page, behavioural indicators |
| `JobOffer` | Offer, URL import, lifecycle, publication, expiry |
| `Discovery` | Deck composition, ranking, exploration, explanation |
| `Swipe` | Intents, reciprocity, match creation |
| `Conversation` | Post-match exchange, screening sequences |
| `Evaluation` | Scorecards, comparison, shortlist, external export |
| `CompanyQA` | Questions, answers, right of reply |
| `Gamification` | Progress, missions, celebration |
| `JobSearchRecord` | Activity log and exports for public employment services |
| `Trust` | Moderation, reports, appeals, verification, fraud signals |
| `Billing` | Recruiter subscriptions and invoicing |
| `Governance` | Model registry, decision logs, explanations, impact assessments |

A module names another module only through that module's published contract, and anything under an internal
namespace is private. The framework ships a structural boundary checker; wiring it into this repository's gate
sequence belongs to M1, the first milestone with more than one module to keep apart.

## The value tables

The framework's extensions supply accounts, messages, payments and administration. The tables below are the
product's own, and they carry its value.

**Taxonomy.** `taxonomy_version` · `occupation` · `skill` · `occupation_skill` (essential or optional) ·
`concept_label` (concept, locale, label, kind) · `concept_embedding` · `occupation_cluster` and
`occupation_cluster_member` with a directed weight, which is what makes a bubble asymmetric.

**Candidate.** `candidate` (visibility, blind by default) · `candidate_photo` · `candidate_experience` ·
`candidate_skill` (code, level, **origin** of document, manual or inferred, confidence, supporting excerpt) ·
`candidate_language` · `candidate_preference` · `cv_document` (digest, parsed at, purge at).

**Offer.** `employer` (legal name, tax identifier, registry identifier, verification method and date) ·
`job_offer` · `offer_skill` (requirement, weight) · `offer_import` (URL, extractor, confidence, provenance,
recruiter confirmation).

**Swipe.** `swipe` (actor, target, direction, context, position in deck, **algorithm version**, shown at,
decided at) · `match` · `recommendation_log` (score, features that produced it, algorithm version, explanation
rendered). The last of these is not analytics convenience: it is the record regulatory logging requires and the
training set the ranking model needs.

**Conversation.** `conversation` · `screening_sequence` · `screening_question` · `screening_answer` ·
`conversation_priority`.

**Evaluation.** `requisition` · `evaluation_criterion` · `evaluation_score` · `shortlist` · `share_consent`.
No identity leaves the platform without a row in `share_consent`.

**Company.** `company_question` · `company_answer` · `answer_right_of_reply` · `employer_indicator` (period,
volume, value).

**Record.** `job_search_action` · `job_search_export` (period, digest, verification code).

**Governance.** `ai_model_version` · `ai_decision_log` · `ai_incident`.

## A request through the deck

1. The client asks for the next cards.
2. `Discovery` reads the candidate's preferences and their occupation bubble from `Taxonomy`.
3. Hard constraints are applied first: work authorisation, commute, language level, contract, salary,
   availability. Anything failing a hard constraint never reaches scoring.
4. Candidates for ranking are retrieved, scored, and boosted where the other party has already expressed
   interest. The boost is bounded.
5. Each card's explanation is generated from the same features that produced its score.
6. One row per card is written to `recommendation_log` before the response is sent.
7. The client renders the deck; the decision returns as a swipe, which `Swipe` records and, on reciprocity,
   turns into a match.

## Trust boundaries

Each control arrives with the milestone that opens the boundary it guards. None of them exists yet, because
none of these boundaries has been crossed by any code.

| Boundary | Control | Milestone |
|---|---|---|
| Uploaded document → processing | Size limit, malware scan, sanitisation, real type detection | M2 |
| Personal data → model inference | Segmentation, minimisation, identifier removal that fails closed, European endpoints only | M2 |
| Candidate → recruiter | Blind profile until match; contact details cloaked | M4 |
| Platform → external system | Per-requisition consent, recorded and revocable | M5 |
| Public input → publication | Classification before publication, human review queue, appeal | M8 |

## The contract

The server generates the OpenAPI specification; the specification is frozen in the repository; the Kotlin and
Swift clients are generated from the frozen file.

Three checks will protect it from **M4**, when the first endpoint a client consumes exists: a test failing when
the generated specification differs from the frozen one, a comparator refusing an unannounced breaking change,
and the framework's own detector over the PHP surface. Until then there is no contract to protect.

## Related records

- [ADR-0001](adr/0001-single-repository.md): one repository
- [ADR-0002](adr/0002-pulsar-as-backend-and-web-renderer.md): framework
- [ADR-0005](adr/0005-codes-not-strings.md): codes and bubbles
- [ADR-0006](adr/0006-reciprocal-decks-and-asymmetric-history.md): decks and history
- [ADR-0007](adr/0007-screening-orders-never-decides.md): screening
- [ADR-0008](adr/0008-staging-runs-on-existing-infrastructure.md): where staging runs, and why the sovereignty
  rule binds production
