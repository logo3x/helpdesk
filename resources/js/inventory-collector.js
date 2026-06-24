/**
 * Inventory Collector — Web Scan
 *
 * Collects browser-available hardware info and POSTs it to the API.
 * Runs once per day when a user visits the portal.
 *
 * Data collected: OS (via userAgentData when available), CPU cores,
 * RAM estimate, GPU (WebGL), screen resolution, timezone, user agent.
 *
 * NOTE: browsers cannot expose the real PC hostname. The backend
 * identifies the asset by authenticated user_id + IP address.
 */
(function () {
    const STORAGE_KEY = 'helpdesk_last_scan';
    const SCAN_INTERVAL_MS = 24 * 60 * 60 * 1000; // 1 day

    const lastScan = localStorage.getItem(STORAGE_KEY);
    if (lastScan && Date.now() - parseInt(lastScan, 10) < SCAN_INTERVAL_MS) {
        return;
    }

    function getGpuInfo() {
        try {
            const canvas = document.createElement('canvas');
            const gl = canvas.getContext('webgl') || canvas.getContext('experimental-webgl');
            if (!gl) return null;
            const ext = gl.getExtension('WEBGL_debug_renderer_info');
            return ext ? gl.getParameter(ext.UNMASKED_RENDERER_WEBGL) : null;
        } catch {
            return null;
        }
    }

    function getOsInfo() {
        const ua = navigator.userAgent;

        // Windows 11 detection: NT 10 + userAgentData platform hints
        if (ua.includes('Windows NT 10') || ua.includes('Windows NT 6.3') || ua.includes('Windows')) {
            // Try modern userAgentData first (Chrome 90+, Edge 90+)
            if (navigator.userAgentData) {
                // getHighEntropyValues is async; we use the synchronous platform as fallback
                // and trigger a best-effort async update on next scan opportunity
                const platform = navigator.userAgentData.platform || '';
                if (platform.toLowerCase().includes('windows')) {
                    // We can't distinguish 10 vs 11 synchronously; mark for async below
                    return { name: 'Windows', version: '' };
                }
            }
            if (ua.includes('Windows NT 10')) return { name: 'Windows', version: '10/11' };
            if (ua.includes('Windows NT 6.3')) return { name: 'Windows', version: '8.1' };
            if (ua.includes('Windows NT 6.2')) return { name: 'Windows', version: '8' };
            if (ua.includes('Windows NT 6.1')) return { name: 'Windows', version: '7' };
            return { name: 'Windows', version: '' };
        }

        if (ua.includes('Mac OS X')) {
            const match = ua.match(/Mac OS X ([\d_]+)/);
            return { name: 'macOS', version: match ? match[1].replace(/_/g, '.') : '' };
        }
        if (ua.includes('Android')) {
            const match = ua.match(/Android ([\d.]+)/);
            return { name: 'Android', version: match ? match[1] : '' };
        }
        if (ua.includes('Linux')) return { name: 'Linux', version: '' };
        if (ua.includes('iPhone') || ua.includes('iPad')) {
            const match = ua.match(/OS ([\d_]+)/);
            return { name: 'iOS', version: match ? match[1].replace(/_/g, '.') : '' };
        }

        return { name: navigator.platform || 'Desconocido', version: '' };
    }

    function buildAndSend(osOverride) {
        const os = osOverride || getOsInfo();

        const payload = {
            // hostname is intentionally omitted — browser cannot read client hostname.
            // Backend uses user_id + IP to find/create the asset.
            os_name: os.name || null,
            os_version: os.version || null,
            cpu_cores: navigator.hardwareConcurrency || null,
            ram_gb: navigator.deviceMemory || null,
            gpu_info: getGpuInfo(),
            screen_resolution: screen.width && screen.height ? screen.width + 'x' + screen.height : null,
            timezone: Intl.DateTimeFormat().resolvedOptions().timeZone || null,
            user_agent: navigator.userAgent,
            browser_language: navigator.language || null,
            touch_points: navigator.maxTouchPoints > 0 ? navigator.maxTouchPoints : null,
        };

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

        fetch('/api/inventory/web-scan', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
            },
            credentials: 'same-origin',
            body: JSON.stringify(payload),
        })
            .then(function (res) {
                if (res.ok) {
                    localStorage.setItem(STORAGE_KEY, String(Date.now()));
                }
            })
            .catch(function () {
                // Silent fail — inventory is best-effort
            });
    }

    // Try to get high-entropy OS hints (Windows 10 vs 11, exact versions)
    // Only available in Chromium-based browsers. Falls back to UA-based detection.
    if (navigator.userAgentData && typeof navigator.userAgentData.getHighEntropyValues === 'function') {
        navigator.userAgentData
            .getHighEntropyValues(['platform', 'platformVersion', 'architecture'])
            .then(function (hints) {
                let osName = hints.platform || '';
                let osVersion = hints.platformVersion || '';

                // Detect Windows 11: platform = 'Windows', platformVersion >= 13
                if (osName === 'Windows' && osVersion) {
                    const major = parseInt(osVersion.split('.')[0], 10);
                    osVersion = major >= 13 ? '11' : '10';
                }

                buildAndSend({ name: osName || getOsInfo().name, version: osVersion });
            })
            .catch(function () {
                buildAndSend(null);
            });
    } else {
        buildAndSend(null);
    }
})();
