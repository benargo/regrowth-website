---
name: new-issue
description: Use when the user wants to file a GitHub issue — describes a bug, unexpected behavior, or missing feature, then needs root-cause analysis, relevant file links, and automatic issue creation via gh CLI.
---

# New Issue Skill

## Behavior

1. **Receive the issue description** from the user — what is broken, unexpected, or missing.
2. **Search the knowledge graph** using `/graphify` to locate relevant nodes (files, classes, functions) related to the reported problem.
3. **Investigate root cause** using `/systematic-debugging` — trace through the located nodes to identify the underlying cause.
4. **Write up the analysis** with markdown links to all relevant files and line numbers.
5. **Create the GitHub issue** via `gh issue create`.

## Step-by-Step

### 1. Receive Issue Description
Ask the user (or use what they provided) to describe:
- What is happening
- What should happen instead
- Any steps to reproduce

### 2. Search the Knowledge Graph
Invoke the graphify skill to find relevant nodes:
```
/graphify
```
Query with terms from the issue description. Identify the key files, classes, and functions involved.

### 3. Investigate Root Cause
Invoke systematic-debugging with the nodes identified above:
```
/systematic-debugging
```
Trace the execution path, identify where the behavior diverges from expectations, and form a clear hypothesis.

### 4. Write the Analysis
Produce a structured analysis that includes:
- A clear summary of the problem
- Root cause hypothesis with supporting evidence
- Links to every relevant file (use `[filename](path)` format relative to repo root)
- Suggested fix direction (optional but helpful)

### 5. Create the Issue
```bash
gh issue create \
  --title "<concise title>" \
  --body "$(cat <<'EOF'
## Problem
<what is happening vs what should happen>

## Root Cause Analysis
<hypothesis with evidence>

## Relevant Files
- [path/to/file.php](path/to/file.php) — reason it's relevant
- [path/to/other.jsx](path/to/other.jsx) — reason it's relevant

## Steps to Reproduce
<numbered steps if applicable>

## Suggested Fix
<optional direction>
EOF
)"
```

## Issue Template

```markdown
## Problem
Brief description of the issue and expected vs actual behavior.

## Root Cause Analysis
Hypothesis about where and why the failure occurs, with links to specific code.

## Relevant Files
- [app/Models/Example.php](app/Models/Example.php) — contains the affected logic
- [resources/js/Pages/Example.jsx](resources/js/Pages/Example.jsx) — frontend surface

## Steps to Reproduce
1. Step one
2. Step two

## Suggested Fix
Optional: where a fix should be applied and what it might look like.
```

## Requirements
- `graphify-out/graph.json` must exist (run `graphify update .` if missing)
- GitHub CLI (`gh`) installed and authenticated
- User has provided a description of the issue
