/**
 * Inventory Collector — Web Scan
 *
 * Collects browser-available hardware info and POSTs it to the API.
 * Runs once per session when a user visits the portal.
 *
 * Data collected: OS, CPU logical cores, RAM estimate, GPU (WebGL),
 * screen resolution, timezone, user agent.
 */
(function () {
    const STORAGE_KEY = 'helpdesk_last_scan';
    const SCAN_INTERVAL_MS = 24 * 60 * 60 * 1000; // 1 day

    // Don't scan more than once per day
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
        if (ua.includes('Windows NT 10')) return { name: 'Windows', version: '10/11' };
        if (ua.includes('Windows NT 6.3')) return { name: 'Windows', version: '8.1' };
        if (ua.includes('Mac OS X')) {
            const match = ua.match(/Mac OS X ([\d_]+)/);
            return { name: 'macOS', version: match ? match[1].replace(/_/g, '.') : '' };
        }
        if (ua.includes('Linux')) return { name: 'Linux', version: '' };
        return { name: navigator.platform, version: '' };
    }

    const os = getOsInfo();

    const payload = {
        hostname: location.hostname,
        os_name: os.name,
        os_version: os.version,
        cpu_cores: navigator.hardwareConcurrency || null,
        ram_gb: navigator.deviceMemory || null,
        gpu_info: getGpuInfo(),
        screen_resolution: `${screen.width}x${screen.height}`,
        timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
        user_agent: navigator.userAgent,
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
})();
