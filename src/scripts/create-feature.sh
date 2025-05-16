#!/bin/bash

FEATURE="$1"

if [[ -z "$FEATURE" ]]; then
    echo "Feature name argument missing."
    exit 1
fi

if [[ -d "app/Features/$FEATURE" ]]; then
    echo "⚠️ Directory app/Features/$FEATURE already exists. Aborting."
    exit 1
fi

composer create-project mirror-and-mountain/meros-feature "app/Features/$FEATURE" --no-install || exit 1

cd "app/Features/$FEATURE" || exit 1

SAFE_FEATURE=$(printf '%s\n' "$FEATURE" | sed 's/[]\/$*.^[]/\\&/g')
sed "s/{{NewFeature}}/$SAFE_FEATURE/g" Feature.stub > "$FEATURE.php"

echo "✅ Feature created: app/Features/$FEATURE/$FEATURE.php"
