# AGENT INSTRUCTIONS

Use this directory as the single source of truth for agent execution notes and progress tracking.

## Files and Purpose
- `AGENT_OVERVIEW.md`: high-level phase plan for the project.
- `AGENT_THINKING.md`: per-pass working notes with:
  - current step
  - immediate next step
- `AGENT_LOG.md`: serialized (JSONL) pass updates after each pass.

## Workflow Rules
1. **Before implementation work**
   - Review `AGENT_OVERVIEW.md` and align current work to the active phase.
2. **During each pass**
   - Update `AGENT_THINKING.md` with concise current and immediate next steps.
3. **After each pass**
   - Append exactly one serialized log entry to `AGENT_LOG.md` including:
     - `pass`
     - `status`
     - `focus`
     - `completed`
     - `next`
     - `timestamp_utc`
4. **When plan changes**
   - Update `AGENT_OVERVIEW.md` to reflect revised phases/scope.
5. **Consistency**
   - Keep timestamps in UTC ISO-8601 format.
   - Keep entries brief and implementation-oriented.

## Maintenance Guidance
- Do not delete historical pass logs; append new entries.
- If a pass is retried, log a new entry rather than rewriting prior entries.
- Keep this directory version-controlled with all project changes.
