#!/usr/bin/env bash
set -euo pipefail

root_dir="$(git rev-parse --show-toplevel)"
cd "$root_dir"

if git grep -nI -E '[[:blank:]]$' HEAD --; then
    echo 'Committed trailing whitespace detected.' >&2
    exit 1
fi

if ! git diff --check HEAD --; then
    echo 'Whitespace errors detected in staged or unstaged changes.' >&2
    exit 1
fi

while IFS= read -r -d '' file; do
    if file "$file" | grep -q 'text'; then
        if grep -nI -E '[[:blank:]]$' "$file"; then
            echo "Trailing whitespace detected in $file." >&2
            exit 1
        fi
    fi
done < <(git ls-files --cached --others --exclude-standard -z)

echo 'Whitespace check passed for committed, staged, and unstaged repository content.'
