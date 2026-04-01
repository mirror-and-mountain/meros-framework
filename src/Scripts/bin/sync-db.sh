#!/bin/bash
set -e

cleanup() {
    [ -n "$TMP_DIR" ] && rm -rf "$TMP_DIR"
}

on_error() {
    echo "Error on line $LINENO on host: $(hostname)"
}

# ---------------------------------------------------------------------
# Parameters
# ---------------------------------------------------------------------
DEST_ENV="$1"
DEST_URL="$2"
DEST_PATH="$3"
DEST_SSH_HOST="$4"
DEST_SSH_PORT="$5"
DEST_SSH_KEY="$6"

SOURCE_ENV="$7"
SOURCE_URL="$8"
SOURCE_PATH="$9"
SOURCE_SSH_HOST="${10:-""}"
SOURCE_SSH_PORT="${11:-""}"
SOURCE_SSH_KEY="${12:-""}"

SOURCE_PREFIX="${13:-""}"
DEST_PREFIX="${14:-""}"
TABLES="${15:-""}"
OPTIONS="${16:-""}"
MAINTAIN_OPTIONS="${17:-""}"
ADD_DROP_TABLE="${18:-"false"}"
SEARCH_REPLACE="${19:-"true"}"

# ---------------------------------------------------------------------
# Constants
# ---------------------------------------------------------------------
TMP_DIR=$(mktemp -d)
trap cleanup EXIT
trap on_error ERR
TMP_DB="$TMP_DIR/source-db.sql"

# Prepare drop table flag
if [ "$ADD_DROP_TABLE" = "true" ]; then
    ADD_DROP_TABLE_FLAG="--add-drop-table"
else
    ADD_DROP_TABLE_FLAG=""
fi

# ---------------------------------------------------------------------
# Export source database from source environment
# ---------------------------------------------------------------------
if [ "$SOURCE_ENV" = "local_dev" ]; then    
    # Export local database
    echo "Exporting source database from local host..."
    wp db export "$TMP_DB" \
    --path="$SOURCE_PATH" \
    --skip-themes \
    --tables="$TABLES" \
    $ADD_DROP_TABLE_FLAG
else
    # Export remote database
    echo "Exporting source database from remote host..."
    ssh -i "${SOURCE_SSH_KEY}" -p "${SOURCE_SSH_PORT}" "${SOURCE_SSH_HOST}" -o StrictHostKeyChecking=no \
    "wp db export - \
        --path='${SOURCE_PATH}' \
        --skip-themes \
        --defaults=true \
        --tables="$TABLES" \
        $ADD_DROP_TABLE_FLAG" \
    > "$TMP_DB"
fi

# Configure table prefix replacement if needed
echo "Configuring table prefix replacements..."
if [ -n "$DEST_PREFIX" ] && [ -n "$SOURCE_PREFIX" ]; then
    sed "s/\`$SOURCE_PREFIX/\`$DEST_PREFIX/g" "$TMP_DB" > "${TMP_DB}-prefixed"
    TMP_DB="${TMP_DB}-prefixed"
fi

# ---------------------------------------------------------------------
# Import source database on destination
# ---------------------------------------------------------------------
if [ ! -f "$TMP_DB" ]; then
    echo "Error: Source database export file not found."
    exit 1
fi

# Handle local import
if [ "$DEST_ENV" = "local_dev" ]; then
    # Import database
    echo "Importing database to local host..."
    wp db import "$TMP_DB" --path="$DEST_PATH"

else
    # Handle remote import
    echo "Importing database on remote host..."
    cat "$TMP_DB" | ssh -i "${DEST_SSH_KEY}" -p "${DEST_SSH_PORT}" "${DEST_SSH_HOST}" -o StrictHostKeyChecking=no \
        "wp db import - --path='${DEST_PATH}'"
fi

# ---------------------------------------------------------------------
# Update options if specified
# ---------------------------------------------------------------------
if [ -n "$OPTIONS" ]; then
    echo "Updating theme options..."
    if [ "$DEST_ENV" = "local_dev" ]; then
        IFS=',' read -ra OPTIONS <<< "$OPTIONS"
        for opt in "${OPTIONS[@]}"; do
            VALUE=$(ssh -i "${SOURCE_SSH_KEY}" -p "${SOURCE_SSH_PORT}" "${SOURCE_SSH_HOST}" -o StrictHostKeyChecking=no \
                "wp option get '$opt' --path='${SOURCE_PATH}'" 2>/dev/null || true)

            if [ -n "$VALUE" ]; then
                wp option update "$opt" "$VALUE" --path="$DEST_PATH"
            fi
        done
    elif [ "$SOURCE_ENV" = "local_dev" ]; then
        IFS=',' read -ra OPTIONS <<< "$OPTIONS"
        for opt in "${OPTIONS[@]}"; do
            VALUE=$(wp option get "$opt" --path="$SOURCE_PATH" 2>/dev/null || true)

            if [ -n "$VALUE" ]; then
                ssh -i "${DEST_SSH_KEY}" -p "${DEST_SSH_PORT}" "${DEST_SSH_HOST}" -o StrictHostKeyChecking=no \
                    "wp option update '$opt' '$VALUE' --path='${DEST_PATH}'"
            fi
        done
    else
        IFS=',' read -ra OPTIONS <<< "$OPTIONS"
        for opt in "${OPTIONS[@]}"; do
            VALUE=$(ssh -i "${SOURCE_SSH_KEY}" -p "${SOURCE_SSH_PORT}" "${SOURCE_SSH_HOST}" -o StrictHostKeyChecking=no \
                "wp option get '$opt' --path='${SOURCE_PATH}'" 2>/dev/null || true)
            
            if [ -n "$VALUE" ]; then
                ssh -i "${DEST_SSH_KEY}" -p "${DEST_SSH_PORT}" "${DEST_SSH_HOST}" -o StrictHostKeyChecking=no \
                    "wp option update '$opt' '$VALUE' --path='${DEST_PATH}'"
            fi
        done
    fi
fi

# ---------------------------------------------------------------------
# Maintain options on the destination if specified
# ---------------------------------------------------------------------
if [ -n "$MAINTAIN_OPTIONS" ]; then
    echo "Maintaining specified options on destination..."
    
    if [ "$DEST_ENV" = "local_dev" ]; then
        echo "$MAINTAIN_OPTIONS" | jq -r 'to_entries[] | "\(.key)|\(.value)"' | while IFS='|' read -r opt value; do
            echo "Maintaining option '$opt' with value '$value' on local destination"
            wp option update "$opt" "$value" --path="$DEST_PATH"
        done
    else
        echo "$MAINTAIN_OPTIONS" | jq -r 'to_entries[] | "\(.key)|\(.value)"' | while IFS='|' read -r opt value; do
            echo "Maintaining option '$opt' with value '$value' on remote destination"
            ssh -i "${DEST_SSH_KEY}" -p "${DEST_SSH_PORT}" "${DEST_SSH_HOST}" -o StrictHostKeyChecking=no \
                "wp option update '$opt' '$value' --path='${DEST_PATH}'"
        done
    fi
fi

# ---------------------------------------------------------------------
# Clean up and search and replace URLs if needed
# ---------------------------------------------------------------------
echo "Cleaning up..."
if [ "$DEST_ENV" = "local_dev" ]; then
    wp cache flush --path="$DEST_PATH"
    wp transient delete --all --path="$DEST_PATH"
    wp rewrite flush --path="$DEST_PATH"

    if [ "$SEARCH_REPLACE" = "true" ]; then
        echo "Performing search and replace of URLs..."
        wp search-replace "$SOURCE_URL" "$DEST_URL" --path="$DEST_PATH" --quiet
    fi
else
    ssh -i "${DEST_SSH_KEY}" -p "${DEST_SSH_PORT}" "${DEST_SSH_HOST}" -o StrictHostKeyChecking=no \
        "wp cache flush --path='${DEST_PATH}' && wp transient delete --all --path='${DEST_PATH}' && wp rewrite flush --path='${DEST_PATH}'"
    
    if [ "$SEARCH_REPLACE" = "true" ]; then
        echo "Performing search and replace of URLs on remote host..."
        ssh -i "${DEST_SSH_KEY}" -p "${DEST_SSH_PORT}" "${DEST_SSH_HOST}" -o StrictHostKeyChecking=no \
            "wp search-replace '$SOURCE_URL' '$DEST_URL' --path='${DEST_PATH}' --quiet"
    fi
fi