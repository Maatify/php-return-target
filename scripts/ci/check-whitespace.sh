#!/usr/bin/env bash
set -euo pipefail

root_dir="$(git rev-parse --show-toplevel)"
cd "$root_dir"

empty_tree="$(git hash-object -t tree /dev/null)"
if ! git diff --check "$empty_tree" HEAD --; then
    echo 'Committed-tree whitespace errors detected.' >&2
    exit 1
fi

if ! git diff --check HEAD --; then
    echo 'Whitespace errors detected in staged or unstaged changes.' >&2
    exit 1
fi

while IFS= read -r -d '' file; do
    if file "$file" | grep -q 'text'; then
        diff_output=''
        diff_rc=0
        diff_output="$(git diff --no-index --check /dev/null "$file" 2>&1)" || diff_rc=$?
        if [[ "$diff_rc" -ne 1 || -n "$diff_output" ]]; then
            printf '%s\n' "$diff_output" >&2
            echo "Whitespace errors detected in untracked text file: $file." >&2
            exit 1
        fi
    fi
done < <(git ls-files --cached --others --exclude-standard -z)

echo 'Whitespace check passed for committed, staged, and unstaged repository content.'
