/*
 * Monban: loads the ALTCHA widget from this folder, registers its workers
 * (files of this plugin, no blob: URLs, so a Content Security Policy with
 * worker-src 'self' is enough) and the German labels when the page asks for
 * them (data-monban-language on the script tag). Nothing here talks to a
 * foreign host.
 */
import './altcha.js';

const base = new URL('./', import.meta.url);
const worker = (file) => () => new Worker(new URL('workers/' + file, base));
const registry = globalThis.$altcha;

if (registry && registry.algorithms) {
	for (const name of ['PBKDF2/SHA-256', 'PBKDF2/SHA-384', 'PBKDF2/SHA-512']) {
		registry.algorithms.set(name, worker('pbkdf2.js'));
	}
	for (const name of ['SHA-256', 'SHA-384', 'SHA-512']) {
		registry.algorithms.set(name, worker('sha.js'));
	}
}

const tag = document.querySelector('script[data-monban-language]');
const language = tag ? tag.dataset.monbanLanguage : '';
if (language === 'de') {
	import('./i18n/de.js');
}
