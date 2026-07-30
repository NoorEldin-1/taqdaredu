import { defineConfig, loadEnv } from "vite";
import react from "@vitejs/plugin-react-swc";
import path from "path";
import { componentTagger } from "lovable-tagger";

// https://vitejs.dev/config/
export default defineConfig(({ mode }) => {
  // Vite does NOT load .env files into process.env — only into import.meta.env
  // for client code. Config-time values have to come through loadEnv, or
  // VITE_DEV_API_TARGET from .env.local is silently ignored and every proxied
  // request falls back to http://localhost (port 80) and 404s.
  const env = loadEnv(mode, process.cwd(), "");

  // Apache vhost serving this project's document root. Everything the SPA
  // fetches at a server path — /api, the legacy /api_* aliases, /uploads —
  // goes here, so the vhost's .htaccess does the same rewriting it does in
  // production.
  const apiTarget = env.VITE_DEV_API_TARGET || "http://localhost:8081";
  const proxyToApi = { target: apiTarget, changeOrigin: true };

  return {
  base: '/',
  build: {
    outDir: 'dist',
    assetsDir: '_app',
  },
  server: {
    host: "::",
    port: 8080,
    proxy: {
      // Current SPA calls canonical /api/<controller>/<endpoint> paths. Proxy
      // them to the local backend (served at http://localhost/api via a docroot
      // that maps /api → the CodeIgniter app). The legacy /api_* entries below
      // are kept for backward compatibility. Regex key matches only "/api/…".
      '^/api/': {
        target: apiTarget,
        changeOrigin: true,
        // Windows/Apache behind this dev proxy doesn't hand the JWT Authorization
        // header to PHP (works direct, dropped via the proxy hop). The backend
        // also accepts ?auth_token=, so translate Bearer → auth_token for the
        // proxied request. Dev-only: production serves SPA + API same-origin
        // (no proxy) and uses the header normally.
        configure: (proxy) => {
          proxy.on('proxyReq', (proxyReq, req) => {
            const auth = req.headers['authorization'];
            if (auth && /^Bearer\s+/i.test(auth)) {
              const token = auth.replace(/^Bearer\s+/i, '');
              const sep = proxyReq.path.includes('?') ? '&' : '?';
              proxyReq.path += sep + 'auth_token=' + encodeURIComponent(token);
            }
          });
        },
      },
      // Legacy aliases. These pointed at http://localhost/MyCommcation — the
      // local folder name of my-communication.uk, the project this codebase was
      // cloned from — so they 404'd here. They now follow apiTarget, where the
      // vhost's .htaccess 307s /api_<module>/* to /api/api_<module>/* exactly as
      // production does. New code should call /api/api_<module> directly.
      '/api_frontend': proxyToApi,
      '/api_courses': proxyToApi,
      '/api_payment': proxyToApi,
      '/api_notifications': proxyToApi,
      '/api_messages': proxyToApi,
      '/api_reports': proxyToApi,
      '/api_admin': proxyToApi,
      '/api_webhooks': proxyToApi,
      // Uploaded media. The vhost rewrites /uploads/* to /api/uploads/*.
      '/uploads': proxyToApi,
    },
  },
  plugins: [react(), mode === "development" && componentTagger()].filter(Boolean),
  resolve: {
    alias: {
      "@": path.resolve(__dirname, "./src"),
    },
  },
  };
});
