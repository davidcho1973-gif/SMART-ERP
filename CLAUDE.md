@AGENTS.md

# Claude Code instructions

- Work only from an assigned GitHub issue and an isolated `claude/<issue>-<slug>` branch or worktree.
- Use plan mode before changing authentication, RBAC, payroll, accounting, attendance, GPS, uploads, migrations, or deployment behavior.
- Do not push directly to `main` or `production`, change branch protection, merge a PR, or deploy without explicit human approval.
- Do not edit a branch currently assigned to Codex. Review Codex PRs from the diff and leave findings before proposing fixes.
- Keep changes to `WorkforceApp.php`, `ViewModel.php`, and translation dictionaries as narrow as possible because they are shared hotspots.
- Before finishing, run the repository verification commands from `AGENTS.md` and report changed files, test results, risks, and rollback notes.
