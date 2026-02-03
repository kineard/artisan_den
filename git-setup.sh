#!/bin/bash
# Run from project root: bash git-setup.sh
# If you see \r errors first run: sed -i 's/\r$//' git-setup.sh
set -e
cd "$(dirname "$0")"
if [ -d .git ]; then
  echo "Already a git repo."
  exit 0
fi
git init
git remote add origin https://github.com/kineard/artisan_den.git
git add -A
git commit -m "Add full artisan_den project: KPI dashboard, inventory V2, daily on-hand"
git branch -M main
if git ls-remote --exit-code origin main 2>/dev/null; then
  git pull origin main --allow-unrelated-histories --no-edit
fi
git push -u origin main
echo "Done. https://github.com/kineard/artisan_den"
