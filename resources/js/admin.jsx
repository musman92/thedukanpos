import { bootInertia } from './boot';

bootInertia({
    ...import.meta.glob('./Pages/Admin/**/*.jsx'),
    // Receipt is shared with POS; admin order print uses the same page.
    ...import.meta.glob('./Pages/Pos/Receipt.jsx'),
});
