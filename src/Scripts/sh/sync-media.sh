#!/bin/bash
set -euo pipefail

cleanup() {
    [ -n "${TMP_DIR:-}" ] && rm -rf "$TMP_DIR"
}

on_error() {
    echo "Error on line $LINENO on host: $(hostname)"
}

trap cleanup EXIT
trap on_error ERR

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
# Constants
# ---------------------------------------------------------------------
UPLOADS_DIR="wp-content/uploads"
IMPORT_DIR="wp-content/.media-import"
TMP_DIR="$(mktemp -d)"

# ---------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------

# Only ORIGINAL uploads (exclude thumbnails)
is_original_media_file() {
    case "$1" in
        *-[0-9]*x[0-9]*.*) return 1 ;;
        *)                 return 0 ;;
    esac
}

# Export attached relative paths from WP
export_attachment_index() {
    wp post list \
        --post_type=attachment \
        --fields=ID \
        --format=ids \
    | tr ' ' '\n' \
    | while read -r id; do
        wp post meta get "$id" _wp_attached_file
      done \
    | sed '/^$/d' \
    | LC_ALL=C sort
}

# Import media on destination. Skip existing files.
register_media() {
    local site_path="$1"
    local import_dir="$2"
    local uploads_dir="$3"

    cd "$site_path"

    [ -d "$import_dir" ] || {
        echo "No media to import."
        return
    }

    echo "Importing media into WordPress..."

    find "$import_dir" -type f | while read -r file; do
        rel_path="${file#$import_dir/}"
        filename="$(basename "$file")"

        # HARDENER: skip if file exists anywhere in uploads
        if find "$uploads_dir" -type f -name "$filename" -print -quit | grep -q .; then
            echo "Skipping existing file (found elsewhere): $filename"
            rm -f "$file"
            continue
        fi

        wp media import "$file" \
            --preserve-filetime \
            --quiet

        rm -f "$file"
    done

    # Clean up empty directories
    find "$import_dir" -type d -empty -delete
}

# ---------------------------------------------------------------------
# 1: Create destination attachment index
# ---------------------------------------------------------------------
echo "Fetching destination media index..."

if [ "$DEST_ENV" = "local_dev" ]; then
    cd "$DEST_PATH"
    export_attachment_index > "$TMP_DIR/dest-media.txt"
else
    ssh -i "$DEST_SSH_KEY" -p "$DEST_SSH_PORT" "$DEST_SSH_HOST" \
        "cd '$DEST_PATH' && $(declare -f export_attachment_index); export_attachment_index" \
        > "$TMP_DIR/dest-media.txt"
fi

# ---------------------------------------------------------------------
# 2: Create source filesystem index (originals only)
# ---------------------------------------------------------------------
echo "Scanning source uploads..."

if [ "$SOURCE_ENV" = "local_dev" ]; then
    cd "$SOURCE_PATH/$UPLOADS_DIR"
    find . -type f | sed 's|^\./||' \
        | while read -r file; do
            is_original_media_file "$file" && echo "$file"
          done \
        | LC_ALL=C sort \
        > "$TMP_DIR/source-media.txt"
else
    ssh -i "$SOURCE_SSH_KEY" -p "$SOURCE_SSH_PORT" "$SOURCE_SSH_HOST" \
        "cd '$SOURCE_PATH/$UPLOADS_DIR' && \
         $(declare -f is_original_media_file); \
         find . -type f | sed 's|^\./||' | \
         while read -r file; do
           is_original_media_file \"\$file\" && echo \"\$file\"
         done | LC_ALL=C sort" \
        > "$TMP_DIR/source-media.txt"
fi

# ---------------------------------------------------------------------
# 3: Diff - find missing media on destination
# ---------------------------------------------------------------------
comm -23 "$TMP_DIR/source-media.txt" "$TMP_DIR/dest-media.txt" \
    > "$TMP_DIR/missing-media.txt"

if [ ! -s "$TMP_DIR/missing-media.txt" ]; then
    echo "No media missing on destination."
    exit 0
fi

echo "Media files to sync:"
wc -l "$TMP_DIR/missing-media.txt"

# ---------------------------------------------------------------------
# 4: Sync to tmp import dir on destination
# ---------------------------------------------------------------------
echo "Syncing media to destination import directory..."

RSYNC_BASE="$SOURCE_PATH/$UPLOADS_DIR/"

# ---------------------------------------------------------------------
# Sync Operation - From Local to Remote
# ---------------------------------------------------------------------
if [ "$SOURCE_ENV" = "local_dev" ] && [ "$DEST_ENV" != "local_dev" ]; then
    ssh -i "$DEST_SSH_KEY" -p "$DEST_SSH_PORT" "$DEST_SSH_HOST" \
        "mkdir -p '$DEST_PATH/$IMPORT_DIR'"

    rsync -avz \
        --files-from="$TMP_DIR/missing-media.txt" \
        --relative \
        -e "ssh -i $DEST_SSH_KEY -p $DEST_SSH_PORT" \
        "$RSYNC_BASE" \
        "$DEST_SSH_HOST:$DEST_PATH/$IMPORT_DIR/"

# ---------------------------------------------------------------------
# Sync Operation - From Remote to Local
# ---------------------------------------------------------------------
elif [ "$SOURCE_ENV" != "local_dev" ] && [ "$DEST_ENV" = "local_dev" ]; then
    mkdir -p "$DEST_PATH/$IMPORT_DIR"

    rsync -avz \
        --files-from="$TMP_DIR/missing-media.txt" \
        --relative \
        -e "ssh -i $SOURCE_SSH_KEY -p $SOURCE_SSH_PORT" \
        "$SOURCE_SSH_HOST:$RSYNC_BASE" \
        "$DEST_PATH/$IMPORT_DIR/"

# ---------------------------------------------------------------------
# Sync Operation - Remote to Remote (Staged)
# ---------------------------------------------------------------------
else
    mkdir -p "$TMP_DIR/staging"

    rsync -avz \
        --files-from="$TMP_DIR/missing-media.txt" \
        --relative \
        -e "ssh -i $SOURCE_SSH_KEY -p $SOURCE_SSH_PORT" \
        "$SOURCE_SSH_HOST:$RSYNC_BASE" \
        "$TMP_DIR/staging/"

    ssh -i "$DEST_SSH_KEY" -p "$DEST_SSH_PORT" "$DEST_SSH_HOST" \
        "mkdir -p '$DEST_PATH/$IMPORT_DIR'"

    rsync -avz \
        -e "ssh -i $DEST_SSH_KEY -p $DEST_SSH_PORT" \
        "$TMP_DIR/staging/" \
        "$DEST_SSH_HOST:$DEST_PATH/$IMPORT_DIR/"
fi

# ---------------------------------------------------------------------
# Step 5: import on destination
# ---------------------------------------------------------------------
echo "Registering media on destination..."

if [ "$DEST_ENV" = "local_dev" ]; then
    register_media "$DEST_PATH" "$IMPORT_DIR" "$UPLOADS_DIR"
else
    ssh -i "$DEST_SSH_KEY" -p "$DEST_SSH_PORT" "$DEST_SSH_HOST" \
        "$(declare -f register_media); register_media '$DEST_PATH' '$IMPORT_DIR' '$UPLOADS_DIR'"
fi

echo "Media sync complete ✔"
