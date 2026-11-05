#!/usr/bin/env bash
#
# Emits composer.json's GitHub package repositories as a GITHUB_OUTPUT `list` heredoc, for
# scoping the App installation token CI hands to Composer. Deriving the scope here rather than
# in each workflow keeps it from drifting away from the dependency list it exists for. With no
# such repositories the list is empty and the workflow skips the token.
set -euo pipefail

repos=$(jq -r '
  (.repositories // [])
  | (if type == "object" then [.[]] else . end)[]
  | select(type == "object" and .type == "vcs")
  | .url
  | capture("^(https://github\\.com/|git@github\\.com:)(?<owner>[^/]+)/(?<repo>.+?)(\\.git)?$")
  | .repo
' "${1:-composer.json}" | sort -u)

printf 'list<<EOF\n%s\nEOF\n' "$repos"
