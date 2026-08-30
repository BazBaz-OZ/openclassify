import '../css/app.css';

import { startBehaviors, type Behavior } from './core/behavior';
import { characterCounter, confirmAction, dependentSelect, imagePreview, ratingInput, revealPanel } from './modules/forms';
import { favoriteToggle } from './modules/favorite-toggle';
import { filterDrawer, listingFilters, viewModeToggle } from './modules/listing-filters';
import { inboxBadge, inboxPane, inboxThread } from './modules/inbox';
import { listingGallery } from './modules/gallery';
import { locationPicker } from './modules/location-picker';
import { contactReveal, shareAction } from './modules/reveal';
import { disclosureGroup, navigationDrawer, searchSuggest, stickyHeader } from './modules/navigation';

const behaviors: readonly Behavior<never>[] = [
    navigationDrawer,
    disclosureGroup,
    stickyHeader,
    searchSuggest,
    locationPicker,
    listingFilters,
    filterDrawer,
    viewModeToggle,
    listingGallery,
    favoriteToggle,
    contactReveal,
    shareAction,
    inboxThread,
    inboxPane,
    inboxBadge,
    characterCounter,
    dependentSelect,
    imagePreview,
    confirmAction,
    ratingInput,
    revealPanel,
];

function prepareListingImages(): void {
    document.querySelectorAll<HTMLImageElement>('[data-listing-image]').forEach((image) => {
        if (image.dataset['imageBound'] === '1') {
            return;
        }

        image.dataset['imageBound'] = '1';

        const reveal = (): void => {
            image.classList.add('is-loaded');

            const placeholder = image.previousElementSibling;

            if (placeholder?.classList.contains('listing-card__image-loading')) {
                placeholder.classList.add('is-hidden');
            }
        };

        if (image.complete && image.naturalWidth > 0) {
            reveal();
            return;
        }

        image.addEventListener('load', reveal, { once: true });
    });
}

function boot(): void {
    prepareListingImages();
    startBehaviors(behaviors);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot, { once: true });
} else {
    boot();
}

document.addEventListener('livewire:navigated', boot);
