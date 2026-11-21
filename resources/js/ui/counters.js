/**
 * Count-up numbers: <span data-count="4800" data-suffix="+" data-decimals="0">4,800+</span>
 * The final number is rendered by the server (correct without JS); JS animates from 0 once visible.
 */
export function initCounters(root = document) {
    const elements = root.querySelectorAll('[data-count]');
    if (!elements.length || !('IntersectionObserver' in window)) return;
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    const run = (el) => {
        const target = Number(el.dataset.count);
        const decimals = Number(el.dataset.decimals || 0);
        const suffix = el.dataset.suffix || '';
        const start = performance.now();
        const duration = 1600;
        const tick = (now) => {
            const progress = Math.min(1, (now - start) / duration);
            const value = target * (1 - Math.pow(1 - progress, 3));
            el.textContent = value.toLocaleString('en-US', { minimumFractionDigits: decimals, maximumFractionDigits: decimals }) + suffix;
            if (progress < 1) requestAnimationFrame(tick);
        };
        requestAnimationFrame(tick);
    };

    const observer = new IntersectionObserver(
        (entries) => {
            for (const entry of entries) {
                if (entry.isIntersecting) {
                    run(entry.target);
                    observer.unobserve(entry.target);
                }
            }
        },
        { threshold: 0.5 },
    );
    elements.forEach((el) => observer.observe(el));
}
