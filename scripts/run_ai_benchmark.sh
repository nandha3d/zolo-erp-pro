#!/usr/bin/env bash
# Runner for live AI benchmarks and evaluations in CI/local environments

set -e

echo "=== Starting AI Live Omni Benchmark ==="
php scripts/run_live_omni_benchmark.php

echo "=== Starting Full 250-Scenario AI Evaluation Suite ==="
php vendor/bin/phpunit -c phpunit.ai.xml

echo "=== Exporting Machine-Readable Evaluation Artifacts ==="
php scripts/export_ai_evaluation_report.php

echo "=== All AI Benchmarks and Evaluations Completed Successfully ==="
