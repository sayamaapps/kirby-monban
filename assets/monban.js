/*
 * Monban: loads the ALTCHA widget, registers its workers and, when the page
 * asks for them, the German labels. Kirby serves every plugin asset under a
 * folder of its own, so the script gets the URLs as data attributes of its
 * own script tag instead of relative imports. Workers are files, not blob:
 * URLs, so a Content Security Policy with worker-src 'self' is enough.
 * Nothing here talks to a foreign host.
 */
const tag = document.querySelector('script[data-monban-altcha]');

if (tag) {
	const urls = tag.dataset;
	import(urls.monbanAltcha).then(() => {
		const registry = globalThis.$altcha;
		if (registry && registry.algorithms) {
			const worker = (url) => () => new Worker(url);
			for (const name of ['PBKDF2/SHA-256', 'PBKDF2/SHA-384', 'PBKDF2/SHA-512']) {
				registry.algorithms.set(name, worker(urls.monbanPbkdf2));
			}
			for (const name of ['SHA-256', 'SHA-384', 'SHA-512']) {
				registry.algorithms.set(name, worker(urls.monbanSha));
			}
		}
		if (urls.monbanI18n) {
			return import(urls.monbanI18n);
		}
		return undefined;
	});
}
