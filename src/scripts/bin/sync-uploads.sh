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
SOURCE_PATH="${9}"
SOURCE_SSH_HOST="${10:-""}"
SOURCE_SSH_PORT="${11:-""}"
SOURCE_SSH_KEY="${12:-""}"

# ---------------------------------------------------------------------
# Sync Operation - From Local to Remote
# ---------------------------------------------------------------------
if [ $SOURCE_ENV = 'local_dev' ]; then
    # Ensure uploads directory exists on destination
    ssh -i "${DEST_SSH_KEY}" -p "${DEST_SSH_PORT}" "${DEST_SSH_HOST}" -o StrictHostKeyChecking=no \
        "[ -d '${DEST_PATH}/wp-content/uploads' ] || { \
            echo 'Destination uploads directory does not exist. Creating...'; \
            mkdir -p '${DEST_PATH}/wp-content/uploads' || {
                echo 'Error: Failed to create destination uploads directory.'; \
                exit 1; \
            }; \
        }" || { \
        echo "Error: Failed to ensure destination uploads directory on ${DEST_ENV}"; \
        exit 1; \
    }

    rsync -avz \
        -e "ssh -i ${DEST_SSH_KEY} -p ${DEST_SSH_PORT} -o StrictHostKeyChecking=no" \
        "${SOURCE_PATH}/wp-content/uploads/" \
        "${DEST_SSH_HOST}:${DEST_PATH}/wp-content/uploads/" \
            --delete

# ---------------------------------------------------------------------
# Sync Operation - From Remote to Remote (Staged)
# ---------------------------------------------------------------------
else 
    echo "Performing staged sync of uploads from '${SOURCE_ENV}' to '${DEST_ENV}'..."
    TMP_DIR=$(mktemp -d)
    trap cleanup EXIT
    trap on_error ERR
    mkdir -p "$TMP_DIR/remote-uploads"

    # Ensure destination uploads directory exists
    ssh -i "${DEST_SSH_KEY}" -p "${DEST_SSH_PORT}" "${DEST_SSH_HOST}" -o StrictHostKeyChecking=no \
        "[ -d '${DEST_PATH}/wp-content/uploads' ] || { \
            echo 'Destination uploads directory does not exist. Creating...'; \
            mkdir -p '${DEST_PATH}/wp-content/uploads' || { \
                echo 'Error: Failed to create destination uploads directory.'; \
                exit 1; \
            }; \
        }" || { \
        echo "Error: Failed to ensure destination uploads directory on ${DEST_ENV}"; \
        exit 1; \
    }

    # Sync uploads from source to local temp directory
    rsync -avz \
        -e "ssh -i ${SOURCE_SSH_KEY} -p ${SOURCE_SSH_PORT} -o StrictHostKeyChecking=no" \
        "${SOURCE_PATH}/wp-content/uploads/" \
        "$TMP_DIR/remote-uploads/"

    # Sync uploads from local temp directory to destination
    rsync -avz \
        -e "ssh -i ${DEST_SSH_KEY} -p ${DEST_SSH_PORT} -o StrictHostKeyChecking=no" \
        "$TMP_DIR/remote-uploads/" \
        "${DEST_SSH_HOST}:${DEST_PATH}/wp-content/uploads/" \
            --delete
fi
