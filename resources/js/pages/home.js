import { html, setHtml } from '../lib/dom.js';
import { renderIcons } from '../lib/icons.js';

/**
 * Home page: pause/play for the background video (accessibility). Visitors who prefer reduced
 * motion get the poster image instead of the moving video.
 */
export default function home() {
    const video = document.getElementById('hero-video');
    const toggle = document.getElementById('video-toggle');
    if (!video) return;

    const render = () => {
        const paused = video.paused;
        setHtml(toggle, html`<i data-lucide="${paused ? 'play' : 'pause'}" class="h-4 w-4"></i><span>${paused ? 'Play video' : 'Pause video'}</span>`);
        toggle.setAttribute('aria-label', paused ? 'Play background video' : 'Pause background video');
        renderIcons(toggle);
    };

    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        video.pause();
        video.removeAttribute('autoplay');
    }

    toggle?.addEventListener('click', () => {
        if (video.paused) video.play().catch(() => {});
        else video.pause();
    });
    video.addEventListener('play', () => toggle && render());
    video.addEventListener('pause', () => toggle && render());
    if (toggle) render();
}
