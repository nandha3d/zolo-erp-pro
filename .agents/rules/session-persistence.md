# Session Persistence & Crash Recovery Rule

## Core Directives for Agent Execution
1. **On Every Invocation / After Any Crash or Restart**:
   - The agent MUST immediately inspect SESSION_MEMORY.md located at the root of the workspace to load active context into memory.
   - Cross-check git status (git status, git branch -vv) to align in-memory state with the actual filesystem.
2. **Update on Milestones**:
   - Before ending a major session or whenever completing tasks, tests, or commits, the agent MUST update SESSION_MEMORY.md with:
     - The latest commit hash and message
     - Completed milestones and modified files
     - Current testing status
     - Next prioritized tasks
3. **Preserve Architectural Decisions**:
   - Never overwrite or discard established decisions (e.g. Zero Hardcoding Rule, database-backed dropdowns, inline modal creation [+], single shared ledger).
