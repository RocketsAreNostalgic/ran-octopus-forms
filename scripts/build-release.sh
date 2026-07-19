#!/usr/bin/env sh
# Build a clean, reviewable plugin ZIP from the explicit release allowlist.
set -eu

root=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
output=${1:-"$root/dist"}
slug=ran-emailoctopus-jetpack-forms
archive=$(sh "$root/scripts/release-archive-path.sh" "$output")
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
php -l ran-emailoctopus-jetpack-forms.php

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

# Keep archive bytes stable across machines and repeated builds. ZIP stores
# entry timestamps and filesystem metadata unless they are normalized first.
find "$stage/$slug" -exec touch -t 200001010000 {} +

(
	cd "$stage"
	find "$slug" -print | LC_ALL=C sort | zip -X -q "$archive" -@
)

unzip -t "$archive" >/dev/null
unzip -Z1 "$archive" | grep -qx "$slug/ran-emailoctopus-jetpack-forms.php"
echo "Created and validated $archive"
