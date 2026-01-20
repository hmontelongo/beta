---
description: Run two-step PropData code review
---

Review recent code changes in two steps:

## Step 1: PropData Review
Use the propdata-reviewer agent to analyze files changed since last commit. Focus on:
- Unnecessary complexity
- Laravel convention violations
- Files that shouldn't exist
- PropData-specific patterns

## Step 2: Laravel Pint
After the propdata-reviewer finishes, run:
```bash
vendor/bin/pint --dirty
```

Show results from both steps.
