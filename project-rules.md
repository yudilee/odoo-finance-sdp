# Project Rules & Guidelines for AI Assistant

This document contains persistent rules that the AI assistant must follow when working on this project.

## 1. Always Create a Local Git Backup
**CRITICAL RULE:** Before writing any new code, building a new feature, or attempting any major refactoring, you MUST run a local git commit to back up the current stable state of the project. 

**Instructions for AI:**
1. Run `git status` to check for any uncommitted work.
2. If there is uncommitted work (code that hasn't been locked in yet), run `git add .` and `git commit -m "Backup: Save stable state before starting new task"` to safely lock it into Git history.
3. Only AFTER the local commit is successfully created may you begin modifying the codebase.
4. If your new changes break the system, use Git to revert your changes back to the safe commit rather than trying to manually undo your code line-by-line.
5. Acknowledge these rules: Every time you start a new conversation, your first message MUST include a brief confirmation that you have read and will follow these project rules.
6. **Backup Before Changes**: You MUST ALWAYS create a local git commit of the current state BEFORE you make any significant changes or begin a new task. Run `git add .` and `git commit -m "Backup: Save stable state before starting new task"`. Never skip this step. This ensures we can easily rollback if an experiment fails. Do not ask the user for permission to do this, just do it.

## Automations

1. **New Task Trigger**: If the user says "new task", you MUST automatically run `git add .` and `git commit -m "Backup: Save stable state before starting new task"` BEFORE discussing or planning the new feature.

2. **Sign Off Command**: If the user says "sign off" (or similar), you MUST automatically:
   - Run `git add .` and `git commit -m "chore: save end of session state"` (if there are uncommitted changes).
   - Generate a markdown file named `session_log_YYYY-MM-DD.md` in the project root.
   - Summarize all the work done, bugs fixed, and exact technical choices made during the current session into that file.
   - The user will use this file to quickly bring the AI up to speed in the next session by dragging and dropping it into the chat.
