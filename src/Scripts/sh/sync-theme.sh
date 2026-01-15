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

MAKE_DIR="${14:-"false"}"
ACTIVATE="${15:-"false"}"

# ---------------------------------------------------------------------
# Sync Operation - From Local to Remote
# ---------------------------------------------------------------------
if [ $SOURCE_ENV = 'local_dev' ]; then
    # Ensure theme directory exists on destination
    ssh -i "${DEST_SSH_KEY}" -p "${DEST_SSH_PORT}" "${DEST_SSH_HOST}" -o StrictHostKeyChecking=no \
        "[ -d '${DEST_PATH}/wp-content/themes/${THEME_SLUG}' ] || { \
            if [ '${MAKE_DIR}' = 'true' ]; then \
                echo 'Destination theme directory does not exist. Creating...'; \
                mkdir -p '${DEST_PATH}/wp-content/themes/${THEME_SLUG}' || {
                    echo 'Error: Failed to create destination theme directory.'; \
                    exit 1; \
                }; \
            else \
                echo 'Error: Destination theme directory does not exist.'; \
                exit 1; \
            fi \
        }"

    rsync -avz \
        -e "ssh -i ${DEST_SSH_KEY} -p ${DEST_SSH_PORT} -o StrictHostKeyChecking=no" \
        "${SOURCE_PATH}/wp-content/themes/${THEME_SLUG}/" \
        "${DEST_SSH_HOST}:${DEST_PATH}/wp-content/themes/${THEME_SLUG}/" \
            --exclude='/.devcontainer/' \
            --exclude='/.git/' \
            --exclude='/.vscode/' \
            --exclude='/node_modules/' \
            --exclude='/.DS_Store' \
            --exclude='/.gitattributes' \
            --exclude='/.gitignore' \
            --exclude='/composer.json' \
            --exclude='/composer.lock' \
            --exclude='/package.json' \
            --exclude='/package-lock.json' \
            --exclude='/webpack.assets.config.js' \
            --exclude='/storage/logs/laravel.log' \
            --exclude='/config/environments.php' \
            --exclude='/app/Features/**/assets/src/**' \
            --exclude='/app/Features/**/blocks/src/**' \
            --exclude='**/.git/**' \
            --exclude='**/.vscode/**' \
            --exclude='**/node_modules/**' \
            --exclude='**/.DS_Store' \
            --exclude='**/.gitattributes' \
            --exclude='**/.gitignore' \
            --exclude='**/composer.json' \
            --exclude='**/composer.lock' \
            --exclude='**/package.json' \
            --exclude='**/package-lock.json' \
            --exclude='**/webpack.assets.config.js' \
            --exclude='**/storage/logs/laravel.log' \
            --delete \
            --delete-excluded

    if [ "${ACTIVATE}" = "true" ]; then
        echo "Activating theme '${THEME_SLUG}' on destination..."
        ssh -i "${DEST_SSH_KEY}" -p "${DEST_SSH_PORT}" "${DEST_SSH_HOST}" -o StrictHostKeyChecking=no \
            "wp theme activate '${THEME_SLUG}' --url='${DEST_URL}' --path='${DEST_PATH}' --quiet"
    fi
    
# ---------------------------------------------------------------------
# Sync Operation - From Remote to Remote (Staged)
# ---------------------------------------------------------------------
else 
    echo "Performing staged sync of theme '${THEME_SLUG}' from '${SOURCE_ENV}' to '${DEST_ENV}'..."
    TMP_DIR=$(mktemp -d)
    trap cleanup EXIT
    trap on_error ERR
    mkdir -p "$TMP_DIR/remote-theme"

    # Ensure source theme exists
    ssh -i "${SOURCE_SSH_KEY}" -p "${SOURCE_SSH_PORT}" "${SOURCE_SSH_HOST}" -o StrictHostKeyChecking=no \
        "[ -d '${SOURCE_PATH}/wp-content/themes/${THEME_SLUG}' ]" || {
            echo "Error: Source theme directory does not exist."
            exit 1
        }

    # Pull source remote to local temp
    rsync -avz \
        -e "ssh -i ${SOURCE_SSH_KEY} -p ${SOURCE_SSH_PORT}" \
        "${SOURCE_SSH_HOST}:${SOURCE_PATH}/wp-content/themes/${THEME_SLUG}/" \
        "$TMP_DIR/remote-theme/"

    # Push local temp to dest remote
    rsync -avz \
        -e "ssh -i ${DEST_SSH_KEY} -p ${DEST_SSH_PORT}" \
        "$TMP_DIR/remote-theme/" \
        "${DEST_SSH_HOST}:${DEST_PATH}/wp-content/themes/${THEME_SLUG}/" \
        --delete

    if [ "${ACTIVATE}" = "true" ]; then
        echo "Activating theme '${THEME_SLUG}' on destination..."
        ssh -i "${DEST_SSH_KEY}" -p "${DEST_SSH_PORT}" "${DEST_SSH_HOST}" -o StrictHostKeyChecking=no \
            "wp theme activate '${THEME_SLUG}' --url='${DEST_URL}' --path='${DEST_PATH}' --quiet"
    fi
fi
