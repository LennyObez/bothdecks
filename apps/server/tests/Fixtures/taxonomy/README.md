# Taxonomy fixture

`snapshot/` is a closed slice of a real snapshot of ESCO v1.2.1, derived by `tools/derive-taxonomy-fixture.php`:
seven occupations named by code, one of them under another occupation rather than under a group, the first
eight essential skills of each, every group above them, in six of the product's languages plus two the
product does not serve. It opens with the same class as a real snapshot
and imports through the same importer, so the integration suite exercises the code a deployment runs.

The content is ESCO data, European Commission, DG Employment, Social Affairs and Inclusion, reused under the
Creative Commons Attribution 4.0 International licence. See [`docs/taxonomy.md`](../../../../../docs/taxonomy.md).
