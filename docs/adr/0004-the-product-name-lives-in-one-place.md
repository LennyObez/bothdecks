# ADR-0004: The product name lives in one configuration value

## Status

Accepted

## Context

The product is called **Both Decks**, after the mechanic that distinguishes it: the candidate has a deck of
offers, the recruiter has a deck of profiles, and a match happens when both swipe right. The name says what the
product does, which is worth more in conversation than a name that has to be explained.

A name reaches a lot of surfaces. Templates, translation catalogues in 24 languages, two mobile applications
and their store listings, notification bodies, exported documents, error messages. Written literally in each of
them, it becomes expensive to change at exactly the moment a business needs to change it: a trademark
objection, an acquisition, a pivot, a market where the name means something unfortunate.

The cost of avoiding that is one indirection. The cost of not avoiding it is a rename that misses an occurrence
in one of 24 languages on one of three platforms, which is the class of defect that survives review.

## Decision drivers

1. A rename must be a configuration change, not a refactor.
2. A descriptive name is more exposed to a trademark objection than a coined one, so the possibility of having
   to change it is real rather than theoretical.
3. The mechanism costs almost nothing to build now and cannot be retrofitted cheaply later.

## Decision

The product name is a single configuration value. It appears nowhere else in the source: not in a template, not
in a translation catalogue, not in a mobile application's user-visible strings, not in an error message.
Everything that displays it reads it from the application identity.

Technical identifiers that cannot change after publication are deliberately separate from it: the mobile
package identifier, the PHP namespace, the repository name and the Composer package all derive from a stable
slug rather than from the display name. They are not user-visible, so they take no part in a rename. The
identity object exposes the two independently, and a test asserts that neither is derived from the other.

## Alternatives considered

### Write the name where it is needed and rename later with a search

Rejected. A repository-wide search finds the occurrences someone thought to look for. It does not find a name
built by concatenation at runtime, and it does not find the one in the language nobody on the team reads.

### Treat the name as immutable and pick carefully

Rejected as wishful. Trademark clearance for a descriptive name is not assured, and a product that ships in 27
countries will eventually meet a market where the name is a problem.

## Consequences

### Positive

- Renaming is a configuration change with a test proving it was complete.
- The display name and the technical identifiers cannot drift into each other.

### Negative

- Strings that would read naturally with a literal name must be parameterised, which is slightly more verbose
  in templates and catalogues.

### Neutral

- Translators see a placeholder rather than a name. This is standard practice for branded strings.

## Enforcement

Three tests, all in the `guarantees` suite.

The first walks **every file git tracks** and fails if the name appears in text a user could see. PHP is read
through the tokenizer, so a namespace is ignored while a string literal is caught; every other tracked file
type is read as plain text. Two categories are excluded, each by an entry that states its reason: documentation
and repository metadata, which may name the project, and test code, which never reaches a user and has to be
able to name the mechanism it tests.

The second compares the scan against `git ls-files` and fails if a tracked file that could carry user-visible
text falls outside it without an explicit exclusion. A scan is worth what it reaches, and a list of directories
is outgrown silently; this one cannot be.

The third renders the product under a different configured name and fails if any occurrence of the previous
name survives in the output: the half a static scan cannot see, because a name assembled at runtime never
appears in source.

## Field report

**2026-09-07.** The mechanism was exercised in earnest before publication, when the working name was replaced
by the chosen one. Display name, slug, namespace, package name and repository name all moved. The parts the
records above cover were a configuration change; the parts deliberately outside it, being immutable
identifiers, were a mechanical rename done once while nothing depended on them. That is the split this record
predicted, and it held.

## Security impact

None.

## Privacy impact

None.

## Performance impact

None. The value is read once at boot.

## Migration / rollback plan

Changing the displayed name means changing one configuration value and the store metadata. The slug and the
namespace stay as they are, because nothing user-visible depends on them.

## Links

- Trademark clearance is still required before the name is used commercially: Benelux and European
  intellectual-property offices, Nice classes 9, 35 and 42. A descriptive name is harder to register than a
  coined one, and that is a conversation for a professional rather than a decision recorded here.
