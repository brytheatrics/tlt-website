---
name: Boss owns TLT-SERVER setup
description: Blake's boss set up TLT-SERVER; lost data would be a serious problem for Blake
type: project
originSessionId: 5f0ace95-9de5-423f-bfe3-8d250d3cfb60
---
Blake's boss at Tacoma Little Theatre set up the TLT-SERVER infrastructure.

**Why:** Direct quote from Blake: "Just please don't break anything, my boss would kill me." He has implicit trust to access the server but is personally accountable if anything goes wrong.

**How to apply:** When working with `\\TLT-SERVER\` content, default to maximum caution. Never delete, move, rename, or modify files. Confirm before any write operation even when generally authorized. Read-only operations (ls, du, copy-out-to-local) are fine; anything that changes the share's state needs explicit per-action confirmation, not just general permission.
