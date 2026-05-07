#!/bin/sh
set -eu

TZINFO_BIN="$(command -v mariadb-tzinfo-to-sql || command -v mysql_tzinfo_to_sql)"
MYSQL_BIN="$(command -v mariadb || command -v mysql)"

"$TZINFO_BIN" /usr/share/zoneinfo | "$MYSQL_BIN" -uroot -p"${MYSQL_ROOT_PASSWORD}" mysql