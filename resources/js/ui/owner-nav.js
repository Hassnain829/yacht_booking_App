/** Owner area pill nav scrolls sideways on phones: bring the current section into view. */
export function initOwnerNav() {
    const scroller = document.querySelector('[data-owner-nav]');
    const current = scroller?.querySelector('[aria-current="page"]');
    if (!scroller || !current || scroller.scrollWidth <= scroller.clientWidth) return;
    scroller.scrollLeft = Math.max(0, current.offsetLeft - (scroller.clientWidth - current.offsetWidth) / 2);
}
