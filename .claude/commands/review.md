Perform a two-step code review on files changed since last commit:

## Step 1: PropData Review
Use the propdata-reviewer agent to check:
- Unnecessary complexity
- Files that shouldn't exist
- PropData-specific patterns
- Laravel convention violations

## Step 2: Laravel Simplifier
After step 1, use the laravel-simplifier agent to:
- Apply PSR-12 standards
- Simplify verbose code
- Add missing return types

Show results of both steps.
