/**
 * AutoTranslator for Pterodactyl Panel
 * Author: ElDeiividMtz
 * License: MIT
 *
 * NATIVE interception strategy — zero flicker:
 *   1. Reads window.__TRANSLATIONS__ (sync, injected in <head> by server).
 *   2. Monkey-patches document.createTextNode() so React creates
 *      text nodes ALREADY translated (English never hits the DOM).
 *   3. Patches Node.prototype.nodeValue setter so React state
 *      updates that change text are also caught instantly.
 *   4. MutationObserver as safety net for attributes and edge cases.
 *   5. Fallback: fetches /translations/{lang}.json if inline injection is missing.
 */

// ── Translation data ──
let exactMap = new Map<string, string>();
let partialEntries: [string, string][] = [];
let minPartialLength = Infinity;
let allTranslations: Record<string, string> = {};
let translationsReady = false;
let patchesInstalled = false;

// Brand names that should NEVER be translated
const NEVER_TRANSLATE = new Set([
    'Pterodactyl', 'pterodactyl', 'Pterodactyl Software', 'Pterodactyl Panel',
    'Pterodactyl®', 'Wings', 'SFTP', 'SSH', 'MySQL', 'MariaDB',
    'Redis', 'Docker', 'GitHub', 'Discord', 'PayPal', 'Stripe', 'Linux',
    'Ubuntu', 'Debian', 'CentOS', 'nginx', 'Apache', 'PHP', 'Node.js',
    'JavaScript', 'TypeScript', 'Laravel', 'React', 'Webpack', 'Gravatar',
]);

// Regex patterns for technical content
const TECHNICAL_PATTERNS: RegExp[] = [
    /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i,
    /^[0-9a-f]{8,}$/i,
    /^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}(:\d+)?$/,
    /^[\d,.]+\s*(%|MB|GB|KB|TB|ms|s|B|GiB|MiB|KiB)?$/i,
    /^[^\s@]+@[^\s@]+\.[^\s@]+$/,
    /^[\/\\][\w\/\\.@-]+$/,
    /^(sftp|ssh|jdbc|mysql|https?|ftp):\/\//i,
    /^(sha256|sha1|md5|sha512):/i,
    /^[A-Za-z0-9_-]{32,}$/,
    /^v?\d+\.\d+(\.\d+)*(-[\w.]+)?$/,
    /^[\w.-]+\.(io|com|org)\/[\w./-]+(:\w+)?$/,
    /^[A-Z][A-Z0-9_]{2,}$/,
    /^jdbc:/i,
    /^[\d*\/,-]+(\s+[\d*\/,-]+){3,5}$/,
    /^s\d+_\w+$/,
    /^\w+\.[0-9a-f]{6,}$/i,
];

const SKIP_DICTIONARY_ENTRIES = new Set([
    'No', 'Error', 'CPU', 'of', 'Admin',
]);

function isTechnicalText(text: string): boolean {
    for (const pattern of TECHNICAL_PATTERNS) {
        if (pattern.test(text)) return true;
    }
    return false;
}

// ── Dictionary management ──

function buildLookup(dict: Record<string, string>): void {
    allTranslations = { ...allTranslations, ...dict };

    for (const brand of NEVER_TRANSLATE) delete allTranslations[brand];
    for (const skip of SKIP_DICTIONARY_ENTRIES) delete allTranslations[skip];
    for (const [key, val] of Object.entries(allTranslations)) {
        if (key === val) delete allTranslations[key];
    }

    exactMap.clear();
    partialEntries = [];

    for (const [en, translated] of Object.entries(allTranslations)) {
        if (translated) {
            exactMap.set(en.trim(), translated);
            if (en.split(' ').length >= 3) {
                partialEntries.push([en, translated]);
            }
        }
    }
    partialEntries.sort((a, b) => b[0].length - a[0].length);
    minPartialLength = partialEntries.length > 0
        ? partialEntries[partialEntries.length - 1][0].length
        : Infinity;
    translationsReady = exactMap.size > 0;
}

// ── Core translate function (must be FAST — called on every text node) ──

function translateText(text: string): string | null {
    const trimmed = text.trim();
    if (!trimmed || trimmed.length < 2) return null;
    if (NEVER_TRANSLATE.has(trimmed)) return null;
    if (isTechnicalText(trimmed)) return null;

    const exact = exactMap.get(trimmed);
    if (exact) {
        const leading = text.match(/^\s*/)?.[0] || '';
        const trailing = text.match(/\s*$/)?.[0] || '';
        return leading + exact + trailing;
    }

    // Partial matching for long phrases embedded in text
    if (trimmed.length >= minPartialLength) {
        let result = trimmed;
        let changed = false;
        for (const [en, translated] of partialEntries) {
            if (en.length > result.length) continue; // early skip
            if (result.includes(en)) {
                result = result.split(en).join(translated);
                changed = true;
            }
        }
        if (changed) {
            const leading = text.match(/^\s*/)?.[0] || '';
            const trailing = text.match(/\s*$/)?.[0] || '';
            return leading + result + trailing;
        }
    }

    return null;
}

// ── NATIVE PATCHES — intercept React's DOM operations ──

const _origCreateTextNode = document.createTextNode.bind(document);
const _origNodeValueDesc = Object.getOwnPropertyDescriptor(Node.prototype, 'nodeValue')!;
const _origTextContentDesc = Object.getOwnPropertyDescriptor(Node.prototype, 'textContent')!;

function installPatches(): void {
    if (patchesInstalled) return;
    patchesInstalled = true;

    // Patch 1: document.createTextNode()
    document.createTextNode = function (data: string): Text {
        if (translationsReady && data) {
            const translated = translateText(data);
            if (translated !== null) {
                return _origCreateTextNode(translated);
            }
        }
        return _origCreateTextNode(data);
    };

    // Patch 2: Node.prototype.nodeValue setter
    Object.defineProperty(Node.prototype, 'nodeValue', {
        get: _origNodeValueDesc.get,
        set(value: string | null) {
            if (translationsReady && this.nodeType === Node.TEXT_NODE && value) {
                if (!isInSkippedElement(this)) {
                    const translated = translateText(value);
                    if (translated !== null) {
                        _origNodeValueDesc.set!.call(this, translated);
                        return;
                    }
                }
            }
            _origNodeValueDesc.set!.call(this, value);
        },
        configurable: true,
        enumerable: true,
    });

    // Patch 3: textContent setter for elements
    Object.defineProperty(Node.prototype, 'textContent', {
        get: _origTextContentDesc.get,
        set(value: string | null) {
            if (
                translationsReady &&
                value &&
                this.nodeType === Node.ELEMENT_NODE &&
                !isSkippedTag((this as Element).tagName) &&
                !isInSkippedElement(this)
            ) {
                const translated = translateText(value);
                if (translated !== null) {
                    _origTextContentDesc.set!.call(this, translated);
                    return;
                }
            }
            _origTextContentDesc.set!.call(this, value);
        },
        configurable: true,
        enumerable: true,
    });
}

const SKIPPED_TAGS = new Set([
    'SCRIPT', 'STYLE', 'TEXTAREA', 'CODE', 'PRE', 'SVG', 'INPUT', 'SELECT',
]);

function isSkippedTag(tag: string): boolean {
    return SKIPPED_TAGS.has(tag);
}

function isInSkippedElement(node: Node): boolean {
    let el = node.nodeType === Node.ELEMENT_NODE
        ? (node as Element)
        : node.parentElement;
    while (el) {
        if (SKIPPED_TAGS.has(el.tagName)) return true;
        if (el.classList?.contains('notranslate') || el.hasAttribute?.('data-notranslate')) return true;
        if (el.hasAttribute?.('data-clipboard-text')) return true;
        if (el.tagName === 'CODE') return true;
        el = el.parentElement;
    }
    return false;
}

// ── Attribute translator (placeholders, titles) ──

function translateAttributes(el: Element): void {
    for (const attr of ['placeholder', 'title', 'aria-label', 'data-tooltip']) {
        const value = el.getAttribute(attr);
        if (value && !isTechnicalText(value.trim())) {
            const translated = translateText(value);
            if (translated) el.setAttribute(attr, translated);
        }
    }
}

// Full subtree sweep for attributes + any text missed by patches
function translateSubtree(root: Node): void {
    if (!translationsReady) return;

    const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT | NodeFilter.SHOW_ELEMENT, {
        acceptNode(node: Node) {
            if (node.nodeType === Node.ELEMENT_NODE) {
                const el = node as Element;
                if (SKIPPED_TAGS.has(el.tagName)) return NodeFilter.FILTER_REJECT;
                if (el.classList?.contains('notranslate') || el.hasAttribute?.('data-notranslate')) {
                    return NodeFilter.FILTER_REJECT;
                }
            }
            return NodeFilter.FILTER_ACCEPT;
        },
    });

    let node: Node | null;
    while ((node = walker.nextNode())) {
        if (node.nodeType === Node.TEXT_NODE) {
            const text = node.textContent;
            if (text && text.trim().length >= 2) {
                const translated = translateText(text);
                if (translated !== null && translated !== text) {
                    _origNodeValueDesc.set!.call(node, translated);
                }
            }
        } else if (node.nodeType === Node.ELEMENT_NODE) {
            translateAttributes(node as Element);
        }
    }
}

// ── Async fallback (only when server injection is missing) ──

async function loadRuntimeTranslations(lang: string): Promise<void> {
    try {
        const resp = await fetch(`/translations/${lang}.json`);
        if (resp.ok) {
            const data = await resp.json();
            if (data && Object.keys(data).length > 0) {
                buildLookup(data);
                if (!patchesInstalled) installPatches();
                translateSubtree(document.body);
            }
        }
    } catch {
        // Not available
    }
}

// ── Language persistence (cookie + localStorage) ──

const LANG_STORAGE_KEY = 'autotranslator_lang';

function getStoredLang(): string | null {
    try {
        return localStorage.getItem(LANG_STORAGE_KEY);
    } catch {
        return null;
    }
}

function storeLang(lang: string): void {
    try {
        localStorage.setItem(LANG_STORAGE_KEY, lang);
    } catch { /* private browsing */ }
    // Set cookie so the server can detect language on next page load (login page, etc.)
    document.cookie = `${LANG_STORAGE_KEY}=${lang};path=/;max-age=${60 * 60 * 24 * 365};SameSite=Lax`;
}

function detectBrowserLang(supported: Record<string, string> | null): string | null {
    if (!supported) return null;
    const codes = Object.keys(supported);
    for (const navLang of navigator.languages || [navigator.language]) {
        const code = navLang.substring(0, 2).toLowerCase();
        if (codes.includes(code)) return code;
    }
    return null;
}

// ── Initialization ──

export function initAutoTranslator(): void {
    const siteConfig = (window as any).SiteConfiguration;
    const supported = siteConfig?.translatorLanguages || null;

    // Language detection chain:
    // 1. Server-detected lang (authenticated user, cookie, or Accept-Language)
    // 2. localStorage (previous preference, survives across sessions)
    // 3. Browser navigator.languages (first visit auto-detection)
    let userLang = siteConfig?.translatorLang
        || (window as any).PterodactylUser?.language
        || getStoredLang()
        || detectBrowserLang(supported)
        || 'en';

    if (userLang === 'en') return;

    // Validate language is supported
    if (supported && !supported[userLang]) return;

    // Persist the detected language for future visits (login page, etc.)
    storeLang(userLang);

    // Expose detected lang for the language selector widget
    (window as any).__AUTOTRANSLATOR_LANG__ = userLang;

    // ── Source A (sync): Server-injected translations from <head> ──
    const serverTranslations = (window as any).__TRANSLATIONS__;
    const hasInline = serverTranslations
        && typeof serverTranslations === 'object'
        && Object.keys(serverTranslations).length > 0;

    if (hasInline) {
        buildLookup(serverTranslations);
    }

    // Install native patches BEFORE React renders anything
    if (translationsReady) {
        installPatches();
    }

    // Start the safety-net observer
    startObserver();

    // ── Source B (async fallback): Only if inline was empty ──
    // This covers: login page (no auth), error pages, or when inline injection is skipped
    if (!hasInline) {
        loadRuntimeTranslations(userLang);
    }
}

/**
 * Change language at runtime (called by the language selector widget).
 * Persists choice, reloads page so server injects correct translations.
 */
export function changeLanguage(lang: string): void {
    storeLang(lang);
    window.location.reload();
}

let observerInstance: MutationObserver | null = null;

function startObserver(): void {
    if (observerInstance) return; // prevent double-init (e.g. HMR)

    if (translationsReady) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => translateSubtree(document.body));
        } else {
            translateSubtree(document.body);
        }
    }

    // Batched MutationObserver: collect nodes, process once per frame
    let pendingNodes: Set<Node> = new Set();
    let rafScheduled = false;

    function processPendingNodes(): void {
        rafScheduled = false;
        if (!translationsReady) { pendingNodes.clear(); return; }

        // Filter out children whose ancestors are already in the set
        const topLevel: Node[] = [];
        for (const node of pendingNodes) {
            if (!node.isConnected) continue;
            let dominated = false;
            let parent = node.parentNode;
            while (parent) {
                if (pendingNodes.has(parent)) { dominated = true; break; }
                parent = parent.parentNode;
            }
            if (!dominated) topLevel.push(node);
        }
        pendingNodes.clear();

        for (const node of topLevel) {
            translateSubtree(node);
        }
    }

    observerInstance = new MutationObserver((mutations) => {
        if (!translationsReady) return;
        for (const mutation of mutations) {
            for (let i = 0; i < mutation.addedNodes.length; i++) {
                const node = mutation.addedNodes[i];
                if (node.nodeType === Node.ELEMENT_NODE) {
                    translateAttributes(node as Element);
                    const el = node as Element;
                    if (!SKIPPED_TAGS.has(el.tagName)) {
                        pendingNodes.add(node);
                    }
                }
            }
        }
        if (pendingNodes.size > 0 && !rafScheduled) {
            rafScheduled = true;
            requestAnimationFrame(processPendingNodes);
        }
    });

    observerInstance.observe(document.body, { childList: true, subtree: true });

    // One-time sweep after React finishes initial hydration
    setTimeout(() => {
        if (translationsReady) translateSubtree(document.body);
    }, 200);
}

export default initAutoTranslator;
