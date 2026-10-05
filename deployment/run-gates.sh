#!/bin/bash
# Runs gates.py --stage staging on the server. Prompts for the hPanel directory login (never stored, never on a command line).
set -u
cd ~/domains/techdosedaily.com/public_html/staging || exit 1
PY=/opt/alt/python311/bin/python3
for try in 1 2 3; do
  read -rsp "Staging directory login as username:password > " TDD_BASIC_AUTH; echo; export TDD_BASIC_AUTH
  code=$($PY -c "import os,requests; u,p=os.environ[\"TDD_BASIC_AUTH\"].split(\":\",1); print(requests.get(\"https://staging.techdosedaily.com/\",auth=(u,p),allow_redirects=False,timeout=30).status_code)" 2>/dev/null)
  [ "$code" != "401" ] && [ -n "$code" ] && { echo "Login accepted (HTTP $code)."; break; }
  echo "Login rejected (HTTP ${code:-error}). Username is the one set in hPanel Password Protect Directories; check the password."
  [ $try = 3 ] && { unset TDD_BASIC_AUTH; exit 1; }
done
WP="wp --path=$PWD" BASE=https://staging.techdosedaily.com $PY ~/release-0.9.0/gates.py --stage staging
echo "exit=$?"
unset TDD_BASIC_AUTH
