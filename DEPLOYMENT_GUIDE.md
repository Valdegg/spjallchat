# VPS Deployment Guide - Spjall Chat

Complete guide for deploying the PHP-based Spjall chat application on Ubuntu VPS with systemd, Caddy, and HTTPS.

## Table of Contents

1. [Prerequisites](#prerequisites)
2. [Initial Server Setup](#initial-server-setup)
3. [Application Setup](#application-setup)
4. [systemd Services](#systemd-services)
5. [Caddy Configuration](#caddy-configuration)
6. [DNS Configuration](#dns-configuration)
7. [Troubleshooting](#troubleshooting)
8. [Maintenance & Updates](#maintenance--updates)

---

## Prerequisites

- Ubuntu 22.04+ VPS
- Root or sudo access
- Domain name configured
- SSH access to server

---

## Initial Server Setup

### 1. Update System Packages

```bash
apt update && apt upgrade -y
```

### 2. Install Core Dependencies

```bash
apt install -y \
    git \
    php8.1 \
    php8.1-cli \
    php8.1-sqlite3 \
    sqlite3 \
    caddy
```

### 3. Verify Installations

```bash
php --version  # Should be 8.1+
sqlite3 --version
caddy version
```

---

## Application Setup

### 1. Clone Repository

```bash
# Recommended location
cd /opt
git clone https://github.com/YourUsername/spjallchat.git
cd spjallchat
```

### 2. Initialize Database

```bash
chmod +x setup.sh
./setup.sh
```

**Output:**
```
✓ Created data directory
✓ Database initialized with schema
✓ Created seed invite: WELCOME1
```

### 3. Set Permissions

```bash
# Ensure data directory is writable
chmod -R 755 data
chown -R www-data:www-data data
```

### 4. Manual Verification (Important!)

**Terminal 1 - HTTP Server:**
```bash
cd /opt/spjallchat
php -S 0.0.0.0:8080 -t public
# Should see: "Development Server started"
# Test: curl http://localhost:8080
```

**Terminal 2 - WebSocket Server:**
```bash
cd /opt/spjallchat
php websocket_server.php
# Should see: "=== Spjall WebSocket Server ==="
# Should see: "Listening on ws://0.0.0.0:8081"
```

**Terminal 3 - Test:**
```bash
curl http://localhost:8080
curl http://localhost:8080/join/WELCOME1
```

If both work, proceed to systemd setup. **Stop manual processes** (Ctrl+C) before continuing.

---

## systemd Services

### 1. Create HTTP Service File

```bash
sudo nano /etc/systemd/system/spjallchat-http.service
```

**Content:**
```ini
[Unit]
Description=Spjall Chat HTTP Server
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/opt/spjallchat
ExecStart=/usr/bin/php -S 0.0.0.0:8080 -t public
Restart=always
RestartSec=10
StandardOutput=journal
StandardError=journal

[Install]
WantedBy=multi-user.target
```

### 2. Create WebSocket Service File

```bash
sudo nano /etc/systemd/system/spjallchat-websocket.service
```

**Content:**
```ini
[Unit]
Description=Spjall Chat WebSocket Server
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/opt/spjallchat
ExecStart=/usr/bin/php websocket_server.php
Restart=always
RestartSec=10
StandardOutput=journal
StandardError=journal

[Install]
WantedBy=multi-user.target
```

### 3. Enable and Start Services

```bash
# Reload systemd
sudo systemctl daemon-reload

# Enable services (start on boot)
sudo systemctl enable spjallchat-http
sudo systemctl enable spjallchat-websocket

# Start services
sudo systemctl start spjallchat-http
sudo systemctl start spjallchat-websocket

# Check status
sudo systemctl status spjallchat-http
sudo systemctl status spjallchat-websocket
```

### 4. Verify Services

```bash
# Test endpoints
curl http://localhost:8080
curl http://localhost:8080/join/WELCOME1

# View logs if needed
journalctl -u spjallchat-http -n 50 --no-pager
journalctl -u spjallchat-websocket -n 50 --no-pager
```

---

## Caddy Configuration

### 1. Update WebSocket URL in Frontend

Before configuring Caddy, update the WebSocket URL in the frontend to use your domain:

```bash
cd /opt/spjallchat
# Replace YOUR_DOMAIN with your actual domain
sed -i "s|wsUrl: '.*'|wsUrl: 'wss://YOUR_DOMAIN/ws'|" public/js/app.js
```

### 2. Create Caddyfile

```bash
sudo nano /etc/caddy/Caddyfile
```

### 3. Configuration Template

```caddyfile
# Replace YOUR_DOMAIN with your actual domain

YOUR_DOMAIN {
    # Proxy WebSocket connections to WebSocket server
    # Caddy automatically handles WebSocket upgrade for this route
    handle /ws {
        reverse_proxy localhost:8081 {
            header_up X-Real-IP {remote_host}
        }
    }
    
    # Proxy all other requests to HTTP server
    handle {
        reverse_proxy localhost:8080 {
            header_up X-Real-IP {remote_host}
        }
    }
}
```

**Key Points:**
- WebSocket route (`/ws`) must come first
- Caddy automatically upgrades HTTP to WebSocket for `/ws` route
- All other requests go to the HTTP server
- The WebSocket server doesn't need path-based routing - it accepts all connections

### 4. Validate and Reload

```bash
# Validate configuration
sudo caddy validate --config /etc/caddy/Caddyfile

# Reload Caddy
sudo systemctl reload caddy

# Check status
sudo systemctl status caddy
```

### 5. Test Configuration

```bash
# Test HTTP
curl -I https://YOUR_DOMAIN/

# Test join page
curl -I https://YOUR_DOMAIN/join/WELCOME1

# Test WebSocket endpoint (should return 101 Switching Protocols)
curl -i -N -H "Connection: Upgrade" -H "Upgrade: websocket" \
  -H "Sec-WebSocket-Key: test" -H "Sec-WebSocket-Version: 13" \
  https://YOUR_DOMAIN/ws
```

**Note:** The WebSocket test should return `HTTP/1.1 101 Switching Protocols` if working correctly.

---

## DNS Configuration

### 1. Configure DNS Records

In your domain registrar (e.g., Porkbun, Cloudflare):

- **Type:** A
- **Name:** @ (or blank for root domain)
- **Value:** YOUR_VPS_IP
- **TTL:** 300 (or default)

### 2. Wait for Propagation

DNS changes can take 5-60 minutes to propagate. Check with:

```bash
dig YOUR_DOMAIN +short
# Should show your VPS IP
```

### 3. Caddy Will Auto-Obtain SSL

Once DNS points to your VPS, Caddy will automatically:
- Obtain SSL certificate from Let's Encrypt
- Enable HTTPS
- Set up HTTP→HTTPS redirects

Check certificate status:
```bash
journalctl -u caddy -n 50 --no-pager | grep -E "(certificate|tls|obtained)"
```

---

## Troubleshooting

### Port Conflicts

**Problem:** Service can't start because port is in use.

**Solution:**
```bash
# Find what's using the port
lsof -i :8080  # HTTP
lsof -i :8081  # WebSocket

# Kill the process
kill <PID>

# Or kill by name
pkill -f "php.*-S.*8080"
pkill -f "php.*websocket_server"
```

### WebSocket Won't Connect

**Problem:** Frontend can't connect to WebSocket server.

**Solution:**
```bash
# Check WebSocket service is running
systemctl status spjallchat-websocket

# Check WebSocket URL in app.js matches your domain
grep wsUrl /opt/spjallchat/public/js/app.js

# Test WebSocket server directly
curl -i -N -H "Connection: Upgrade" -H "Upgrade: websocket" -H "Sec-WebSocket-Key: test" http://localhost:8081

# Check Caddy logs for WebSocket upgrade
journalctl -u caddy -n 100 --no-pager | grep -i websocket
```

### Database Permission Issues

**Problem:** Can't write to database.

**Solution:**
```bash
# Fix permissions
sudo chown -R www-data:www-data /opt/spjallchat/data
sudo chmod -R 755 /opt/spjallchat/data
```

### SSL Certificate Not Obtaining

**Problem:** DNS not pointing to VPS, or port 80/443 blocked.

**Solution:**
```bash
# Check DNS
dig YOUR_DOMAIN +short

# Check ports are open
sudo netstat -tulpn | grep -E ':(80|443)'

# Check Caddy logs
journalctl -u caddy -n 100 --no-pager | grep -i "certificate\|tls\|error"
```

### Service Won't Start

**Problem:** Check logs for errors.

**Solution:**
```bash
# View service logs
journalctl -u spjallchat-http -n 100 --no-pager
journalctl -u spjallchat-websocket -n 100 --no-pager

# Common issues:
# - Wrong paths in service file
# - PHP extensions not installed (php8.1-sqlite3)
# - Database file permissions
```

---

## Maintenance & Updates

### Quick Restart

```bash
sudo systemctl restart spjallchat-http
sudo systemctl restart spjallchat-websocket
```

### Full Update (Pull Code + Restart)

```bash
cd /opt/spjallchat
git pull
sudo systemctl restart spjallchat-http
sudo systemctl restart spjallchat-websocket
```

### Viewing Logs

```bash
# HTTP logs (follow)
journalctl -u spjallchat-http -f

# WebSocket logs (follow)
journalctl -u spjallchat-websocket -f

# Caddy logs (follow)
journalctl -u caddy -f

# All services status
systemctl status spjallchat-http spjallchat-websocket caddy
```

### Backup Database

```bash
# Create backup
cp /opt/spjallchat/data/spjallchat.db /opt/spjallchat/data/spjallchat.db.backup.$(date +%Y%m%d)

# Restore from backup
cp /opt/spjallchat/data/spjallchat.db.backup.YYYYMMDD /opt/spjallchat/data/spjallchat.db
```

---

## Architecture Summary

```
┌─────────────────────────────────────────┐
│           Internet / Users              │
└─────────────────┬───────────────────────┘
                  │ HTTPS (443)
                  │
         ┌────────▼────────┐
         │   Caddy (443)   │  ← SSL Termination, Reverse Proxy
         └────────┬────────┘
                  │
     ┌────────────┼────────────┐
     │            │            │
┌────▼────┐  ┌────▼────┐  ┌───▼────┐
│   /ws   │  │   /     │  │        │
│ WebSocket│  │  HTTP   │  │        │
└────┬────┘  └────┬────┘  └────────┘
     │            │
     │      ┌─────▼─────┐
     │      │ HTTP      │
     │      │ (8080)    │
     │      └─────┬─────┘
     │            │
┌────▼────────────▼──┐
│ WebSocket (8081)   │
│ PHP WebSocket      │
│ Server             │
└────────┬───────────┘
         │
┌────────▼────────┐
│  SQLite DB      │  ← Chat Data
│  (spjallchat.db)│
└─────────────────┘
```

**Key Components:**
- **Caddy:** Reverse proxy, SSL, WebSocket upgrade
- **HTTP Server:** PHP built-in server on port 8080, serves frontend
- **WebSocket Server:** Custom PHP WebSocket server on port 8081
- **SQLite:** Database for users, messages, conversations
- **systemd:** Service management, auto-start on boot

---

## Checklist for New Deployment

- [ ] Server packages installed (git, php8.1, sqlite3, caddy)
- [ ] Repository cloned to `/opt/spjallchat`
- [ ] Database initialized with `./setup.sh`
- [ ] Manual verification passed (both services work)
- [ ] systemd service files created and enabled
- [ ] WebSocket URL updated in `public/js/app.js` to use domain
- [ ] Caddyfile configured with correct domain
- [ ] DNS A record points to VPS IP
- [ ] SSL certificate obtained (check Caddy logs)
- [ ] HTTP endpoints working (test with curl)
- [ ] Frontend accessible via HTTPS
- [ ] WebSocket connections working (test in browser)
- [ ] Services auto-start on reboot (test with `sudo reboot`)

---

## Notes

- **File Paths:** All paths assume `/opt/spjallchat` - adjust if different
- **Ports:** HTTP uses 8080, WebSocket uses 8081
- **WebSocket URL:** Must be updated in `public/js/app.js` to match your domain
- **SSL:** Caddy automatically obtains and renews certificates
- **Database:** SQLite file is in `data/spjallchat.db` - ensure proper permissions

---

## Additional Resources

- [Caddy Documentation](https://caddyserver.com/docs/)
- [systemd Service Files](https://www.freedesktop.org/software/systemd/man/systemd.service.html)
- [PHP Built-in Server](https://www.php.net/manual/en/features.commandline.webserver.php)

---

**Last Updated:** January 2026
**Tested On:** Ubuntu 22.04, PHP 8.1+

