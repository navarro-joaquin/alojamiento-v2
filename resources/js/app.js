/**
 * A select with `data-auto-submit` submits its form when it changes.
 */
document.querySelectorAll('[data-auto-submit]').forEach((field) => {
    field.addEventListener('change', () => field.form.requestSubmit());
});

/**
 * Counter sale catalog: filters the products by name or code and shows each
 * line amount and the total as quantities are typed. Amounts are added up in
 * centavos; the server recalculates them when the sale is registered.
 */
document.querySelectorAll('[data-counter-sale]').forEach((form) => {
    const rows = [...form.querySelectorAll('[data-counter-sale-row]')];
    const searchField = form.querySelector('[data-counter-sale-search]');
    const totalElement = form.querySelector('[data-counter-sale-total]');
    const noResults = form.querySelector('[data-counter-sale-no-results]');

    const formatCentavos = (centavos) => (centavos / 100).toFixed(2);

    const updateTotals = () => {
        const totalCentavos = rows.reduce((total, row) => {
            const quantity = Number.parseFloat(row.querySelector('[data-counter-sale-quantity]').value);
            const lineCentavos = quantity > 0 ? Math.round(Number(row.dataset.priceCents) * quantity) : 0;

            row.querySelector('[data-counter-sale-line-amount]').textContent = lineCentavos > 0 ? `Bs ${formatCentavos(lineCentavos)}` : '';
            row.classList.toggle('bg-denom-50', lineCentavos > 0);

            return total + lineCentavos;
        }, 0);

        totalElement.textContent = formatCentavos(totalCentavos);
    };

    const filterRows = () => {
        const searchText = searchField.value.trim().toLowerCase();
        let visibleRows = 0;

        rows.forEach((row) => {
            const quantityField = row.querySelector('[data-counter-sale-quantity]');
            const matches = searchText === '' || row.dataset.searchText.includes(searchText) || quantityField.value !== '';

            row.hidden = ! matches;
            visibleRows += matches ? 1 : 0;
        });

        noResults.classList.toggle('hidden', visibleRows > 0);
    };

    form.addEventListener('input', (event) => {
        if (event.target === searchField) {
            filterRows();
        } else if (event.target.matches('[data-counter-sale-quantity]')) {
            updateTotals();
        }
    });

    searchField.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
        }
    });

    updateTotals();
});
