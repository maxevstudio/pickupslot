#!/bin/sh
# Construit dist/pickupslot-<version>.zip, dossier racine pickupslot/ (celui que WordPress remplace).
set -e
cd "$(dirname "$0")/.."
VERSION=$(grep -m1 -E '^\s*\* Version:' pickupslot.php | awk '{print $NF}')
rm -rf dist/pickupslot "dist/pickupslot-$VERSION.zip"
mkdir -p dist/pickupslot
cp -R pickupslot.php includes assets readme.txt LICENSE dist/pickupslot/
(cd dist && zip -rqX "pickupslot-$VERSION.zip" pickupslot)
rm -rf dist/pickupslot
echo "dist/pickupslot-$VERSION.zip"
