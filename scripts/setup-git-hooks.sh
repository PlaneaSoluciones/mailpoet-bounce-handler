#!/usr/bin/env bash
set -e

HOOK_DIR=".git/hooks"
HOOK_FILE="$HOOK_DIR/pre-commit"
SCRIPT="scripts/pre-commit.sh"

if [ ! -d "$HOOK_DIR" ]; then
    echo "No .git directory found. Skipping git hook installation."
    exit 0
fi

cp "$SCRIPT" "$HOOK_FILE"
chmod +x "$HOOK_FILE"
echo "Git pre-commit hook installed."
