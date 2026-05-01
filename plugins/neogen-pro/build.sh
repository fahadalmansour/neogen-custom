#!/bin/bash
# NeoGen Pro Build Script
# Packages the plugin into a zip file for distribution.

PLUGIN_SLUG="neogen-pro"
VERSION=$(grep "Version:" neogen-pro.php | awk '{print $3}')
ZIP_FILE="${PLUGIN_SLUG}-v${VERSION}.zip"

echo "Building ${PLUGIN_SLUG} v${VERSION}..."

# Create a temporary build directory
BUILD_DIR="./build-tmp"
mkdir -p "${BUILD_DIR}/${PLUGIN_SLUG}"

# Build rsync command
RSYNC_OPTS="-rc --exclude=.git* --exclude=tests --exclude=build.sh --exclude=build-tmp"
if [ -f .gitignore ]; then
    RSYNC_OPTS="${RSYNC_OPTS} --exclude-from=.gitignore"
fi

# Copy all files to build directory
rsync ${RSYNC_OPTS} ./ "${BUILD_DIR}/${PLUGIN_SLUG}/"

# Create the zip file
cd "${BUILD_DIR}"
zip -r "../${ZIP_FILE}" "${PLUGIN_SLUG}"
cd ..

# Cleanup
rm -rf "${BUILD_DIR}"

echo "Build complete: ${ZIP_FILE}"
