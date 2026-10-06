# Agent Workspace Guidelines & Session Persistence

This repository uses a persistent session memory protocol to guarantee zero context loss across IDE crashes, reboots, or conversation resets.

## Mandatory Initialization Routine
Whenever starting a new turn, task, or after a system crash:
1. **Read Session Memory:** Open and read [SESSION_MEMORY.md](SESSION_MEMORY.md).
2. **Verify Repository State:** Run git status and verify branch enhanced-ui.
3. **Execute Active Tasks:** Proceed from the documented "Crash Recovery Protocol" and "Next Steps" in SESSION_MEMORY.md.
4. **Persist State:** Keep SESSION_MEMORY.md updated as changes occur.
