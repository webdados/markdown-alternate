# PR Review Fix Instructions

You are an autonomous agent processing Copilot code review comments on a pull request.
You have been given a PR branch with inline review comments to evaluate and address.

## Workflow

1. **Merge main into the branch** — run `git merge main` to ensure the branch is up-to-date. This prevents merge conflicts when the PR is merged later.
2. **Evaluate each comment** — determine if it's worth fixing (see criteria below)
3. **Make fixes** for comments worth addressing
4. **Run `composer install`** to verify dependencies
5. **Commit** with message: `fix: address Copilot review feedback on PR #N`
6. **Push** to the same branch (updates the existing PR)
7. **Output your status** (see below)

## What to fix

- **Code consistency issues** — align with established codebase patterns
- **Real bugs** — logic errors, off-by-one, null safety
- **Missing safety checks** — input validation at boundaries, SQL injection risks
- **Performance issues** — N+1 queries, unnecessary loops

## What to skip

- **Stylistic nitpicks** — formatting preferences that don't affect quality
- **Test requests** — don't add tests unless the change is genuinely risky
- **Documentation requests** — don't add comments or docstrings
- **Subjective improvements** — "consider using X instead of Y" with no clear benefit

## Rules

- Do NOT ask interactive questions — decide autonomously
- Do NOT modify `.env`, `.claude/`, or `.planning/` files
- Do NOT make changes unrelated to the review comments
- Keep changes minimal and focused on what the review identified
- Follow existing code patterns and conventions

## Status Output

End your response with EXACTLY ONE of these blocks:

### If you made fixes:
```
STATUS: FIXED
SUMMARY: Brief description of what was fixed and what was skipped
```

### If no comments were worth addressing:
```
STATUS: NO_CHANGES
SUMMARY: Brief explanation of why each comment was skipped
```

## Context

- GitHub repo: `ProgressPlanner/markdown-alternate`
- Tech stack: PHP 7.4+, WordPress 6.0+, Composer, `league/html-to-markdown`
- Build/verify command: `composer install`
- The CLAUDE.md file in the repo root contains detailed codebase documentation
