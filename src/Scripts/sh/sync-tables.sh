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
EXCLUDE_TABLES="${16:-""}"
ADD_DROP_TABLE="${17:-"false"}"
THEME_OPTIONS="${18:-""}"

# ---------------------------------------------------------------------
# Constants
# ---------------------------------------------------------------------
TMP_DIR=$(mktemp -d)
trap cleanup EXIT
trap on_error ERR
TMP_DB="$TMP_DIR/source-db.sql"
EXCLUDE_TABLES_PARAM="--exclude_tables=$EXCLUDE_TABLES"

# ---------------------------------------------------------------------
# Export source database from source environment
# ---------------------------------------------------------------------
if [ "$SOURCE_ENV" = "local_dev" ]; then    
    # Export local database
    if [ "$TABLES" = "all" ]; then
        wp db export "$TMP_DB" \
            --path="$SOURCE_PATH" \
            $( [ "$ADD_DROP_TABLE" = "true" ] && echo "--add-drop-table" ) \
            --skip-themes \
            $EXCLUDE_TABLES_PARAM
    else
        wp db export "$TMP_DB" \
            --path="$SOURCE_PATH" \
            $( [ "$ADD_DROP_TABLE" = "true" ] && echo "--add-drop-table" ) \
            --skip-themes \
            --tables="$TABLES"
    fi
else
    # Export remote database
    if [ "$TABLES" = "all" ]; then
        ssh -i "${SOURCE_SSH_KEY}" -p "${SOURCE_SSH_PORT}" "${SOURCE_SSH_HOST}" \
        "wp db export - \
            --path='${SOURCE_PATH}' \
            $( [ \"$ADD_DROP_TABLE\" = \"true\" ] && echo \"--add-drop-table\" ) \
            --skip-themes \
            $EXCLUDE_TABLES_PARAM" \
        > "$TMP_DB"
    else
        ssh -i "${SOURCE_SSH_KEY}" -p "${SOURCE_SSH_PORT}" "${SOURCE_SSH_HOST}" \
        "wp db export - \
            --path='${SOURCE_PATH}' \
            $( [ \"$ADD_DROP_TABLE\" = \"true\" ] && echo \"--add-drop-table\" ) \
            --skip-themes \
            --tables='${TABLES}'" \
        > "$TMP_DB"
    fi
fi

# Configure table prefix replacement if needed
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
    wp db import "$TMP_DB" --path="$DEST_PATH"

else
    # Handle remote import
    cat "$TMP_DB" | ssh -i "${DEST_SSH_KEY}" -p "${DEST_SSH_PORT}" "${DEST_SSH_HOST}" \
        "wp db import - --path='${DEST_PATH}'"
fi

# ---------------------------------------------------------------------
# Update theme options
# ---------------------------------------------------------------------
if [ -n "$THEME_OPTIONS" ]; then
    if [ "$DEST_ENV" = "local_dev" ]; then
        IFS=',' read -ra OPTIONS <<< "$THEME_OPTIONS"
        for opt in "${OPTIONS[@]}"; do
            wp option update "$opt" "$(wp option get "$opt" --path="$SOURCE_PATH")" --path="$DEST_PATH"
        done
    else 
        IFS=',' read -ra OPTIONS <<< "$THEME_OPTIONS"
        for opt in "${OPTIONS[@]}"; do
            VALUE=$(ssh -i "${SOURCE_SSH_KEY}" -p "${SOURCE_SSH_PORT}" "${SOURCE_SSH_HOST}" \
                "wp option get '$opt' --path='${SOURCE_PATH}'")
            ssh -i "${DEST_SSH_KEY}" -p "${DEST_SSH_PORT}" "${DEST_SSH_HOST}" \
                "wp option update '$opt' '$VALUE' --path='${DEST_PATH}'"
        done
    fi
fi