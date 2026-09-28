#!/usr/bin/env bash
set -euo pipefail

root_dir="$(git rev-parse --show-toplevel)"
cd "$root_dir"

case "$(uname -s):$(uname -m)" in
    Linux:x86_64) asset='linux_amd64'; checksum='8aca8db96f1b94770f1b0d72b6dddcb1ebb8123cb3712530b08cc387b349a3d8' ;;
    Linux:aarch64|Linux:arm64) asset='linux_arm64'; checksum='325e971b6ba9bfa504672e29be93c24981eeb1c07576d730e9f7c8805afff0c6' ;;
    Darwin:x86_64) asset='darwin_amd64'; checksum='5b44c3bc2255115c9b69e30efc0fecdf498fdb63c5d58e17084fd5f16324c644' ;;
    Darwin:arm64) asset='darwin_arm64'; checksum='aba9ced2dee8d27fecca3dc7feb1a7f9a52caefa1eb46f3271ea66b6e0e6953f' ;;
    *) echo "Unsupported actionlint platform: $(uname -s):$(uname -m)." >&2; exit 1 ;;
esac

temp_dir="$(mktemp -d)"
trap 'rm -rf "$temp_dir"' EXIT

archive="$temp_dir/actionlint.tar.gz"
url="https://github.com/rhysd/actionlint/releases/download/v1.7.12/actionlint_1.7.12_${asset}.tar.gz"
curl --fail --location --silent --show-error --output "$archive" "$url"
printf '%s  %s\n' "$checksum" "$archive" | shasum --algorithm 256 --check --status
tar -xzf "$archive" -C "$temp_dir"

"$temp_dir/actionlint" -version
while IFS= read -r -d '' workflow_file; do
    "$temp_dir/actionlint" "$workflow_file"
done < <(find .github/workflows -type f \( -name '*.yml' -o -name '*.yaml' \) -print0)
