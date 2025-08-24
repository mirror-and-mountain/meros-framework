#!/bin/bash

FEATURE="$1"
NAMESPACE="$2"

if [[ -z "$FEATURE" ]]; then
    echo "Feature name argument missing."
    exit 1
fi

if [[ -z "$NAMESPACE" ]]; then
    echo "Namespace argument missing."
    exit 1
fi

if [[ -d "app/Features/$FEATURE" ]]; then
    echo "⚠️ Directory app/Features/$FEATURE already exists. Aborting."
    exit 1
fi

composer create-project mirror-and-mountain/meros-feature "app/Features/$FEATURE" --no-install || exit 1

cd "app/Features/$FEATURE" || exit 1

# Resolve absolute path to stub (relative to this script)
STUB_PATH="$(cd "$(dirname "${BASH_SOURCE[0]}")/../stubs" && pwd)/Feature.stub"

if [[ ! -f "$STUB_PATH" ]]; then
    echo "❌ Stub file not found at $STUB_PATH"
    exit 1
fi

# Escape values for sed
SAFE_FEATURE=$(printf '%s\n' "$FEATURE" | sed 's/[\/&]/\\&/g')
SAFE_NAMESPACE=$(printf '%s\n' "$NAMESPACE" | sed 's/[\/&]/\\&/g')

# Replace both placeholders
sed -e "s/{{NewFeature}}/$SAFE_FEATURE/g" -e "s/{{namespace}}/$SAFE_NAMESPACE/g" "$STUB_PATH" > "$FEATURE.php"

echo "✅ Feature created: app/Features/$FEATURE/$FEATURE.php"
