document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-confirm]').forEach((button) => {
        button.addEventListener('click', (event) => {
            const message = button.getAttribute('data-confirm') || 'Are you sure?';
            if (!window.confirm(message)) {
                event.preventDefault();
            }
        });
    });

    const searchInput = document.querySelector('[data-table-search]');
    const searchTable = document.querySelector('[data-filter-table]');

    if (searchInput && searchTable) {
        searchInput.addEventListener('input', () => {
            const value = searchInput.value.toLowerCase();
            searchTable.querySelectorAll('tbody tr').forEach((row) => {
                row.style.display = row.textContent.toLowerCase().includes(value) ? '' : 'none';
            });
        });
    }
});
