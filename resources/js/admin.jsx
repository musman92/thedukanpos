import { bootInertia } from './boot';

const addonPages = Object.fromEntries(
    Object.entries(import.meta.glob('../../addons/*/resources/js/Pages/**/*.jsx')).map(
        ([path, loader]) => {
            const match = path.match(/^\.\.\/\.\.\/addons\/([^/]+)\/resources\/js\/Pages\/(.+)\.jsx$/);
            const addon = match?.[1]
                ?.split('-')
                .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
                .join('');

            return [`./Pages/Addons/${addon}/${match?.[2]}.jsx`, loader];
        },
    ),
);

bootInertia({
    ...import.meta.glob('./Pages/Admin/**/*.jsx'),
    ...addonPages,
    // Receipt is shared with POS; admin order print uses the same page.
    ...import.meta.glob('./Pages/Pos/Receipt.jsx'),
});
