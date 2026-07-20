#!/bin/bash

# set environment variables EMAIL and PASSWORD to use this script
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


timetable_filename=$(basename "$(unzip -Z -1 timetable.zip "*.DAT" | head -n 1)")
timetable_date=$(unzip -p timetable.zip "*.DAT" | tr -d '\r' | grep "Generated:" | head -n 1 | awk '{split($NF, a, "/"); print a[3]"-"a[2]"-"a[1]}')
eval "$("$DIR/get_update_database.php" | jq -r 'to_entries | .[] | if .value == null then "unset \(.key)" else "export \(.key)=\(.value|@sh)" end')"

echo "Updating database $DATABASE_NAME"
echo "Current database date: $generated"
echo "File date: $timetable_date"

if 
    [ "$generated" != "$timetable_date" ]
then
    yarn run dtd2mysql --timetable timetable.zip
    mysql -h "$DATABASE_HOST" ${DATABASE_PORT:+ -P "$DATABASE_PORT"} -u "$DATABASE_USERNAME" ${DATABASE_PASSWORD:+ -p"$DATABASE_PASSWORD"} "$DATABASE_NAME" < "$DIR/../resource/create_additional_tables.sql"
    mysql -h "$DATABASE_HOST" ${DATABASE_PORT:+ -P "$DATABASE_PORT"} -u "$DATABASE_USERNAME" ${DATABASE_PASSWORD:+ -p"$DATABASE_PASSWORD"} "$DATABASE_NAME" -e "INSERT INTO import (file_name, generated_date, imported_date) VALUES ('$timetable_filename', '$timetable_date', NOW())"
    echo "$timetable_filename has been loaded into $DATABASE_NAME."
else
    echo "Database is up to date. Nothing to do."
fi
     