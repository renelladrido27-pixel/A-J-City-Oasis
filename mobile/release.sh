#!/usr/bin/env bash
# Builds the Android app against the live site and publishes it, so installed
# apps show "Update available" and https://ajcityoasis.online/download-app
# serves the new build.
#
#   1. Bump `version:` in mobile/pubspec.yaml — e.g. 1.0.1+2 -> 1.0.2+3.
#      The number after "+" MUST go up, or phones won't see an update.
#   2. From Git Bash, in the repo root:   bash mobile/release.sh "What changed"
#
# Always build on the same computer: Android only installs an update signed
# with the same key as the app already on the phone (this project signs with
# this machine's debug keystore, ~/.android/debug.keystore).
set -euo pipefail

SITE="https://ajcityoasis.online"
SSH_KEY="$HOME/.ssh/ajoasis_hostinger"
SSH_PORT=65002
SSH_HOST="u844547191@195.35.62.90"
REMOTE_DIR="domains/ajcityoasis.online/app/storage/app/public/app"

NOTES="${1:-Improvements and fixes.}"
REPO="$(cd "$(dirname "$0")/.." && pwd)"

VERSION_LINE="$(grep -E '^version:' "$REPO/mobile/pubspec.yaml" | awk '{print $2}')"
VERSION="${VERSION_LINE%%+*}"
BUILD="${VERSION_LINE##*+}"
APK="AJ-City-Oasis-$VERSION.apk"
echo "Releasing version $VERSION (build $BUILD)"

LIVE_BUILD="$(curl -fsS "$SITE/api/app/version" | sed -n 's/.*"build":\([0-9]*\).*/\1/p')"
if [ -n "$LIVE_BUILD" ] && [ "$BUILD" -le "$LIVE_BUILD" ]; then
    echo "Build $BUILD is not newer than the published build $LIVE_BUILD."
    echo "Bump the number after '+' in mobile/pubspec.yaml and run this again."
    exit 1
fi

# Gradle's Windows launcher breaks on "&" in a path (this repo lives under
# "a&j water oasis"), so build through a drive letter mapped to the repo.
BUILD_ROOT="$REPO"
if [[ "$REPO" == *"&"* ]]; then
    DRIVE="$(cmd //c subst | tr -d '\r' | grep -F "$(cygpath -w "$REPO")" | cut -c1 | head -1 || true)"
    if [ -z "$DRIVE" ]; then
        for D in Q R S T U V W; do
            [ -e "/${D,,}/" ] || { cmd //c "subst $D: \"$(cygpath -w "$REPO")\"" && DRIVE="$D" && break; }
        done
    fi
    [ -n "$DRIVE" ] || { echo "Could not map a drive letter for the build."; exit 1; }
    BUILD_ROOT="/${DRIVE,,}"
fi

( cd "$BUILD_ROOT/mobile" && flutter build apk --release --dart-define=API_BASE_URL="$SITE/api" )

mkdir -p "$REPO/release"
cp "$REPO/mobile/build/app/outputs/flutter-apk/app-release.apk" "$REPO/release/$APK"
# Minimal JSON escaping for the notes (backslashes and double quotes).
ESCAPED_NOTES="$(printf '%s' "$NOTES" | sed 's/\\/\\\\/g; s/"/\\"/g')"
printf '{"build": %s, "version": "%s", "file": "%s", "notes": "%s"}\n' "$BUILD" "$VERSION" "$APK" "$ESCAPED_NOTES" > "$REPO/release/version.json"

SSH="ssh -i $SSH_KEY -p $SSH_PORT -o BatchMode=yes"
$SSH "$SSH_HOST" "mkdir -p ~/$REMOTE_DIR"
# The APK first, version.json last: phones are only told about the update
# once the file they'd download is fully there.
scp -q -i "$SSH_KEY" -P "$SSH_PORT" -o BatchMode=yes "$REPO/release/$APK" "$SSH_HOST:$REMOTE_DIR/$APK"
scp -q -i "$SSH_KEY" -P "$SSH_PORT" -o BatchMode=yes "$REPO/release/version.json" "$SSH_HOST:$REMOTE_DIR/version.json"
# Keep only the build that version.json points at.
$SSH "$SSH_HOST" "cd ~/$REMOTE_DIR && ls *.apk | grep -vxF '$APK' | xargs -r rm -f"

echo
echo "Published. The server now reports:"
curl -fsS "$SITE/api/app/version"
echo
echo "Local copy: release/$APK"
