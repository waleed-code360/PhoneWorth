#!/bin/sh
set -eu
root=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
find "$root/public" "$root/includes" "$root/scripts" -type f -name '*.php' -print | while IFS= read -r f; do
    php -l "$f" >/dev/null || exit 1
done
if command -v node >/dev/null 2>&1; then
    node --check "$root/public/js/phone.js"
    node --check "$root/public/js/questions.js"
fi
php "$root/tests/test_calculation.php"
printf 'Source syntax and pricing checks completed.\n'
