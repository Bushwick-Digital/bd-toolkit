#!/usr/bin/env bash
# Builds bd-toolkit.zip from committed files and publishes it as a GitHub release.
#
#   bin/release.sh          build, tag v{Version}, push and create the release
#   bin/release.sh --build  only build build/bd-toolkit.zip
#
# Before releasing: bump Version and BDTK_VERSION in bd-toolkit.php, Stable tag in readme.txt,
# add a "= x.y.z =" changelog entry to readme.txt (used as the release notes), commit.
#
# The repo and its releases are public: never add secrets to the zip.

set -euo pipefail

cd "$(dirname "$0")/.."

fail() {
	echo "Error: $1" >&2
	exit 1
}

VERSION=$(sed -n 's/^ \* Version: *//p' bd-toolkit.php)
[ -n "$VERSION" ] || fail "no Version in bd-toolkit.php"
grep -q "define( 'BDTK_VERSION', '$VERSION' );" bd-toolkit.php || fail "BDTK_VERSION doesn't match Version $VERSION"
grep -q "^Stable tag: $VERSION$" readme.txt || fail "readme.txt Stable tag doesn't match Version $VERSION"

NOTES=$(awk -v v="$VERSION" '$0 == "= " v " =" { found = 1; next } found && /^= / { exit } found' readme.txt | sed '/^$/d')
[ -n "$NOTES" ] || fail "no '= $VERSION =' changelog entry in readme.txt"

if [ "${1:-}" != "--build" ]; then
	[ -z "$(git status --porcelain)" ] || fail "uncommitted changes"
	[ "$(git branch --show-current)" = "main" ] || fail "not on main"
	git rev-parse -q --verify "refs/tags/v$VERSION" > /dev/null && fail "tag v$VERSION already exists"
	git ls-remote --exit-code --tags origin "v$VERSION" > /dev/null && fail "tag v$VERSION already exists on GitHub"
fi

# Committed files only (.gitattributes leaves out bin/ and dev files), in a bd-toolkit/ folder so
# WordPress replaces the installed plugin instead of adding a second one.
rm -rf build
mkdir build
git archive --format=tar --prefix=bd-toolkit/ HEAD | tar -x -C build

(cd build && zip -rqX bd-toolkit.zip bd-toolkit)
echo "Built build/bd-toolkit.zip ($VERSION)"

[ "${1:-}" = "--build" ] && exit 0

git tag -a "v$VERSION" -m "BD Toolkit $VERSION"
git push origin main "v$VERSION"
gh release create "v$VERSION" build/bd-toolkit.zip --verify-tag --title "$VERSION" --notes "$NOTES"
