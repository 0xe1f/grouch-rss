#!/usr/bin/env bash
set -euo pipefail

COMPOSE="docker compose -f docker/docker-compose.yml"

echo "==> Running golden tests..."
$COMPOSE run --rm test-golden

echo "==> Running live tests..."
$COMPOSE run --rm test-live

echo "==> All tests passed."
