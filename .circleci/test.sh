#!/usr/bin/env bash
##
# Run tests.
#
set -e

echo "==> Lint code"
ahoy lint

echo "==> Run BDD tests"
ahoy test-bdd -- "--format=progress_fail" || ahoy test-bdd -- "--rerun --format=progress_fail"
