#!/usr/bin/env bash

per_test_setup() {
  set -e -o pipefail
  set +u

  echo "# doing 'ddev add-on get ${PROJECT_SOURCE:-}' PROJNAME=${PROJNAME:-} in TESTDIR=${TESTDIR:-} ($(pwd))" >&3
  run ddev add-on get ${PROJECT_SOURCE:-}
  assert_success

  echo "# ddev start with PROJNAME=${PROJNAME:-} in ${TESTDIR:-} ($(pwd))" >&3
  run ddev start -y
  assert_success

  if [ -f ${PROJECT_SOURCE}/tests/testdata/${testname}/db.sql.gz ]; then
    echo "# Importing database ${PROJECT_SOURCE}/tests/testdata/${testname}/db.sql.gz" >&3
    run ddev import-db --file=${PROJECT_SOURCE}/tests/testdata/${testname}/db.sql.gz
    assert_success
  fi
}

per_test_teardown() {
  ddev delete -Oy ${PROJNAME} >/dev/null 2>&1 || true
}
