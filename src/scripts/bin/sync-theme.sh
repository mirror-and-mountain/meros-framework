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

# ---------------------------------------------------------------------
# Sync Operation - From Local to Remote
# ---------------------------------------------------------------------
if [ $SOURCE_ENV = 'local_dev' ]; then
    # Ensure theme directory exists on destination
    ssh -i "${DEST_SSH_KEY}" -p "${DEST_SSH_PORT}" "${DEST_SSH_HOST}" -o StrictHostKeyChecking=no \
        "[ -d '${DEST_PATH}/wp-content/themes/${THEME_SLUG}' ] || { \
            echo 'Destination theme directory does not exist. Creating...'; \
            mkdir -p '${DEST_PATH}/wp-content/themes/${THEME_SLUG}' || { \
                echo 'Error: Failed to create destination theme directory.'; \
                exit 1; \
            }; \
        }" || { \
        echo "Error: Failed to ensure destination theme directory on ${DEST_ENV}"; \
        exit 1; \
    }

    # Clear laravel caches
    wp acorn view:clear --path="${SOURCE_PATH}" --quiet || true
    wp acorn cache:clear --path="${SOURCE_PATH}" --quiet || true

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
            --exclude='/package.json' \
            --exclude='/package-lock.json' \
            --exclude='/webpack.assets.config.js' \
            --exclude='/config/environments.php' \
            --exclude='/storage/logs/laravel.log' \
            --exclude='/vendor/mirror-and-mountain/.DS_Store' \
            --exclude='/vendor/mirror-and-mountain/**/.DS_Store' \
            --exclude='/vendor/mirror-and-mountain/**/.git/' \
            --exclude='/vendor/mirror-and-mountain/**/.gitattributes' \
            --exclude='/vendor/mirror-and-mountain/**/.gitignore' \
            --exclude='/vendor/mirror-and-mountain/**/package.json' \
            --exclude='/vendor/mirror-and-mountain/**/package-lock.json' \
            --exclude='/vendor/mirror-and-mountain/**/node_modules/' \
            --exclude='/vendor/mirror-and-mountain/**/webpack.assets.config.js' \
            --exclude='/vendor/mirror-and-mountain/meros-framework/src/scripts/EnvironmentCommands.php' \
            --exclude='/vendor/mirror-and-mountain/meros-framework/src/scripts/EnvironmentCommands.php' \
            --exclude='/vendor/mirror-and-mountain/meros-framework/src/scripts/EnvironmentManager.php' \
            --exclude='/vendor/mirror-and-mountain/meros-framework/src/scripts/bin/' \
            --delete \
            --delete-excluded

    echo "Activating theme '${THEME_SLUG}' on destination..."
    ssh -i "${DEST_SSH_KEY}" -p "${DEST_SSH_PORT}" "${DEST_SSH_HOST}" -o StrictHostKeyChecking=no \
        "wp theme activate '${THEME_SLUG}' --url='${DEST_URL}' --path='${DEST_PATH}' --quiet"
    
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

    echo "Activating theme '${THEME_SLUG}' on destination..."
    ssh -i "${DEST_SSH_KEY}" -p "${DEST_SSH_PORT}" "${DEST_SSH_HOST}" -o StrictHostKeyChecking=no \
        "wp theme activate '${THEME_SLUG}' --url='${DEST_URL}' --path='${DEST_PATH}' --quiet"
fi
