/**
 * Scroll-reveal: elements with .reveal fade up when they enter the viewport.
 * Call revealAll() again after rendering new .reveal elements with JS.
 */
let observer = null;

export function revealAll(root = document) {
    const elements = root.querySelectorAll('.reveal:not(.is-visible)');
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    if (!('IntersectionObserver' in window) || reduceMotion) {
        elements.forEach((el) => el.classList.add('is-visible'));
        return;
    }

    observer ??= new IntersectionObserver(
        (entries) => {
            for (const entry of entries) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    observer.unobserve(entry.target);
                }
            }
        },
        { threshold: 0.12, rootMargin: '0px 0px -40px 0px' },
    );
    elements.forEach((el) => observer.observe(el));
}
