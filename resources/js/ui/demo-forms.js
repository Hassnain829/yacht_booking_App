import { toast } from '../lib/toast.js';

/**
 * PHASE 1 ONLY: forms marked <form data-demo-form="message"> are checked with the browser's built-in
 * validation, then show a toast instead of posting (their endpoints arrive in Phase 2+: Fortify, Stripe…).
 * `data-match="otherFieldId"` on an input checks that both values are equal (password confirmation).
 */
export function initDemoForms() {
    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-demo-form')) return;
        event.preventDefault();
        for (const input of form.querySelectorAll('[data-match]')) {
            const other = document.getElementById(input.dataset.match);
            input.setCustomValidity(other && other.value !== input.value ? 'The passwords do not match.' : '');
        }
        if (!form.reportValidity()) return;
        toast(form.dataset.demoForm || 'Demo: this form goes live in the next phase.');
    });
    document.addEventListener('input', (event) => {
        if (event.target instanceof HTMLInputElement && event.target.dataset.match) event.target.setCustomValidity('');
    });
}
