const { spawn } = require('child_process');
const fs = require('fs');
const path = require('path');
const os = require('os');

const CHROME_PATH = 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const CDP_PORT = 9244;
const USER_DATA_DIR = path.join(os.tmpdir(), 'pos_ilp_dash_cdp_' + Date.now());
fs.mkdirSync(USER_DATA_DIR, { recursive: true });

const SCREENSHOT_DIR = path.resolve(__dirname, '../../brain/screenshots_fase2');
fs.mkdirSync(SCREENSHOT_DIR, { recursive: true });

function sleep(ms) {
    return new Promise(r => setTimeout(r, ms));
}

class CDPClient {
    constructor(wsUrl) {
        this.ws = new WebSocket(wsUrl);
        this.id = 0;
        this.pending = new Map();
    }
    async connect() {
        await new Promise((resolve, reject) => {
            this.ws.onopen = resolve;
            this.ws.onerror = reject;
        });
        this.ws.onmessage = (msg) => {
            const data = JSON.parse(msg.data);
            if (data.id && this.pending.has(data.id)) {
                const { resolve, reject } = this.pending.get(data.id);
                this.pending.delete(data.id);
                if (data.error) reject(new Error(JSON.stringify(data.error)));
                else resolve(data.result);
            }
        };
    }
    send(method, params = {}) {
        const id = ++this.id;
        return new Promise((resolve, reject) => {
            this.pending.set(id, { resolve, reject });
            this.ws.send(JSON.stringify({ id, method, params }));
        });
    }
    async eval(expression) {
        const res = await this.send('Runtime.evaluate', {
            expression,
            awaitPromise: true,
            returnByValue: true
        });
        return res.result ? res.result.value : undefined;
    }
    async captureScreenshot(filename) {
        const res = await this.send('Page.captureScreenshot', { format: 'png' });
        const filePath = path.join(SCREENSHOT_DIR, filename);
        fs.writeFileSync(filePath, Buffer.from(res.data, 'base64'));
        return filePath;
    }
    close() {
        this.ws.close();
    }
}

(async () => {
    const chrome = spawn(CHROME_PATH, [
        '--headless=new',
        `--remote-debugging-port=${CDP_PORT}`,
        `--user-data-dir=${USER_DATA_DIR}`,
        '--no-first-run',
        '--no-default-browser-check',
        '--disable-gpu',
        '--window-size=1440,900',
        'about:blank'
    ], { stdio: 'ignore' });

    let cdp = null;
    try {
        let targets = null;
        for (let i = 0; i < 30; i++) {
            try {
                const res = await fetch(`http://127.0.0.1:${CDP_PORT}/json/list`);
                targets = await res.json();
                if (Array.isArray(targets) && targets.find(t => t.type === 'page')) break;
            } catch (e) {}
            await sleep(250);
        }
        const pageTarget = targets && targets.find(t => t.type === 'page');
        cdp = new CDPClient(pageTarget.webSocketDebuggerUrl);
        await cdp.connect();
        await cdp.send('Page.enable');
        await cdp.send('Runtime.enable');

        // 1. Manager Login
        await cdp.send('Page.navigate', { url: 'http://127.0.0.1:8000/login?role=manager' });
        await sleep(1500);
        await cdp.eval(`(() => {
            const u = document.getElementById('manager-identifier');
            const p = document.getElementById('manager-password');
            if (u && p) {
                u.value = 'manager1';
                u.dispatchEvent(new Event('input', { bubbles: true }));
                p.value = 'manager123';
                p.dispatchEvent(new Event('input', { bubbles: true }));
                const form = document.getElementById('form-manager');
                form.querySelector('button[type="submit"]').click();
            }
        })()`);
        await sleep(2500);
        await cdp.captureScreenshot('03_manager_dashboard.png');
        console.log('Captured 03_manager_dashboard.png');

        // 2. Owner Login
        await cdp.send('Page.navigate', { url: 'http://127.0.0.1:8000/login?role=owner' });
        await sleep(1500);
        await cdp.eval(`(() => {
            const u = document.getElementById('owner-identifier');
            const p = document.getElementById('owner-password');
            if (u && p) {
                u.value = 'owner1';
                u.dispatchEvent(new Event('input', { bubbles: true }));
                p.value = 'owner123';
                p.dispatchEvent(new Event('input', { bubbles: true }));
                const form = document.getElementById('form-owner');
                form.querySelector('button[type="submit"]').click();
            }
        })()`);
        await sleep(2500);
        await cdp.captureScreenshot('04_owner_dashboard.png');
        console.log('Captured 04_owner_dashboard.png');

    } catch (e) {
        console.error(e);
    } finally {
        if (cdp) cdp.close();
        chrome.kill();
        try { fs.rmSync(USER_DATA_DIR, { recursive: true, force: true }); } catch (e) {}
    }
})();
