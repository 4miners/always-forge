#!/bin/bash
numprocesses=$(ps ax | grep '[a]lways_forge.php' | wc -l)
if [[ $numprocesses -lt 1 ]]; then
    echo "Starting AlwaysForge..."
    (cd "${0%/*}" && php always_forge.php >> always_forge.log)
else
  echo "AlwaysForge is already running..."
fi
