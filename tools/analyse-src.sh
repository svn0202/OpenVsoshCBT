#!/usr/bin/env bash

set -euo pipefail

common_files=()
while IFS= read -r file; do
    common_files+=("$file")
done < <(
    rg --files admin public shared \
        -g '*.php' \
        -g '!shared/tcpdf/**' \
        -g '!shared/phpmailer/**' \
        -g '!shared/cas/**' \
        -g '!shared/radius/**' \
        -g '!shared/jscripts/**' \
        -g '!admin/config/**' \
        -g '!public/config/**' \
        -g '!shared/config/**' \
        -g '!admin/code/tmf_word_import.php' \
        -g '!admin/code/tmf_word_import_db.php' \
        -g '!shared/code/tce_db_dal_mysql.php' \
        -g '!shared/code/tce_db_dal_mysqli.php' \
        -g '!shared/code/tce_db_dal_postgresql.php' \
        -g '!shared/code/tce_db_dal_oracle.php'
)
common_files+=(index.php)

vendor/bin/mago --config mago.src.toml analyze "${common_files[@]}" --baseline mago.analyze.baseline.toml

for driver in mysql mysqli postgresql oracle; do
    driver_file="shared/code/tce_db_dal_${driver}.php"
    sed '/@mago-expect analysis:duplicate-definition/d' "$driver_file" \
        | vendor/bin/mago --config mago.src.toml analyze --stdin-input "$driver_file" --ignore-baseline
done
