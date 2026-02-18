# Indobase Backend

## Deployment (Dokploy / Traefik)

### Nginx 403 "directory index of /app/ is forbidden" or OPTIONS 405

If the API is behind Traefik (or any proxy) and you get **403 Forbidden**, the app’s **router protection** is blocking the request: the request hostname must be in the allowed platform hostnames.

**Fix:** Replace the default nginx config with one that **proxy_passes** all traffic to the app. Example: **`docker/nginx-default.conf`**. If nginx and app are in the same pod, set **`PORT=8080`** for the app and use `proxy_pass http://127.0.0.1:8080`. If nginx is a sidecar, use the app service URL (e.g. `http://indobase-bck-n6hiqf:80`). Mount the config into the nginx container and reload. Or deploy without the nginx layer so Traefik talks directly to the app on port 80.

### 403 from the app (router protection)

If the API returns **403** with a message about "Router protection" or "this domain", set on the **backend** service: **`_APP_DOMAIN=api.indobase.fun`**, **`_APP_CONSOLE_DOMAIN=console.indobase.fun`**, and optionally **`_APP_CONSOLE_HOSTNAMES=console.indobase.fun`**. Then restart/redeploy.

### 405 on signup/login (console)

If the proxy blocks **OPTIONS** (CORS preflight), allow OPTIONS and proxy them to this app, or use **`docs/nginx-proxy-options.conf`**.
