#!/bin/bash

# Function to display usage
usage() {
    echo "Usage: $0 [--branch GIT_BRANCH_NAME | --tag GIT_TAG_NAME] [--repository-path LOCAL_REPO_PATH]"
    exit 1
}

# Parse arguments
BRANCH=""
TAG=""
CURRENT_DIR=$(pwd)
REPO_PATH="https://github.com/myflyingbox/woocommerce-plugin.git"
while [[ "$#" -gt 0 ]]; do
    case $1 in
        --branch) BRANCH="$2"; shift ;;
        --tag) TAG="$2"; shift ;;
        --repository-path) REPO_PATH="$2"; shift ;;
        *) usage ;;
    esac
    shift
done

# Check if either branch or tag is specified
if [[ -z "$BRANCH" && -z "$TAG" ]]; then
    usage
fi

# Determine the version
VERSION=""
if [[ -n "$BRANCH" ]]; then
    VERSION="$BRANCH"
    REF="--branch $BRANCH"
elif [[ -n "$TAG" ]]; then
    VERSION="$TAG"
    REF="--branch $TAG"
fi

# Output the BRANCH, TAG and REPO_PATH variables to inform the user of the values being used:
echo "BRANCH: $BRANCH"
echo "TAG: $TAG"
echo "REPO_PATH: $REPO_PATH"

# Wait 2 seconds
sleep 2


# Create a temporary directory
TEMP_DIR=$(mktemp -d)
cd $TEMP_DIR

# Export the repository
if [[ "$REPO_PATH" == http* ]]; then
    git clone --recursive --depth 1 $REF $REPO_PATH my-flying-box
else
    git clone --recursive --depth 1 $REF $REPO_PATH my-flying-box
fi

# Remove .git directories
find my-flying-box -name ".git" -type d -exec rm -rf {} +
# Also remove .git* files (e.g. .gitignore)
find my-flying-box -name ".git*" -exec rm -f {} +

# Remove the package_extension.sh script
find my-flying-box -name "package_extension.sh" -type f -exec rm -f {} +

# Install composer dependencies
cd my-flying-box/includes/lib/php-lce
curl -s http://getcomposer.org/installer | php
php composer.phar install --no-dev

# Compress the directory
cd $TEMP_DIR
zip -r "woocommerce-myflyingbox-$VERSION.zip" my-flying-box

# Check if a file with the same name already exists in the current directory.
# If so, as whether we should overwrite it (y/n, with default as 'n').
# If the user chooses 'n', exit the script.
if [ -f "$CURRENT_DIR/woocommerce-myflyingbox-$VERSION.zip" ]; then
    read -p "A file with the name 'woocommerce-myflyingbox-$VERSION.zip' already exists. Do you want to overwrite it? (y/n) " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        echo "Exiting..."
        exit 1
    fi
fi

# Move the zip file to the current directory
mv "woocommerce-myflyingbox-$VERSION.zip" "$CURRENT_DIR"

# Clean up
rm -rf $TEMP_DIR

echo "Package created: woocommerce-myflyingbox-$VERSION.zip"