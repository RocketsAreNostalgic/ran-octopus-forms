#!/usr/bin/env bash

# Install the official WordPress PHPUnit library and a disposable test database.
# Usage: scripts/install-wp-tests.sh [db-name] [db-user] [db-pass] [db-host] [wp-version]
set -euo pipefail

DB_NAME="${1:-wordpress_test}"
DB_USER="${2:-root}"
DB_PASS="${3:-}"
DB_HOST="${4:-localhost}"
WP_VERSION="${5:-latest}"
WP_TESTS_DIR="${WP_TESTS_DIR:-/tmp/wordpress-tests-lib}"
WP_CORE_DIR="${WP_CORE_DIR:-/tmp/wordpress}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

if [[ "${WP_VERSION}" == "latest" ]]; then
	WP_TESTS_REF="trunk"
	WP_ARCHIVE="https://wordpress.org/latest.tar.gz"
else
	WP_TESTS_REF="tags/${WP_VERSION}"
	WP_ARCHIVE="https://wordpress.org/wordpress-${WP_VERSION}.tar.gz"
fi

if [[ ! -d "${WP_CORE_DIR}" ]]; then
	mkdir -p "${WP_CORE_DIR}"
	curl --fail --location --silent --show-error "${WP_ARCHIVE}" | tar xz --strip-components=1 -C "${WP_CORE_DIR}"
fi

if [[ ! -d "${WP_TESTS_DIR}" ]]; then
	if command -v svn >/dev/null 2>&1; then
		svn export --quiet "https://develop.svn.wordpress.org/${WP_TESTS_REF}/tests/phpunit" "${WP_TESTS_DIR}"
	else
		if [[ "${WP_VERSION}" == "latest" ]]; then
			DEVELOP_ARCHIVE="https://github.com/WordPress/wordpress-develop/archive/refs/heads/trunk.zip"
		else
			DEVELOP_TAG="${WP_VERSION}"

			if [[ "${DEVELOP_TAG}" =~ ^[0-9]+\.[0-9]+$ ]]; then
				DEVELOP_TAG="${DEVELOP_TAG}.0"
			fi

			DEVELOP_ARCHIVE="https://github.com/WordPress/wordpress-develop/archive/refs/tags/${DEVELOP_TAG}.zip"
		fi

		TEMP_DIR="$(mktemp -d)"
		trap 'rm -rf "${TEMP_DIR}"' EXIT
		curl --fail --location --silent --show-error "${DEVELOP_ARCHIVE}" --output "${TEMP_DIR}/wordpress-develop.zip"
		unzip -q "${TEMP_DIR}/wordpress-develop.zip" -d "${TEMP_DIR}/extract"
		TEST_SOURCE="$(find "${TEMP_DIR}/extract" -type d -path '*/tests/phpunit' -print -quit)"

		if [[ -z "${TEST_SOURCE}" ]]; then
			echo "Unable to locate the WordPress PHPUnit library in ${DEVELOP_ARCHIVE}." >&2
			exit 1
		fi

		mv "${TEST_SOURCE}" "${WP_TESTS_DIR}"
	fi
fi

CONFIG_FILE="${WP_TESTS_DIR}/wp-tests-config.php"

cp "${SCRIPT_DIR}/../tests/wp-tests-config.php.template" "${CONFIG_FILE}"
sed -i.bak "s|{{DB_NAME}}|${DB_NAME}|g" "${CONFIG_FILE}"
sed -i.bak "s|{{DB_USER}}|${DB_USER}|g" "${CONFIG_FILE}"
sed -i.bak "s|{{DB_PASS}}|${DB_PASS}|g" "${CONFIG_FILE}"
sed -i.bak "s|{{DB_HOST}}|${DB_HOST}|g" "${CONFIG_FILE}"
sed -i.bak "s|{{WP_CORE_DIR}}|${WP_CORE_DIR}|g" "${CONFIG_FILE}"
rm -f "${CONFIG_FILE}.bak"

MYSQL_ARGS=( "--user=${DB_USER}" )

if [[ "${DB_HOST}" == localhost:/*.sock ]]; then
	MYSQL_ARGS+=( "--socket=${DB_HOST#localhost:}" )
else
	MYSQL_ARGS+=( "--host=${DB_HOST}" )
fi

if [[ -n "${DB_PASS}" ]]; then
	MYSQL_ARGS+=( "--password=${DB_PASS}" )
fi

mysql "${MYSQL_ARGS[@]}" --execute="CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\`;"

printf 'Installed WordPress %s test library at %s.\n' "${WP_VERSION}" "${WP_TESTS_DIR}"
