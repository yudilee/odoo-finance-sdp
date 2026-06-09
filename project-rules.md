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
