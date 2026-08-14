import { useLayoutEffect, useRef } from 'react';

const SERIAL_LABELS = /^(sn|s\/n|#|no\.?)$/i;
const ACTION_LABELS = /^(action|actions)$/i;
const PRIMARY_LABELS =
    /^(name|product|customer|supplier|employee|user|branch|account|source|order|invoice|reference|description|title)$/i;

function cleanLabel(value) {
    return String(value || '')
        .replace(/[↑↓↕]/g, '')
        .replace(/\s+/g, ' ')
        .trim();
}

function columnLabels(table) {
    const rows = Array.from(table.tHead?.rows || []);
    const headingRow = rows.at(-1);
    if (!headingRow) return [];

    return Array.from(headingRow.cells).flatMap((cell) => {
        const label = cleanLabel(cell.textContent);
        return Array.from({ length: cell.colSpan || 1 }, () => label);
    });
}

function prepareTable(table) {
    const mode = table.getAttribute('data-mobile-table');
    if (mode && mode !== 'cards') return;

    const labels = columnLabels(table);
    if (labels.length === 0) {
        table.dataset.mobileTable = 'scroll';
        return;
    }

    table.dataset.mobileTable = 'cards';

    Array.from(table.tBodies).forEach((body) => {
        Array.from(body.rows).forEach((row) => {
            let column = 0;
            let primaryAssigned = false;

            Array.from(row.cells).forEach((cell) => {
                const label = labels[column] || '';
                cell.dataset.mobileLabel = label;

                if (SERIAL_LABELS.test(label)) {
                    cell.dataset.mobileSerial = 'true';
                } else if (ACTION_LABELS.test(label)) {
                    cell.dataset.mobileActions = 'true';
                } else if (!primaryAssigned && PRIMARY_LABELS.test(label)) {
                    cell.dataset.mobilePrimary = 'true';
                    primaryAssigned = true;
                }

                if (cell.colSpan > 1) {
                    cell.dataset.mobileFull = 'true';
                }

                column += cell.colSpan || 1;
            });
        });
    });
}

function prepareTables(root) {
    root.querySelectorAll('table').forEach(prepareTable);
}

/**
 * Adds mobile field labels to regular semantic tables without duplicating row
 * markup in every page. Tables can opt out with data-mobile-table="scroll" or
 * data-mobile-table="manual".
 */
export default function ResponsiveTableScope({ children }) {
    const scopeRef = useRef(null);

    useLayoutEffect(() => {
        const root = scopeRef.current;
        if (!root) return undefined;

        prepareTables(root);

        const observer = new MutationObserver(() => prepareTables(root));
        observer.observe(root, { childList: true, subtree: true });

        return () => observer.disconnect();
    }, []);

    return (
        <div ref={scopeRef} className="dp-responsive-table-scope contents">
            {children}
        </div>
    );
}
