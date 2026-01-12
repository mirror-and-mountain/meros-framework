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
DELETE="${15:-"false"}"
ACTIVATE="${16:-"false"}"

# ---------------------------------------------------------------------
# Sync Operation - From Local to Remote
# ---------------------------------------------------------------------
if [ $SOURCE_ENV = 'local_dev' ]; then
    # Ensure plugins directory exists on destination
    ssh -i "${DEST_SSH_KEY}" -p "${DEST_SSH_PORT}" "${DEST_SSH_HOST}" \
        "[ -d '${DEST_PATH}/wp-content/plugins' ] || { \
            if [ '${MAKE_DIR}' = 'true' ]; then \
                echo 'Destination plugins directory does not exist. Creating...'; \
                mkdir -p '${DEST_PATH}/wp-content/plugins' || {
                    echo 'Error: Failed to create destination plugins directory.'; \
                    exit 1; \
                }; \
            else \
                echo 'Error: Destination plugins directory does not exist.'; \
                exit 1; \
            fi \
        }"

    rsync -avz \
        -e "ssh -i ${DEST_SSH_KEY} -p ${DEST_SSH_PORT}" \
        "${SOURCE_PATH}/wp-content/plugins/" \
        "${DEST_SSH_HOST}:${DEST_PATH}/wp-content/plugins/" \
            $( [ "$DELETE" = "true" ] && echo "--delete" )

    if [ $ACTIVATE = 'true' ]; then
        echo "Activating plugins on destination..."
        ssh -i "${DEST_SSH_KEY}" -p "${DEST_SSH_PORT}" "${DEST_SSH_HOST}" \
            "wp plugin activate --all --path='${DEST_PATH}' --url='${DEST_URL}'"
    fi

# ---------------------------------------------------------------------
# Sync Operation - From Remote to Local
# ---------------------------------------------------------------------
elif [ $DEST_ENV = 'local_dev' ]; then
    # Ensure plugins directory exists on destination
    [ -d "${DEST_PATH}/wp-content/plugins" ] || {
        if [ '${MAKE_DIR}' = 'true' ]; then
            echo "Destination plugins directory does not exist. Creating..."
            mkdir -p "${DEST_PATH}/wp-content/plugins" || {
                echo "Error: Failed to create destination plugins directory."
                exit 1
            }
        else
            echo "Error: Destination plugins directory does not exist."
            exit 1
        fi
    }

    rsync -avz \
        -e "ssh -i ${SOURCE_SSH_KEY} -p ${SOURCE_SSH_PORT}" \
        "${SOURCE_SSH_HOST}:${SOURCE_PATH}/wp-content/plugins/" \
        "${DEST_PATH}/wp-content/plugins/" \
            $( [ "$DELETE" = "true" ] && echo "--delete" )

    if [ $ACTIVATE = 'true' ]; then
        echo "Activating plugins on destination..."
        wp plugin activate --all --path="${DEST_PATH}" --url="${DEST_URL}"
    fi

# ---------------------------------------------------------------------
# Sync Operation - Remote to Remote (Staged)
# ---------------------------------------------------------------------
else 
    TMP_DIR=$(mktemp -d)
    trap cleanup EXIT
    trap on_error ERR
    mkdir -p "$TMP_DIR/remote-plugins"

    # Ensure source plugins directory exists
    ssh -i "${SOURCE_SSH_KEY}" -p "${SOURCE_SSH_PORT}" "${SOURCE_SSH_HOST}" \
        "[ -d '${SOURCE_PATH}/wp-content/plugins' ]" || {
            echo "Error: Source plugins directory does not exist."
            exit 1
        }

    # Pull source remote to local temp
    rsync -avz \
        -e "ssh -i ${SOURCE_SSH_KEY} -p ${SOURCE_SSH_PORT}" \
        "${SOURCE_SSH_HOST}:${SOURCE_PATH}/wp-content/plugins/" \
        "$TMP_DIR/remote-plugins/"

    # Push local temp to dest remote
    rsync -avz \
        -e "ssh -i ${DEST_SSH_KEY} -p ${DEST_SSH_PORT}" \
        "$TMP_DIR/remote-plugins/" \
        "${DEST_SSH_HOST}:${DEST_PATH}/wp-content/plugins/" \
            $( [ "$DELETE" = "true" ] && echo "--delete" )

    if [ $ACTIVATE = 'true' ]; then
        echo "Activating plugins on destination..."
        ssh -i "${DEST_SSH_KEY}" -p "${DEST_SSH_PORT}" "${DEST_SSH_HOST}" \
            "wp plugin activate --all --path='${DEST_PATH}' --url='${DEST_URL}'"
    fi
fi
