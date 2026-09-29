#!/usr/bin/env bash
set -euo pipefail

root_dir="$(git rev-parse --show-toplevel)"
consumer_dir="$root_dir/consumer-verification"

cleanup() {
    rm -rf "$consumer_dir/vendor" "$consumer_dir/composer.lock"
}
trap cleanup EXIT

run_number=0
while ((run_number < 2)); do
    run_number=$((run_number + 1))
    cleanup
    composer update --working-dir="$consumer_dir" --no-interaction --prefer-dist --no-progress
    composer check-platform-reqs --working-dir="$consumer_dir"

    installed_package="$consumer_dir/vendor/maatify/php-return-target"
    if [[ ! -d "$installed_package" || -L "$installed_package" ]]; then
        echo "Consumer package is missing or is a symlink: $installed_package" >&2
        exit 1
    fi

    php "$consumer_dir/verify.php"
    echo "Consumer verification run #$run_number passed."
    cleanup
done

echo 'Consumer verification passed twice from clean Composer consumer states.'
