## What changed

<!-- One or two sentences. What a reader needs to know before looking at the diff. -->

## What proves it

<!-- The test that fails without this change, and the command that runs it. A result without the command that
     produced it is not a result. -->

## Checklist

- [ ] A test fails without this change, and I ran it in the failing state first
- [ ] The test asserts observable behaviour, not the steps taken
- [ ] The full gate sequence passed locally, not a scoped run
- [ ] No finding was silenced with a suppression, an exclusion or a baseline entry
- [ ] No user-visible string was added outside a translation catalogue
- [ ] No field that invites discrimination was added
- [ ] Nothing here exposes a left swipe, a contact detail before a match, or a way to reject automatically
- [ ] A contract change updates the server, the frozen specification and every affected client in this diff
- [ ] An architecture decision record accompanies any change to a boundary, a trust boundary or a guarantee
