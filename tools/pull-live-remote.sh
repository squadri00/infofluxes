#!/bin/bash
# Runs on the LIVE server, called by pull-live.ps1 on your computer. It only reads: it never changes the live data.
#   dump        make a compressed dump of the live database (without sessions and cache) in ~/pull-tmp/live.sql.gz; prints the server time it started
#   files EPOCH make a tar of the uploaded files changed since EPOCH (0 = all) in ~/pull-tmp/live-files.tgz; prints how many files
#   counts      print "table<TAB>rows" for every table of the live database
#   clean       remove ~/pull-tmp
# The database password is read from the live .env here, and is never printed.
set -e
APP="$HOME/infofluxes-app"
OUT="$HOME/pull-tmp"
umask 077
mkdir -p "$OUT"
cd "$APP"

env_get() { grep "^$1=" .env | head -1 | cut -d= -f2- | tr -d '\r' | sed 's/^"//; s/"$//'; }
DB="$(env_get DB_DATABASE)"
DBUSER="$(env_get DB_USERNAME)"
DBHOST="$(env_get DB_HOST)"
DBPORT="$(env_get DB_PORT)"
export MYSQL_PWD="$(env_get DB_PASSWORD)"

case "$1" in
    dump)
        date +%s > "$OUT/started.txt"
        mysqldump -h"${DBHOST:-127.0.0.1}" -P"${DBPORT:-3306}" -u"$DBUSER" --single-transaction --no-tablespaces --skip-lock-tables \
            --default-character-set=utf8mb4 --ignore-table="$DB.sessions" --ignore-table="$DB.cache" --ignore-table="$DB.cache_locks" "$DB" | gzip > "$OUT/live.sql.gz"
        cat "$OUT/started.txt"
        ;;
    files)
        SINCE="${2:-0}"
        cd storage/app/public
        if [ "$SINCE" = "0" ]; then
            find . -type f > "$OUT/files.list"
        else
            find . -type f -newermt "@$SINCE" > "$OUT/files.list"
        fi
        if [ -s "$OUT/files.list" ]; then
            tar czf "$OUT/live-files.tgz" -T "$OUT/files.list"
        else
            tar czf "$OUT/live-files.tgz" --files-from /dev/null
        fi
        wc -l < "$OUT/files.list" | tr -d ' '
        ;;
    counts)
        for t in $(mysql -h"${DBHOST:-127.0.0.1}" -P"${DBPORT:-3306}" -u"$DBUSER" -N -e "show tables" "$DB"); do
            n=$(mysql -h"${DBHOST:-127.0.0.1}" -P"${DBPORT:-3306}" -u"$DBUSER" -N -e "select count(*) from \`$t\`" "$DB")
            printf '%s\t%s\n' "$t" "$n"
        done
        ;;
    clean)
        rm -rf "$OUT"
        ;;
    *)
        echo "usage: pull-live-remote.sh dump | files EPOCH | counts | clean" >&2
        exit 2
        ;;
esac
unset MYSQL_PWD
