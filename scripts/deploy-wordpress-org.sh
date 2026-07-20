#!/usr/bin/env bash

set -euo pipefail

export LC_ALL=C
export TZ=UTC

PLUGIN_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
CONFIG_PATH="${PLUGIN_ROOT}/wordpress-org/deployment.json"
ARCHIVE_PATH="${1:?Usage: deploy-wordpress-org.sh <archive> <checksum> <manifest> [--allow-disabled] [--sync-assets]}"
CHECKSUM_PATH="${2:?A SHA-256 file is required.}"
MANIFEST_PATH="${3:?A release manifest is required.}"
shift 3

ALLOW_DISABLED=false
SYNC_ASSETS=false
for argument in "$@"; do
	case "${argument}" in
		--allow-disabled) ALLOW_DISABLED=true ;;
		--sync-assets) SYNC_ASSETS=true ;;
		*) echo "Unknown deployment option: ${argument}" >&2; exit 1 ;;
	esac
done

ENABLED="$(jq -r '.enabled' "${CONFIG_PATH}")"
if [[ "${ENABLED}" != true && "${ALLOW_DISABLED}" != true ]]; then
	echo "Routine WordPress.org deployment is disabled in ${CONFIG_PATH}." >&2
	exit 1
fi

WORDPRESS_ORG_SLUG="$(jq -er '.wordpressOrgSlug | select(length > 0)' "${CONFIG_PATH}")"
PACKAGE_SLUG="$(jq -er '.packageSlug' "${CONFIG_PATH}")"
MAIN_PLUGIN_FILE="$(jq -er '.mainPluginFile' "${CONFIG_PATH}")"
ASSETS_DIRECTORY="$(jq -er '.listingAssetsDirectory' "${CONFIG_PATH}")"
VERSION="$(jq -er '.version' "${MANIFEST_PATH}")"
TAG_NAME="$(jq -er '.tag' "${MANIFEST_PATH}")"
ARCHIVE_SHA256="$(sha256sum "${ARCHIVE_PATH}" | cut -d ' ' -f 1)"
ARCHIVE_FILES="$(unzip -Z1 "${ARCHIVE_PATH}" | LC_ALL=C sort | jq -Rsc 'split("\n") | map(select(length > 0))')"

if [[ "${TAG_NAME}" != "v${VERSION}" ]]; then
	echo "Release manifest tag and version do not agree." >&2
	exit 1
fi

if [[ "$(jq -er '.archive' "${MANIFEST_PATH}")" != "$(basename "${ARCHIVE_PATH}")" || \
	"$(jq -er '.sha256' "${MANIFEST_PATH}")" != "${ARCHIVE_SHA256}" || \
	"$(jq -cer '.files' "${MANIFEST_PATH}")" != "$(jq -c <<< "${ARCHIVE_FILES}")" || \
	"$(jq -er '.commit' "${MANIFEST_PATH}")" != "$(git -C "${PLUGIN_ROOT}" rev-parse HEAD)" || \
	"$(jq -er '.packageSlug' "${MANIFEST_PATH}")" != "${PACKAGE_SLUG}" || \
	"$(jq -er '.mainPluginFile' "${MANIFEST_PATH}")" != "${MAIN_PLUGIN_FILE}" || \
	"$(jq -r '.wordpressOrgSlug' "${MANIFEST_PATH}")" != "${WORDPRESS_ORG_SLUG}" ]]; then
	echo "Release manifest does not match the deployment contract." >&2
	exit 1
fi

(
	cd "$(dirname "${ARCHIVE_PATH}")"
	sha256sum --check "$(basename "${CHECKSUM_PATH}")"
)

WORK_DIRECTORY="$(mktemp -d)"
cleanup() {
	rm -rf "${WORK_DIRECTORY}"
}
trap cleanup EXIT HUP INT TERM

unzip -q "${ARCHIVE_PATH}" -d "${WORK_DIRECTORY}/release"
if [[ ! -f "${WORK_DIRECTORY}/release/${PACKAGE_SLUG}/${MAIN_PLUGIN_FILE}" ]]; then
	echo "The verified archive does not contain the configured main plugin file." >&2
	exit 1
fi

: "${WORDPRESS_ORG_USERNAME:?WORDPRESS_ORG_USERNAME is required.}"
: "${WORDPRESS_ORG_PASSWORD:?WORDPRESS_ORG_PASSWORD is required.}"

SVN_URL="https://plugins.svn.wordpress.org/${WORDPRESS_ORG_SLUG}"
SVN_AUTH=(--non-interactive --no-auth-cache --username "${WORDPRESS_ORG_USERNAME}" --password "${WORDPRESS_ORG_PASSWORD}")
svn checkout "${SVN_URL}" "${WORK_DIRECTORY}/svn" "${SVN_AUTH[@]}"

rsync -a --delete --exclude='.svn' "${WORK_DIRECTORY}/release/${PACKAGE_SLUG}/" "${WORK_DIRECTORY}/svn/trunk/"
while IFS= read -r missing_path; do
	[[ -n "${missing_path}" ]] || continue
	svn rm --force "${missing_path}"
done < <(svn status "${WORK_DIRECTORY}/svn/trunk" | sed -n 's/^!.......//p')
svn add --force "${WORK_DIRECTORY}/svn/trunk" --parents

if [[ "${SYNC_ASSETS}" == true ]]; then
	rsync -a --delete --exclude='README.md' --exclude='drafts/' --exclude='.svn' \
		"${PLUGIN_ROOT}/${ASSETS_DIRECTORY}/" "${WORK_DIRECTORY}/svn/assets/"
	svn add --force "${WORK_DIRECTORY}/svn/assets" --parents
fi

if svn ls "${SVN_URL}/tags/${VERSION}" "${SVN_AUTH[@]}" >/dev/null 2>&1; then
	echo "WordPress.org tag ${VERSION} already exists; refusing to replace it." >&2
	exit 1
fi

svn status "${WORK_DIRECTORY}/svn"
svn commit "${WORK_DIRECTORY}/svn" -m "Release ${VERSION}" "${SVN_AUTH[@]}"
svn copy "${SVN_URL}/trunk" "${SVN_URL}/tags/${VERSION}" -m "Tag ${VERSION}" "${SVN_AUTH[@]}"
