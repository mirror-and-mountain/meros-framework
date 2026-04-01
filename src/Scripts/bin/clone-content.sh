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
THEME_SLUG="$1"
DEST_ENV="$2"
DEST_URL="$3"
DEST_PATH="$4"
DEST_SSH_HOST="$5"
DEST_SSH_PORT="$6"
DEST_SSH_KEY="$7"

SOURCE_ENV="$8"
SOURCE_URL="$9"
SOURCE_PATH="${10}"
SOURCE_SSH_HOST="${11:-""}"
SOURCE_SSH_PORT="${12:-""}"
SOURCE_SSH_KEY="${13:-""}"

SOURCE_PREFIX="${14:-""}"
DEST_PREFIX="${15:-""}"
EXCLUDE_TABLES="${16:-""}"

# ---------------------------------------------------------------------
# Constants
# ---------------------------------------------------------------------
TMP_DIR=$(mktemp -d)
trap cleanup EXIT
trap on_error ERR
TMP_DB="$TMP_DIR/source-db.sql"
TMP_OPTIONS="$TMP_DIR/source-options.sql"

# ---------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------
    sync_files() {
        local directory="$1"
        if [ "$SOURCE_ENV" = "local_dev" ]; then
            # From Local to Remote
            rsync -avz \
                -e "ssh -i ${DEST_SSH_KEY} -p ${DEST_SSH_PORT}" \
                "${SOURCE_PATH}/wp-content/${directory}/" \
                "${DEST_SSH_HOST}:${DEST_PATH}/wp-content/${directory}/" \
                --delete
        elif [ "$DEST_ENV" = "local_dev" ]; then
            # From Remote to Local
            rsync -avz \
                -e "ssh -i ${SOURCE_SSH_KEY} -p ${SOURCE_SSH_PORT}" \
                "${SOURCE_SSH_HOST}:${SOURCE_PATH}/wp-content/${directory}/" \
                "${DEST_PATH}/wp-content/${directory}/" \
                --delete
        else
            # From Remote to Remote (Staged)
            TMP_DIR_FILES="$TMP_DIR/source-${directory}"
            mkdir -p "$TMP_DIR_FILES"

            rsync -avz \
                -e "ssh -i ${SOURCE_SSH_KEY} -p ${SOURCE_SSH_PORT}" \
                "${SOURCE_SSH_HOST}:${SOURCE_PATH}/wp-content/${directory}/" \
                "$TMP_DIR_FILES/"

            rsync -avz \
                -e "ssh -i ${DEST_SSH_KEY} -p ${DEST_SSH_PORT}" \
                "$TMP_DIR_FILES/" \
                "${DEST_SSH_HOST}:${DEST_PATH}/wp-content/${directory}/" \
                --delete
        fi
    }

# ---------------------------------------------------------------------
# 1: Export source database
# ---------------------------------------------------------------------
if [ "$SOURCE_ENV" = "local_dev" ]; then    
    # Export local database
    wp db export "$TMP_DB" \
        --path="$SOURCE_PATH" \
        --add-drop-table \
        --skip-themes \
        --exclude_tables="$EXCLUDE_TABLES"
else
    # Export remote database
    ssh -i "${SOURCE_SSH_KEY}" -p "${SOURCE_SSH_PORT}" "${SOURCE_SSH_HOST}" \
    "wp db export - \
        --path='${SOURCE_PATH}' \
        --add-drop-table \
        --skip-themes \
        --exclude_tables='$EXCLUDE_TABLES'" \
    > "$TMP_DB"
fi

# Configure table prefix replacement if needed
if [ -n "$DEST_PREFIX" ] && [ -n "$SOURCE_PREFIX" ]; then
    sed "s/\`$SOURCE_PREFIX/\`$DEST_PREFIX/g" "$TMP_DB" > "${TMP_DB}-prefixed"
    TMP_DB="${TMP_DB}-prefixed"
fi

# ---------------------------------------------------------------------
# 2: Import source database on destination
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
# 4: Clone theme - only if destination is remote
# ---------------------------------------------------------------------
if [ "$DEST_ENV" != "local_dev" ]; then
    THEME_SCRIPT="$(dirname "$0")/sync-theme.sh"
    bash "$THEME_SCRIPT" \
        "$THEME_SLUG" \
        "$DEST_ENV" \
        "$DEST_URL" \
        "$DEST_PATH" \
        "$DEST_SSH_HOST" \
        "$DEST_SSH_PORT" \
        "$DEST_SSH_KEY" \
        "$SOURCE_ENV" \
        "$SOURCE_URL" \
        "$SOURCE_PATH" \
        "$SOURCE_SSH_HOST" \
        "$SOURCE_SSH_PORT" \
        "$SOURCE_SSH_KEY" \
        "true" \
        "true"
fi
# ---------------------------------------------------------------------
# 5: Clone uploads directory
# ---------------------------------------------------------------------
sync_files "uploads"

# ---------------------------------------------------------------------
# 6: Clone plugins directory
# ---------------------------------------------------------------------
sync_files "plugins"
if [ "$DEST_ENV" = "local_dev" ]; then
    wp plugin activate --all --path="$DEST_PATH" --url="$DEST_URL"
else
    ssh -i "${DEST_SSH_KEY}" -p "${DEST_SSH_PORT}" "${DEST_SSH_HOST}" \
        "wp plugin activate --all --path='${DEST_PATH}' --url='${DEST_URL}'"
fi

# ---------------------------------------------------------------------
# 7: Clean up and search-replace
# ---------------------------------------------------------------------
if [ $DEST_ENV = "local_dev" ]; then
    wp search-replace "$SOURCE_URL" "$DEST_URL" --path="$DEST_PATH"
    wp rewrite flush --path="$DEST_PATH" --hard
    wp transient delete --all --path="$DEST_PATH"
    wp cache flush --path="$DEST_PATH"
else
    ssh -i "${DEST_SSH_KEY}" -p "${DEST_SSH_PORT}" "${DEST_SSH_HOST}" \
        "wp search-replace '$SOURCE_URL' '$DEST_URL' --path='${DEST_PATH}' && \
         wp rewrite flush --path='${DEST_PATH}' --hard && \
         wp transient delete --all --path='${DEST_PATH}' && \
         wp cache flush --path='${DEST_PATH}'"
fi