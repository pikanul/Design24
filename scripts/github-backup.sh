#!/bin/zsh
# Automatically create a GitHub backup of this project when there are changes.

set -euo pipefail

REPOSITORY="/Users/zaira/Desktop/my-php-project/Design24"
LOG_FILE="$HOME/Library/Logs/design24-github-backup.log"

mkdir -p "${LOG_FILE:h}"

log() {
    print -- "$(date '+%Y-%m-%d %H:%M:%S')  $*" >> "$LOG_FILE"
}

cd "$REPOSITORY"

if [[ "$(git rev-parse --abbrev-ref HEAD)" != "main" ]]; then
    log "Skipped: current branch is not main."
    exit 0
fi

# A repository cloned without a configured author cannot make scheduled commits.
# Reuse the author of the latest project commit in that case.
if [[ -z "$(git config --get user.name || true)" ]]; then
    git config user.name "$(git log -1 --format='%an')"
fi

if [[ -z "$(git config --get user.email || true)" ]]; then
    git config user.email "$(git log -1 --format='%ae')"
fi

git fetch --quiet origin main

# Do not create a backup commit based on an out-of-date main branch. This avoids
# automatic merges and leaves the repository ready for a normal manual pull.
if ! git merge-base --is-ancestor origin/main HEAD; then
    log "Skipped: origin/main is ahead of local main; pull/rebase manually first."
    exit 0
fi

if [[ -z "$(git status --porcelain)" ]]; then
    log "No changes to back up."
    exit 0
fi

git add --all

if git diff --cached --quiet; then
    log "Only ignored changes found; nothing committed."
    exit 0
fi

git commit -m "chore: automated backup $(date '+%Y-%m-%d %H:%M')"
git push origin HEAD:main
log "Backup pushed successfully."
