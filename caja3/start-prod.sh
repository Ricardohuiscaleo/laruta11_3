#!/bin/bash

# Start WebSocket server in background
cd /app/caja3
node ws-server.js &

# Start Astro server
npm run preview
