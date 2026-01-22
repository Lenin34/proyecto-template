/**
 * simple-routing.js – versión mejorada
 * Reemplaza FOSJsRouting con rutas JSON exportadas desde Symfony
 * Exposición global de Routing.routes, generate y generateAsync
 */

(function() {
    const ROUTES_URL = '/assets/routes.json';
    let routesCache = null;
    let loadingPromise = null;

    /**
     * Carga las rutas desde routes.json
     * @returns {Promise<Object>} Rutas cargadas
     */
    function loadRoutes() {
        if (routesCache) return Promise.resolve(routesCache);
        if (loadingPromise) return loadingPromise;

        loadingPromise = fetch(ROUTES_URL, { credentials: 'same-origin' })
            .then(res => {
                if (!res.ok) throw new Error(`[Routing] Failed to load ${ROUTES_URL}: ${res.status}`);
                return res.json();
            })
            .then(json => {
                routesCache = json || {};
                window.Routing.routes = routesCache; // Exponer globalmente
                return routesCache;
            })
            .catch(err => {
                console.error('[Routing] Unable to load', ROUTES_URL, err);
                routesCache = {};
                window.Routing.routes = routesCache;
                return routesCache;
            });

        return loadingPromise;
    }

    /**
     * Sustituye parámetros :param o {param} en la URL
     * @param {string} url
     * @param {Object} params
     * @returns {string}
     */
    function applyParams(url, params) {
        let out = url || '';
        if (!params) return out;

        for (const key in params) {
            if (!Object.prototype.hasOwnProperty.call(params, key)) continue;
            const value = encodeURIComponent(params[key]);
            // Reemplazar :param
            out = out.replace(new RegExp(':' + key + '(?![A-Za-z0-9_])', 'g'), value);
            // Reemplazar {param}
            out = out.replace(new RegExp('\\{' + key + '\\}', 'g'), value);
        }
        return out;
    }

    /**
     * Genera URL de forma asíncrona
     * @param {string} name Nombre de la ruta
     * @param {Object} params Parámetros opcionales
     * @returns {Promise<string>}
     */
    async function generateAsync(name, params = {}) {
        const routes = await loadRoutes();
        const pattern = routes[name];
        if (!pattern) {
            console.warn('[Routing] Route not found:', name);
            return '';
        }
        return applyParams(pattern, params);
    }

    /**
     * Genera URL de forma síncrona
     * @param {string} name Nombre de la ruta
     * @param {Object} params Parámetros opcionales
     * @returns {string}
     */
    function generateSync(name, params = {}) {
        if (!routesCache) {
            // Cargar en background
            loadRoutes();
            console.warn('[Routing] Routes not loaded yet; returning empty string. Use Routing.ready() or generateAsync().');
            return '';
        }
        const pattern = routesCache[name];
        if (!pattern) {
            console.warn('[Routing] Route not found:', name);
            return '';
        }
        return applyParams(pattern, params);
    }

    // Exponer global
    window.Routing = window.Routing || {};
    window.Routing.generate = generateSync;
    window.Routing.generateAsync = generateAsync;
    window.Routing.ready = loadRoutes;
    window.Routing.routes = routesCache; // inicialmente null
})();
