import { defineBehavior } from '../core/behavior';
import { attribute, query, toggleClass } from '../core/dom';
import { post } from '../core/http';
import type { FavoriteToggleResponse } from '../core/types';

function isFavoriteResponse(value: unknown): value is FavoriteToggleResponse {
    if (typeof value !== 'object' || value === null) {
        return false;
    }

    const candidate = value as Record<string, unknown>;

    return typeof candidate['favorited'] === 'boolean'
        && typeof candidate['count'] === 'number';
}

export const favoriteToggle = defineBehavior<HTMLElement>({
    name: 'favorite-toggle',
    selector: '[data-favorite-toggle]',

    mount(element) {
        const endpoint = attribute(element, 'data-favorite-toggle');
        const redirect = attribute(element, 'data-favorite-redirect');
        const counter = query<HTMLElement>(
            '[data-favorite-count]',
            HTMLElement,
            element,
        );

        if (endpoint === null) {
            return;
        }

        let busy = false;

        const activate = () => {
            if (busy) {
                return;
            }

            if (redirect !== null) {
                window.location.assign(redirect);
                return;
            }

            busy = true;
            element.setAttribute('aria-disabled', 'true');

            void post<unknown>(endpoint).then((result) => {
                busy = false;
                element.removeAttribute('aria-disabled');

                if (!result.ok || !isFavoriteResponse(result.value)) {
                    return;
                }

                toggleClass(
                    element,
                    'is-active',
                    result.value.favorited,
                );

                element.setAttribute(
                    'aria-pressed',
                    result.value.favorited ? 'true' : 'false',
                );

                if (counter !== null) {
                    counter.textContent = String(result.value.count);
                }

                const headerCounter = document.querySelector<HTMLElement>(
                    '[data-favorite-header-count]',
                );

                if (headerCounter !== null) {
                    headerCounter.dataset['favoriteHeaderCount'] =
                        String(result.value.count);

                    headerCounter.textContent =
                        String(result.value.count);

                    headerCounter.classList.toggle(
                        'is-hidden',
                        result.value.count <= 0,
                    );
                }
            });
        };

        element.addEventListener('click', activate);

        element.addEventListener('keydown', (event) => {
            if (event.key !== 'Enter' && event.key !== ' ') {
                return;
            }

            event.preventDefault();
            activate();
        });
    },
});
