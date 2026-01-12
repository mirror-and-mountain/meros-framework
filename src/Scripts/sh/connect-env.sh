#!/bin/bash
set +e

# --- Get Parameters from Command Line ---
ENV_PATH="$1"
ENV_SSH_HOST="$2"
ENV_SSH_PORT="$3"
ENV_SSH_KEY="$4"

echo "Attempting connection with: ${ENV_SSH_HOST}:${ENV_SSH_PORT} with path ${ENV_PATH}"

# --- Connect to Environment ---
ssh -o StrictHostKeyChecking=no \
    -i "${ENV_SSH_KEY}" \
    -p "${ENV_SSH_PORT}" \
    "${ENV_SSH_HOST}" \
    -t "cd '${ENV_PATH}' && exec \"\$SHELL\" --login"
set -e