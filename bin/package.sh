#!/bin/sh
# Construit dist/pickupslot-<version>.zip (dossier racine pickupslot/, celui que WordPress remplace)
# et dist/pickupslot.json, le manifeste lu par includes/maj.php sur chaque site.
set -e
cd "$(dirname "$0")/.."
VERSION=$(grep -m1 -E '^\s*\* Version:' pickupslot.php | awk '{print $NF}')
REPO=${GITHUB_REPOSITORY:-maxevstudio/pickupslot}
rm -rf dist/pickupslot "dist/pickupslot-$VERSION.zip" dist/pickupslot.json
mkdir -p dist/pickupslot
cp -R pickupslot.php includes assets readme.txt LICENSE dist/pickupslot/
(cd dist && zip -rqX "pickupslot-$VERSION.zip" pickupslot)
rm -rf dist/pickupslot
VERSION="$VERSION" REPO="$REPO" python3 - <<'PY'
import json, os, re
v, repo = os.environ["VERSION"], os.environ["REPO"]
journal = open("CHANGELOG.md", encoding="utf-8").read()
m = re.search(r"^## \[" + re.escape(v) + r"\][^\n]*\n(.*?)(?=^## \[|\Z)", journal, re.S | re.M)
json.dump({
    "version": v,
    "package": f"https://github.com/{repo}/releases/download/v{v}/pickupslot-{v}.zip",
    "url": f"https://github.com/{repo}/releases/tag/v{v}",
    "changelog": m.group(1).strip() if m else "",
}, open("dist/pickupslot.json", "w", encoding="utf-8"), ensure_ascii=False, indent=2)
PY
echo "dist/pickupslot-$VERSION.zip"
echo "dist/pickupslot.json"
