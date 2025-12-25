#!/bin/bash

# Spjall Chat - Server Launcher
# Usage: ./start.sh [--local | --ngrok]

# --- Configuration ---
MODE="local"
NGROK_DOMAIN="chat.spjall.chat"  # Set your static domain here if you have one, e.g. "spjall.ngrok.dev"
HTTP_PORT=8080
WS_PORT=8081
LOG_DIR="logs"

# --- Colors ---
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

# --- Helpers ---
log() { echo -e "${BLUE}[INFO]${NC} $1"; }
warn() { echo -e "${YELLOW}[WARN]${NC} $1"; }
error() { echo -e "${RED}[ERR]${NC} $1"; }
success() { echo -e "${GREEN}[OK]${NC} $1"; }

cleanup() {
    echo ""
    log "Stopping servers..."
    pkill -P $$ 2>/dev/null
    [ ! -z "$NGROK_PID" ] && kill $NGROK_PID 2>/dev/null
    [ ! -z "$HTTP_PID" ] && kill $HTTP_PID 2>/dev/null
    [ ! -z "$WS_PID" ] && kill $WS_PID 2>/dev/null
    rm -f ngrok_spjall.yml
    success "Cleanup complete."
    exit
}

trap cleanup SIGINT SIGTERM EXIT

# --- Parse Args ---
case "$1" in
    --ngrok|-n) MODE="ngrok" ;;
    --local|-l) MODE="local" ;;
    *) 
        if [ ! -z "$1" ]; then
            echo "Usage: $0 [--local | --ngrok]"
            exit 1
        fi
        ;;
esac

# --- Pre-flight ---
mkdir -p "$LOG_DIR"

if ! command -v php &> /dev/null; then
    error "PHP not found! Install with: brew install php"
    exit 1
fi

# Check database exists
if [ ! -f "data/spjallchat.db" ]; then
    warn "Database not found. Running setup..."
    ./setup.sh
fi

# --- Kill existing processes on our ports ---
lsof -ti:$HTTP_PORT | xargs kill -9 2>/dev/null
lsof -ti:$WS_PORT | xargs kill -9 2>/dev/null

# --- Set URLs based on mode ---
if [ "$MODE" == "ngrok" ]; then
    log "Mode: Public (ngrok)"
    
    if ! command -v ngrok &> /dev/null; then
        error "ngrok not found! Install from https://ngrok.com or run with --local"
        exit 1
    fi

    # Generate ngrok config for both tunnels
    log "Configuring ngrok tunnels..."
    
    if [ ! -z "$NGROK_DOMAIN" ]; then
        log "Using static domain: $NGROK_DOMAIN"
        cat > ngrok_spjall.yml << EOF
version: "2"
tunnels:
  http:
    proto: http
    addr: $HTTP_PORT
    domain: $NGROK_DOMAIN
  websocket:
    proto: http
    addr: $WS_PORT
EOF
    else
        cat > ngrok_spjall.yml << EOF
version: "2"
tunnels:
  http:
    proto: http
    addr: $HTTP_PORT
  websocket:
    proto: http
    addr: $WS_PORT
EOF
    fi

    # Start ngrok
    pkill -9 ngrok 2>/dev/null
    sleep 1
    
    # Find ngrok config with auth token
    DEFAULT_CONFIG="$HOME/Library/Application Support/ngrok/ngrok.yml"
    [ ! -f "$DEFAULT_CONFIG" ] && DEFAULT_CONFIG="$HOME/.ngrok2/ngrok.yml"
    [ ! -f "$DEFAULT_CONFIG" ] && DEFAULT_CONFIG="$HOME/.config/ngrok/ngrok.yml"
    
    if [ -f "$DEFAULT_CONFIG" ]; then
        log "Using ngrok config: $DEFAULT_CONFIG"
        ngrok start --config="$DEFAULT_CONFIG" --config=ngrok_spjall.yml --log=stdout http websocket > "$LOG_DIR/ngrok.log" 2>&1 &
    else
        warn "No ngrok config found. Make sure you've run: ngrok config add-authtoken YOUR_TOKEN"
        ngrok start --config=ngrok_spjall.yml --log=stdout http websocket > "$LOG_DIR/ngrok.log" 2>&1 &
    fi
    NGROK_PID=$!
    
    log "Waiting for ngrok tunnels..."
    sleep 5
    
    # Check ngrok is running
    if ! ps -p $NGROK_PID > /dev/null 2>&1; then
        error "ngrok failed to start. Check $LOG_DIR/ngrok.log"
        tail -20 "$LOG_DIR/ngrok.log"
        exit 1
    fi
    
    # Get tunnel URLs from ngrok API
    sleep 2
    TUNNELS=$(curl -s http://localhost:4040/api/tunnels 2>/dev/null)
    
    if [ -z "$TUNNELS" ]; then
        error "Could not connect to ngrok API"
        exit 1
    fi
    
    if command -v jq &> /dev/null; then
        HTTP_URL=$(echo "$TUNNELS" | jq -r '.tunnels[] | select(.config.addr | contains("'$HTTP_PORT'")) | .public_url' | head -1)
        WS_URL=$(echo "$TUNNELS" | jq -r '.tunnels[] | select(.config.addr | contains("'$WS_PORT'")) | .public_url' | head -1)
    else
        # Fallback without jq
        HTTP_URL=$(echo "$TUNNELS" | grep -o '"public_url":"https://[^"]*"' | head -1 | cut -d'"' -f4)
        WS_URL=$(echo "$TUNNELS" | grep -o '"public_url":"https://[^"]*"' | tail -1 | cut -d'"' -f4)
        warn "Install jq for more reliable URL parsing: brew install jq"
    fi
    
    if [ -z "$HTTP_URL" ] || [ -z "$WS_URL" ]; then
        error "Failed to get tunnel URLs. Check $LOG_DIR/ngrok.log"
        exit 1
    fi
    
    # Convert https to wss for WebSocket
    WS_URL_WSS="${WS_URL/https:/wss:}"
    WS_URL_WSS="${WS_URL_WSS/http:/ws:}"
    
    success "HTTP:      $HTTP_URL"
    success "WebSocket: $WS_URL_WSS"
    
    # Update frontend WebSocket URL
    log "Updating frontend WebSocket URL..."
    sed -i.bak "s|wsUrl: '.*'|wsUrl: '$WS_URL_WSS'|" public/js/app.js
    
    # Set APP_URL for invite links
    export APP_URL="$HTTP_URL"
    
else
    log "Mode: Local"
    HTTP_URL="http://localhost:$HTTP_PORT"
    WS_URL_WSS="ws://localhost:$WS_PORT"
    
    # Make sure frontend points to local WebSocket
    sed -i.bak "s|wsUrl: '.*'|wsUrl: '$WS_URL_WSS'|" public/js/app.js
    
    export APP_URL="$HTTP_URL"
fi

# --- Start Servers ---
log "Starting HTTP server on port $HTTP_PORT..."
php -S 0.0.0.0:$HTTP_PORT -t public > "$LOG_DIR/http.log" 2>&1 &
HTTP_PID=$!

sleep 1

log "Starting WebSocket server on port $WS_PORT..."
php websocket_server.php > "$LOG_DIR/websocket.log" 2>&1 &
WS_PID=$!

sleep 1

# Verify servers started
if ! ps -p $HTTP_PID > /dev/null 2>&1; then
    error "HTTP server failed to start. Check $LOG_DIR/http.log"
    exit 1
fi

if ! ps -p $WS_PID > /dev/null 2>&1; then
    error "WebSocket server failed to start. Check $LOG_DIR/websocket.log"
    exit 1
fi

# --- Status ---
echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
success "SPJALL CHAT RUNNING!"
echo ""
if [ "$MODE" == "ngrok" ]; then
    echo -e "🌐 Share this link: ${GREEN}$HTTP_URL${NC}"
else
    echo -e "🏠 Local: ${GREEN}$HTTP_URL${NC}"
fi
echo ""
echo "📝 Logs:"
echo "   $LOG_DIR/http.log"
echo "   $LOG_DIR/websocket.log"
[ "$MODE" == "ngrok" ] && echo "   $LOG_DIR/ngrok.log"
echo ""
echo -e "${YELLOW}Press Ctrl+C to stop${NC}"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# Keep running
wait $WS_PID

