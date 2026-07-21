#!/bin/bash

# set environment variables EMAIL and PASSWORD to use this script

cleanup() {
    rm -fr "$TMPDIR"
}

set -e
DIR="$(dirname "$(realpath "$0")")"

SKIP_DOWNLOAD=false
for arg in "$@"; do
    if [ "$arg" == "--skip-download" ]; then
        SKIP_DOWNLOAD=true
    fi
done

cd "$DIR"/../var

if [ "$SKIP_DOWNLOAD" = false ]; then
    token=$(curl -f --location \
         --request POST \
         'https://opendata.nationalrail.co.uk/authenticate' \
         --header 'Content-Type: application/x-www-form-urlencoded' \
         --data-urlencode "username=$EMAIL" \
         --data-urlencode "password=$PASSWORD" | jq -r .token)
    curl -f --location -H "X-Auth-Token: $token" https://opendata.nationalrail.co.uk/api/staticfeeds/3.0/timetable -o timetable.zip
fi

TIMETABLE_DIR="$(pwd)"

TMPDIR="$(mktemp -d)"
trap cleanup EXIT
pushd "$TMPDIR"
unzip "$TIMETABLE_DIR/timetable.zip"
mkdir -p "$DIR/var/data"
php "$DIR"/load_data.php .
popd
"$DIR"/cache_boards.bash
