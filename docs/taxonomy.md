# Taxonomy

Occupations and skills are codes; text is only a rendering ([ADR-0005](adr/0005-codes-not-strings.md)). This
page records where the codes come from, on what terms, how they reach the database, how free text becomes a
code, how the bubbles around an occupation are computed, and what is measured.

## The source

| | |
|---|---|
| Classification | ESCO, the European classification of skills, competences, qualifications and occupations, published by the European Commission |
| Release used | v1.2.1, current since 10 December 2025; written in `config/taxonomy.php` |
| Reached through | its API, `https://ec.europa.eu/esco/api`, over HTTPS |
| Data licence | Creative Commons Attribution 4.0 International, as the publisher's copyright notice states for content the Union owns; parts of the skill hierarchy draw on third-party material the same notice lists |
| Attribution given | "ESCO, European Commission, DG Employment, Social Affairs and Inclusion", copied onto every version and every concept row |
| Languages carried | 28 in the source, of which the product imports its 24 |
| Size of v1.2.1 | 3 039 occupations, 13 939 skills, 619 occupation groups (the international classification), 640 skill groups |
| Release cadence | irregular, one to three releases a year in recent years |

Two properties of the API shape the code and are worth knowing before touching it:

- **A request that does not name a release is answered for an old one**, and nothing in the answer says so.
  Every request the product sends carries `selectedVersion`; a snapshot of a release the configuration does
  not name cannot be taken.
- **The listing endpoint's `offset` counts pages, not records.** The client checks that the page size it
  asked for is the one served, checks the total the endpoint announced against what it listed, and refuses
  a listing that came out short or repeated a concept.

The client names the product in its user agent, as the publisher's terms ask; fetches resources in batches,
several batches at a time, retrying each failed request on its own with a pause that doubles between
attempts; and does not follow redirects, because the address is a reviewed configuration value and a source
that moves is reconfigured rather than followed.

The bulk download the publisher offers asks for an e-mail address before serving the files; the API asks
nothing and serves the same data, so the API is what the product reads.

## From the source to the database

```
taxonomy:snapshot   the API, every concept of the release, to var/taxonomy/esco/<release>/, with a manifest
taxonomy:import     the snapshot, verified against its manifest, to the tables, as one new version
taxonomy:embed      a vector per occupation and skill, from the configured model
taxonomy:bubbles    the bubble of every occupation, from the four signals
taxonomy:report     what the version holds, what changed since the previous one, the measures
taxonomy:evaluate   resolution precision per language
```

**The snapshot** is the dated identity of a version. Its manifest carries the digest of every file and a
digest of the whole, over the source, the release, the address, the licence, the attribution and the file
digests; the version row carries that digest, so the database can always say which bytes it was built from
and on what terms. A snapshot whose files were truncated or edited, or whose manifest was relabelled in any
of those fields, does not open; the time it was taken is the one field that may be edited, because it is
not part of what the data is. Snapshots are not tracked; a test fixture derived from one is.

**The import** is one transaction: either the whole snapshot becomes a version or nothing does. A relation
pointing outside the snapshot, a concept without a preferred label in the fallback language, a preferred
label made of no letters: each stops the import with the record named. Labels in languages the product does
not serve are not imported. Alternative labels made of no letters or digits (the source carries a few dashes)
are left out and listed in the log. The new version becomes current in the same transaction.

Every concept row carries its provenance (the resource it was fetched from) and its licence. Occupations
narrower than another carry no group of their own in the source; they inherit the group of the occupation
above them, which the import resolves by walking up.

## The tables

`taxonomy_version` · `taxonomy_concept` (every kind, with provenance and licence) · `concept_label`
(per language and wording, with a normalised form) · `concept_description` · `concept_broader` (every
hierarchy edge) · `occupation` · `skill` · `occupation_skill` (essential or optional) · `concept_embedding`
· `occupation_transition` · `occupation_cluster` and `occupation_cluster_member` · `occupation_cluster_override`
· `product_skill` · `taxonomy_resolution_log`.

The schema is one migration in plain SQL, because the product runs on one engine and leans on what only it
offers. It creates the trigram extension itself and refuses to run where `vector` or `postgis` is missing.

## Normalisation

A label at import and a query at resolution go through the same function: compatibility decomposition,
lowercase, combining marks removed, everything but letters and digits collapsed to single spaces. Accents
are dropped on purpose: a title typed on a phone often lacks them, and the cost, two words differing only by
an accent colliding, is paid as a question to the user rather than as a wrong code, because an exact match on
more than one concept is never resolved silently.

## Resolution

From the deterministic to the probabilistic, never the other way. Each step answers with one concept, hands
the question to the user with the concepts it found, or passes to the next because it found nothing close
enough. No step guesses between two concepts it cannot tell apart.

1. **Exact.** The normalised query equals a normalised label in the query's language. One concept: resolved.
   Several: resolved to the one whose preferred label matched, when exactly one did; otherwise the user
   chooses.
2. **Approximate.** Two trigram similarities between the query and the labels of the language, through the
   trigram index: of the whole label, and of the closest part of it, since the source writes many labels as
   a masculine and a feminine form joined and a person types one word of it. Candidates come from the
   configured floor up. A whole label within the trusted similarity, clear of the runner-up by a margin,
   resolves alone. Anything looser is trusted only when the vector step names the same concept above the
   vector floor; when the two disagree, or the vectors are not confident, the user chooses among what both
   found, the label matches first. Before the concepts have vectors, only a whole-label match resolves and a
   word match asks.
3. **Vector.** Reached when the labels offered nothing. The query is embedded as typed, in its own language,
   and compared with the concepts' vectors, which are computed from the label and description in the
   fallback language; the model is multilingual, and how well that holds per language is what the
   evaluation measures. The nearest concept wins when it clears the vector floor and the runner-up by the
   margin.
4. **Ask.** The best candidates, with their codes visible, and no answer.

Every call writes one row to `taxonomy_resolution_log`: path, score, version, locale, the candidates shown,
the time taken. A code can be explained afterwards by reading the log. The evaluation runs its resolutions
inside transactions it rolls back, so they never reach the log; a resolution run from the inspection screen
does reach it, like any other.

## Bubbles

A bubble is the set of occupations whose offers someone in a given occupation would see, each with a
directed weight: the weight of B in A's bubble says how acceptable an offer for B is to someone in A, and
the reverse pair is scored on its own. Four signals, each between 0 and 1, combined with the weights in
`config/taxonomy.php`:

| Signal | What it measures | Source |
|---|---|---|
| hierarchy | distance in the occupation classification, or a parent relation between the two | `concept_broader`, the group codes |
| skills | the share of B's essential skills that are essential to A as well | `occupation_skill` |
| vector | similarity of the two vectors | `concept_embedding` |
| mobility | the share of observed moves out of A that went to B | `occupation_transition`, empty until the product has histories to observe |

A signal without data for a version takes weight zero and the others are rescaled to sum to one; the weights
actually used are stored with every bubble, and the report says which signals carried it. Candidates are
the occupations in the same minor group, those sharing an essential skill, and the nearest by vector; a
neighbour below the floor is not in the bubble, and a bubble holds at most the configured number.

**Corrections.** A person adds or excludes a neighbour with `taxonomy:bubble:override`, giving a reason and
their name. The correction is keyed by the source identifiers of the two occupations, so it survives a
recomputation and a version change, and the member it produces says it was added by a person. An exclusion
is applied before the cut at the maximum, so the next candidate takes the place; an addition comes on top of
the computed members. The bubble is recomputed at once.

**Inspection.** `/console/taxonomy` shows the version, the measures, any occupation with its bubble, the
four signals behind each neighbour and the corrections recorded, and runs the cascade on any text. It reads;
corrections are made at the console. It exists only where `TAXONOMY_INSPECTION_TOKEN` is set, and only for
a request presenting the token as the password of HTTP basic authentication; without a token the routes
answer 404. This holds until accounts and roles exist, when the screen moves behind them.

## Product-owned skills

A skill the source lacks is created under the product's namespace with a version of its own (none), a
status (`proposed`, `accepted`, `promoted`, `retired`), a reason and an author. When a later release of the
source covers it, it is promoted: the row records the source identifier it now maps to and the date, and
nothing else about it changes, so profiles that carry the product's identifier keep it and resolve through
the mapping. The table exists; the commands that create and promote arrive with the first module that needs
a skill the source lacks, which is the profile import.

## Moving to a newer release

1. Change `source.version` in `config/taxonomy.php`.
2. `taxonomy:snapshot`, `taxonomy:import`, `taxonomy:embed`, `taxonomy:bubbles`.
3. `taxonomy:report`: the migration section lists the concepts that appeared and disappeared per kind, the
   preferred labels that changed, the skill relations added and removed. A removed occupation is a code some
   profile may carry; that is what the section is for.
4. `taxonomy:evaluate`, and compare with the figures recorded for the previous release.

The previous version stays in the tables, not current, so the report can be re-run and a profile that
carries an older code can still be read.

## Measures

`taxonomy:report` gives, per language and per concept kind, the share of concepts with a preferred label and
with a description, and the number of alternative labels; the vectors present; the bubbles, how many are
empty, their mean size, how many members a person added; and, over the log, the share of resolutions that
asked the user.

`taxonomy:evaluate` gives, per language, precision and recall of the cascade two ways:

- **held out**: a seeded sample of alternative labels is removed inside a transaction, each is resolved as if
  typed, and the transaction is rolled back. This measures the approximate and vector steps on wordings the
  labels do not carry, which is what a real query mostly is;
- **annotated**: the queries in `apps/server/resources/taxonomy/annotated-queries.json`, colloquial wordings
  a person wrote with the code each should resolve to. Sixty queries today, occupations only, in English,
  French, Dutch and German; the other twenty languages and the skills are measured held-out only until
  someone who speaks them writes their queries.

Four figures each time: resolved right, resolved wrong, asked with the right concept among the candidates,
asked without it. A wrong resolution is the figure to keep near zero: a question costs a tap, a wrong code
costs a deck of wrong offers.

The share of matches that came from a neighbouring occupation rather than the exact one, which is what
measures a bubble's usefulness, needs matches; it is measured from the milestone that produces them.

### Figures for v1.2.1

Measured on 17 September 2026, version 1 of the tables, vectors from `bge-m3`, the thresholds in
`config/taxonomy.php` as committed, `taxonomy:evaluate --sample=100 --seed=20260917`. Every language has a
preferred label and a description for every occupation and every skill. Held out, per language: precision
between 89 % and 100 % (median 100 %), wrong resolutions between 0 % and 3 % of queries (median 0 %), recall
between 7 % and 74 %, the share asking between 25 % and 93 %. In most of the questions asked, the right
concept is among the candidates shown. The languages that ask most (Irish, Swedish, Finnish, Hungarian,
Danish, Polish) are those the source gives the fewest alternative labels, so a held-out label there is often
the only other wording of its concept. On the 60 annotated queries: precision 92 % to 100 %, one wrong
resolution in German and one in Dutch, both to a concept the source itself labels with that word.

Bubbles: 3 039, of which 7 empty, 10.6 members on average, computed with skills 0.44, hierarchy 0.28, vector
0.28, mobility 0.

The thresholds were set from these figures: the trigram floor, the rule that anything but a whole label a
typo away needs the vectors' agreement, and a vector floor of 0.60 rather than 0.70, which raised recall in
every language without raising the wrong resolutions. A question costs a tap; the trade towards asking is
deliberate, and the annotated set is where a person's judgement of it is recorded.
