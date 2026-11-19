import './bootstrap';

const dashboard = document.querySelector('[data-invoice-dashboard]');

if (dashboard) {
    const form = dashboard.querySelector('[data-generate-form]');
    const submitButton = dashboard.querySelector('[data-generate-button]');
    const notice = dashboard.querySelector('[data-notice]');
    const invoiceRows = dashboard.querySelector('[data-invoice-rows]');

    const showNotice = (message, isError = false) => {
        notice.textContent = message;
        notice.classList.toggle('error', isError);
        notice.hidden = false;
    };

    const formatCurrency = (value) => new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(Number(value) || 0);

    const appendCell = (row, value) => {
        const cell = document.createElement('td');
        cell.textContent = value || '—';
        row.append(cell);
        return cell;
    };

    const loadInvoices = async () => {
        invoiceRows.innerHTML = '';
        const loadingRow = document.createElement('tr');
        const loadingCell = appendCell(loadingRow, 'Loading invoices…');
        loadingCell.colSpan = 6;
        loadingCell.className = 'table-message';
        invoiceRows.append(loadingRow);

        try {
            const response = await fetch('/api/invoices', {
                headers: { Accept: 'application/json' },
            });

            if (!response.ok) {
                throw new Error('Unable to load invoices. Please try again.');
            }

            const invoices = await response.json();
            const paidCount = invoices.filter((invoice) => invoice.status === 'paid').length;
            const openCount = invoices.filter((invoice) => ['unpaid', 'overdue'].includes(invoice.status)).length;

            dashboard.querySelector('[data-stat-total]').textContent = invoices.length;
            dashboard.querySelector('[data-stat-paid]').textContent = paidCount;
            dashboard.querySelector('[data-stat-open]').textContent = openCount;
            invoiceRows.innerHTML = '';

            if (invoices.length === 0) {
                const row = document.createElement('tr');
                const cell = appendCell(row, 'No invoices yet. Queue a monthly generation to get started.');
                cell.colSpan = 6;
                cell.className = 'table-message';
                invoiceRows.append(row);
                return;
            }

            invoices.forEach((invoice) => {
                const row = document.createElement('tr');
                const contract = invoice.contract || {};
                const room = contract.room || {};
                const property = room.property || {};
                appendCell(row, contract.tenant?.name);
                appendCell(row, [property.name, room.room_number].filter(Boolean).join(' · '));
                appendCell(row, invoice.period);
                appendCell(row, invoice.due_date);
                appendCell(row, formatCurrency(invoice.total));
                const statusCell = appendCell(row, '');
                const badge = document.createElement('span');
                badge.className = `status-pill ${invoice.status || ''}`;
                badge.textContent = invoice.status || 'Unknown';
                statusCell.append(badge);
                invoiceRows.append(row);
            });
        } catch (error) {
            invoiceRows.innerHTML = '';
            const row = document.createElement('tr');
            const cell = appendCell(row, error.message);
            cell.colSpan = 6;
            cell.className = 'table-message';
            invoiceRows.append(row);
        }
    };

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        submitButton.disabled = true;
        notice.hidden = true;

        try {
            const response = await fetch('/api/invoices/generate-monthly', {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ period: new FormData(form).get('period') }),
            });
            const result = await response.json();

            if (!response.ok) {
                throw new Error(result.message || 'Unable to queue invoice generation.');
            }

            showNotice(`Invoice generation for ${result.period} was added to the Redis queue. Refresh the list after the worker finishes.`);
        } catch (error) {
            showNotice(error.message, true);
        } finally {
            submitButton.disabled = false;
        }
    });

    dashboard.querySelector('[data-refresh-button]').addEventListener('click', loadInvoices);
    loadInvoices();
}
