#!/bin/bash
# Post-deploy script to ensure sessions directory exists

SESSION_DIR="/var/www/html/sessions"

if [ ! -d "$SESSION_DIR" ]; then
    mkdir -p "$SESSION_DIR"
    chmod 700 "$SESSION_DIR"
    echo "# Session files directory" > "$SESSION_DIR/.gitkeep"
fi
