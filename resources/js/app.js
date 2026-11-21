/**
 * Entry point for every page.
 *
 * Shared behaviour (header, menu, reveal, accordions, carousels, counters, lightbox, newsletter)
 * runs on every page. Page-specific code lives in resources/js/pages/<name>.js and is loaded only
 * on the page whose <body data-page="<name>">; Vite gives each page its own chunk. Only files that
 * exist in pages/ can be loaded, so data-page can't load arbitrary code.
 *
 * <html class="js"> is rendered by the server (layouts/app.blade.php), so no inline script is needed.
 */
import { renderIcons } from './lib/icons.js';
import { initHeader } from './ui/header.js';
import { initMobileMenu } from './ui/mobile-menu.js';
import { revealAll } from './ui/reveal.js';
import { initAccordions } from './ui/accordion.js';
import { initCarousels } from './ui/carousel.js';
import { initCounters } from './ui/counters.js';
import { initLightboxes } from './ui/lightbox.js';
import { initNewsletter } from './ui/newsletter.js';
import { initTabs } from './ui/tabs.js';
import { initQuickBook } from './ui/quick-book.js';
import { initSoon } from './ui/soon.js';
import { initOwnerNav } from './ui/owner-nav.js';
import { initPasswordToggles } from './ui/password-toggle.js';
import { initDemoForms } from './ui/demo-forms.js';
import { initAdmin } from './ui/admin.js';
import { initAccountMenu } from './ui/account-menu.js';

renderIcons();
initHeader();
initMobileMenu();
initAccordions();
initCarousels();
initCounters();
initLightboxes();
initNewsletter();
initTabs();
initQuickBook();
initSoon();
initOwnerNav();
initPasswordToggles();
initDemoForms();
initAdmin();
initAccountMenu();
revealAll();

const pages = import.meta.glob('./pages/*.js');
const page = document.body.dataset.page;
const loader = page ? pages[`./pages/${page}.js`] : undefined;

if (loader) {
    loader()
        .then((module) => module.default?.())
        .catch((error) => console.error(`Page script "${page}" failed`, error));
}
