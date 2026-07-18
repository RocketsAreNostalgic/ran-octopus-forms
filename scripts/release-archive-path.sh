#!/usr/bin/env sh
# Print the archive path from the canonical WordPress plugin header.
set -eu

root=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
slug=ran-emailoctopus-jetpack-forms
output=${1:-"$root/dist"}
version=$(sed -n 's/^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*\([^[:space:]]*\).*$/\1/p' "$root/ran-emailoctopus-jetpack-forms.php")

if [ -z "$version" ] || [ "$(printf '%s\n' "$version" | wc -l | tr -d ' ')" -ne 1 ]; then
	echo 'Unable to read exactly one plugin version from ran-emailoctopus-jetpack-forms.php.' >&2
	exit 1
fi

printf '%s/%s-%s.zip\n' "$output" "$slug" "$version"
