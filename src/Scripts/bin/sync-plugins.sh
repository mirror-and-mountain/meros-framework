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

PLUGINS="$7"

SOURCE_ENV="$8"
SOURCE_URL="$9"
SOURCE_PATH="${10}"
SOURCE_SSH_HOST="${11:-""}"
SOURCE_SSH_PORT="${12:-""}"
SOURCE_SSH_KEY="${13:-""}"

ACTIVATE_PLUGINS="${14:-"true"}"

# ---------------------------------------------------------------------
# Sync Operation - From Local to Remote
# ---------------------------------------------------------------------
if [ $SOURCE_ENV = 'local_dev' ]; then
    # Ensure plugins directory exists on destination
    ssh -i "${DEST_SSH_KEY}" -p "${DEST_SSH_PORT}" "${DEST_SSH_HOST}" -o StrictHostKeyChecking=no \
        "[ -d '${DEST_PATH}/wp-content/plugins' ] || { \
            echo 'Destination plugin directory does not exist. Creating...'; \
            mkdir -p '${DEST_PATH}/wp-content/plugins' || { \
                echo 'Error: Failed to create destination plugin directory.'; \
                exit 1; \
            }; \
        }" || { \
        echo "Error: Failed to ensure destination plugin directory on ${DEST_ENV}"; \
        exit 1; \
    }

    if [ "$PLUGINS" = "all" ]; then
        rsync -avz \
            -e "ssh -i ${DEST_SSH_KEY} -p ${DEST_SSH_PORT} -o StrictHostKeyChecking=no" \
            "${SOURCE_PATH}/wp-content/plugins/" \
            "${DEST_SSH_HOST}:${DEST_PATH}/wp-content/plugins/" \
            --delete
    else
        IFS=',' read -ra PLUGINS_ARRAY <<< "$PLUGINS"
        for plugin in "${PLUGINS_ARRAY[@]}"; do
            plugin=$(echo "$plugin" | xargs)  # trim whitespace
            rsync -avz \
                -e "ssh -i ${DEST_SSH_KEY} -p ${DEST_SSH_PORT} -o StrictHostKeyChecking=no" \
                "${SOURCE_PATH}/wp-content/plugins/${plugin}/" \
                "${DEST_SSH_HOST}:${DEST_PATH}/wp-content/plugins/${plugin}/" \
                --delete
        done
    fi

    echo "Activating plugins on destination..."
    if [ "$ACTIVATE_PLUGINS" = "true" ]; then
        if [ "$PLUGINS" = "all" ]; then
            ssh -i "${DEST_SSH_KEY}" -p "${DEST_SSH_PORT}" "${DEST_SSH_HOST}" -o StrictHostKeyChecking=no \
                "wp plugin activate --all --path='${DEST_PATH}'"
        else
            IFS=',' read -ra PLUGINS <<< "$PLUGINS"
            for plugin in "${PLUGINS[@]}"; do
                ssh -i "${DEST_SSH_KEY}" -p "${DEST_SSH_PORT}" "${DEST_SSH_HOST}" -o StrictHostKeyChecking=no \
                    "wp plugin activate '$plugin' --path='${DEST_PATH}'"
            done
        fi
    fi

# ---------------------------------------------------------------------
# Sync Operation - From Remote to Remote (Staged)
# ---------------------------------------------------------------------
else 
    echo "Performing staged sync of plugins from '${SOURCE_ENV}' to '${DEST_ENV}'..."
    TMP_DIR=$(mktemp -d)
    trap cleanup EXIT
    trap on_error ERR
    mkdir -p "$TMP_DIR/remote-plugins"

    # Ensure destination plugin directory exists
    ssh -i "${DEST_SSH_KEY}" -p "${DEST_SSH_PORT}" "${DEST_SSH_HOST}" -o StrictHostKeyChecking=no \
        "[ -d '${DEST_PATH}/wp-content/plugins' ] || { \
            echo 'Destination plugin directory does not exist. Creating...'; \
            mkdir -p '${DEST_PATH}/wp-content/plugins' || { \
                echo 'Error: Failed to create destination plugin directory.'; \
                exit 1; \
            }; \
        }" || { \
        echo "Error: Failed to ensure destination plugin directory on ${DEST_ENV}"; \
        exit 1; \
    }

    if [ "$PLUGINS" = "all" ]; then
        # Pull source remote to local temp
        rsync -avz \
            -e "ssh -i ${SOURCE_SSH_KEY} -p ${SOURCE_SSH_PORT} -o StrictHostKeyChecking=no" \
            "${SOURCE_SSH_HOST}:${SOURCE_PATH}/wp-content/plugins/" \
            "$TMP_DIR/remote-plugins/"

        # Push local temp to dest remote
        rsync -avz \
            -e "ssh -i ${DEST_SSH_KEY} -p ${DEST_SSH_PORT} -o StrictHostKeyChecking=no" \
            "$TMP_DIR/remote-plugins/" \
            "${DEST_SSH_HOST}:${DEST_PATH}/wp-content/plugins/" \
            --delete
    else
        IFS=',' read -ra PLUGINS_ARRAY <<< "$PLUGINS"
        for plugin in "${PLUGINS_ARRAY[@]}"; do
            plugin=$(echo "$plugin" | xargs)  # trim whitespace
            
            # Pull source remote to local temp
            rsync -avz \
                -e "ssh -i ${SOURCE_SSH_KEY} -p ${SOURCE_SSH_PORT} -o StrictHostKeyChecking=no" \
                "${SOURCE_SSH_HOST}:${SOURCE_PATH}/wp-content/plugins/${plugin}/" \
                "$TMP_DIR/remote-plugins/${plugin}/"

            # Push local temp to dest remote
            rsync -avz \
                -e "ssh -i ${DEST_SSH_KEY} -p ${DEST_SSH_PORT} -o StrictHostKeyChecking=no" \
                "$TMP_DIR/remote-plugins/${plugin}/" \
                "${DEST_SSH_HOST}:${DEST_PATH}/wp-content/plugins/${plugin}/" \
                --delete
        done
    fi

    echo "Activating plugins on destination..."
    if [ "$ACTIVATE_PLUGINS" = "true" ]; then
        if [ "$PLUGINS" = "all" ]; then
            ssh -i "${DEST_SSH_KEY}" -p "${DEST_SSH_PORT}" "${DEST_SSH_HOST}" -o StrictHostKeyChecking=no \
                "wp plugin activate --all --path='${DEST_PATH}'"
        else
            IFS=',' read -ra PLUGINS <<< "$PLUGINS"
            for plugin in "${PLUGINS[@]}"; do
                ssh -i "${DEST_SSH_KEY}" -p "${DEST_SSH_PORT}" "${DEST_SSH_HOST}" -o StrictHostKeyChecking=no \
                    "wp plugin activate '$plugin' --path='${DEST_PATH}'"
            done
        fi
    fi
fi
