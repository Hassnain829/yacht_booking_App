/**
 * Header: transparent over the hero, solid navy after 40px of scroll (or always, when
 * <header data-solid="true">). Also lets visitors dismiss the promo strip.
 */
export function initHeader() {
    const header = document.getElementById('header');
    if (!header) return;

    const alwaysSolid = header.dataset.solid === 'true';
    let ticking = false;
    const update = () => {
        header.classList.toggle('is-solid', alwaysSolid || window.scrollY > 40);
        ticking = false;
    };
    update();
    window.addEventListener(
        'scroll',
        () => {
            if (!ticking) {
                ticking = true;
                requestAnimationFrame(update);
            }
        },
        { passive: true },
    );

    const promo = document.getElementById('promo-bar');
    document.getElementById('promo-close')?.addEventListener('click', () => {
        const id = promo?.dataset.promoId ?? '';
        promo?.remove();
        // Remembered for 30 days. The server only hides the strip when this matches the current promo id.
        if (/^[a-z0-9-]{1,64}$/.test(id)) {
            const secure = window.location.protocol === 'https:' ? '; secure' : '';
            document.cookie = `promo_hidden=${id}; path=/; max-age=${60 * 60 * 24 * 30}; samesite=lax${secure}`;
        }
    });
}
