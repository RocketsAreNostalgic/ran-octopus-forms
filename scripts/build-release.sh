#!/usr/bin/env sh
# Build a clean, reviewable plugin ZIP from the explicit release allowlist.
set -eu

root=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
slug=ran-octopus-forms
version=1.0.0
output=${1:-"$root/dist"}
archive="$output/$slug-$version.zip"
stage=$(mktemp -d)

cleanup() {
	rm -rf "$stage"
}

trap cleanup EXIT HUP INT TERM

if [ -e "$archive" ]; then
	echo "Refusing to overwrite existing archive: $archive" >&2
	exit 1
fi

mkdir -p "$output" "$stage/$slug"
cd "$root"

pnpm check
find includes -name '*.php' -print0 | xargs -0 -n 1 php -l
php -l ran-octopus-forms.php

while IFS= read -r release_path; do
	[ -n "$release_path" ] || continue
	case "$release_path" in
		*/)
			mkdir -p "$stage/$slug/$release_path"
			cp -R "$release_path". "$stage/$slug/$release_path"
			;;
		*)
			cp "$release_path" "$stage/$slug/$release_path"
			;;
	esac
done < release-contents.txt

(
	cd "$stage"
	zip -qr "$archive" "$slug"
)

unzip -t "$archive" >/dev/null
unzip -Z1 "$archive" | grep -qx "$slug/ran-octopus-forms.php"
echo "Created and validated $archive"
