# Taxonomy

> **Empty until M1.** What follows describes what will live here and why, not what is here now.

Ingestion, versioning and bubble computation for the multilingual occupation and skill classification that the
whole product depends on ([ADR-0005](../docs/adr/0005-codes-not-strings.md)).

Three things live here:

- **Ingestion.** Reads a released version of the reference classification and writes concepts, labels per
  language, relations and hierarchy. Each import is dated, digested and versioned; attachments point at a code
  *and* a version.
- **Migration.** Moving between versions produces a report of disappeared, merged and new codes. No attachment
  is changed silently.
- **Bubbles.** Related occupations are grouped offline from four weighted signals: hierarchy distance,
  essential-skill overlap, description similarity and observed career transitions. Results are stored and
  versioned, never computed per request, and are correctable by hand because no automatic clustering survives
  contact with reality unamended.

Populated in **M1**.
