#!/bin/bash
set -e

cleanup() {
    [ -n "$TMP_DIR" ] && rm -rf "$TMP_DIR"
}

on_error() {
    echo "Error on line $LINENO on host: $(hostname)"
}