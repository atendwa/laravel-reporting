#!/usr/bin/env bash
set -euo pipefail

# Only fully staged files, so re-adding the fixes below doesn't also stage unrelated unstaged edits.
staged_files() {
    git diff --cached --name-only --diff-filter=ACMR -- "$@" | while read -r file; do
        [ -f "$file" ] && git diff --quiet -- "$file" && printf '%s\n' "$file"
    done
}

mapfile -t formatted < <(staged_files '*.blade.php' '*.js' '*.css')
if [ ${#formatted[@]} -gt 0 ]; then
    if [ -x node_modules/.bin/prettier ]; then
        node_modules/.bin/prettier --write --log-level warn "${formatted[@]}"
        git add -- "${formatted[@]}"
    else
        echo "Prettier is not installed: run npm install to format Blade, JS and CSS before committing." >&2
    fi
fi

mapfile -t staged < <(staged_files '*.php' ':!*.blade.php')
[ ${#staged[@]} -eq 0 ] && exit 0

./vendor/bin/rector process --no-progress-bar "${staged[@]}"
./vendor/bin/pint --quiet "${staged[@]}"

git add -- "${staged[@]}"
