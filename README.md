# Indobase Backend

## Deployment (Dokploy / reverse proxy)

If you put **nginx** (or another proxy) in front of the API and the console shows **405** on signup/login, the proxy is blocking **OPTIONS** (CORS preflight). Fix it by:

- **Allowing OPTIONS** and proxying them to this app (recommended), or  
- Using the snippet in **`docs/nginx-proxy-options.conf`** to handle OPTIONS at the proxy.

Ensure `_APP_CONSOLE_DOMAIN` or `_APP_CONSOLE_HOSTNAMES` includes your console host (e.g. `console.indobase.fun`).
