# Contract

> **Empty until M4.** What follows describes what will live here and why, not what is here now.

The OpenAPI 3.1 specification the server generates, frozen here, and the checks that keep it honest.

Three clients depend on this file: the web application, the Android application and the iOS application. The
Kotlin and Swift clients are generated from it rather than written by hand.

Three checks protect it, and they are the reason this product lives in one repository ([ADR-0001](../docs/adr/0001-single-repository.md)):

- A test fails when the specification the server generates differs from the file frozen here.
- A comparator refuses a breaking change that was not declared.
- The framework's own detector covers the PHP surface behind it.

A change to the contract, its server implementation and every affected client belongs in one commit.

Populated in **M4**, when the first endpoints the mobile clients consume exist.
