import { defineConfig } from "vite";
import react from "@vitejs/plugin-react-swc";
import path from "path";
import { componentTagger } from "lovable-tagger";

// https://vitejs.dev/config/
export default defineConfig(({ mode }) => ({
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
        target: process.env.VITE_DEV_API_TARGET || 'http://localhost',
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
      '/api_frontend': {
        target: 'http://localhost/MyCommcation',
        changeOrigin: true,
      },
      '/api_courses': {
        target: 'http://localhost/MyCommcation',
        changeOrigin: true,
      },
      '/api_payment': {
        target: 'http://localhost/MyCommcation',
        changeOrigin: true,
      },
      '/api_notifications': {
        target: 'http://localhost/MyCommcation',
        changeOrigin: true,
      },
      '/api_messages': {
        target: 'http://localhost/MyCommcation',
        changeOrigin: true,
      },
      '/api_reports': {
        target: 'http://localhost/MyCommcation',
        changeOrigin: true,
      },
      '/api_admin': {
        target: 'http://localhost/MyCommcation',
        changeOrigin: true,
      },
      '/api_webhooks': {
        target: 'http://localhost/MyCommcation',
        changeOrigin: true,
      },
      '/uploads': {
        target: 'http://localhost/MyCommcation',
        changeOrigin: true,
      },
    },
  },
  plugins: [react(), mode === "development" && componentTagger()].filter(Boolean),
  resolve: {
    alias: {
      "@": path.resolve(__dirname, "./src"),
    },
  },
}));
