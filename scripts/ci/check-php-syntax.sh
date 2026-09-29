#!/usr/bin/env bash
set -euo pipefail

root_dir="$(git rev-parse --show-toplevel)"
cd "$root_dir"

file_count=0
while IFS= read -r -d '' file; do
    file_count=$((file_count + 1))
    php -l "$file" >/dev/null
done < <(git ls-files --cached --others --exclude-standard -z -- '*.php')

if ((file_count == 0)); then
    echo 'No maintained PHP files found.' >&2
    exit 1
fi

echo "PHP syntax check passed for $file_count maintained files."
