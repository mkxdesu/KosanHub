<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Invoices | KosanHub</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <main class="page-shell" data-invoice-dashboard>
        <header class="topbar">
            <a class="brand" href="/" aria-label="KosanHub home">
                <span class="brand-mark" aria-hidden="true">K</span>
                <span>Kosan<span class="brand-accent">Hub</span></span>
            </a>
            <span class="topbar-label">PROPERTY MANAGEMENT</span>
        </header>

        <section class="hero">
            <div>
                <p class="eyebrow">INVOICE CENTER</p>
                <h1>Billing, made simple.</h1>
                <p class="hero-copy">Create monthly invoices and keep track of payments in one place.</p>
            </div>
            <form class="generate-card" data-generate-form>
                <label for="invoice-period">Generate monthly invoices</label>
                <div class="generate-controls">
                    <input id="invoice-period" name="period" type="month" value="{{ now()->format('Y-m') }}" required>
                    <button type="submit" data-generate-button>
                        <span aria-hidden="true">＋</span> Queue invoices
                    </button>
                </div>
            </form>
        </section>

        <p class="notice" data-notice role="status" aria-live="polite" hidden></p>

        <section class="stats" aria-label="Invoice summary">
            <article class="stat-card">
                <span class="stat-label">TOTAL INVOICES</span>
                <strong data-stat-total>—</strong>
                <span class="stat-detail">All periods</span>
            </article>
            <article class="stat-card">
                <span class="stat-label">PAID</span>
                <strong data-stat-paid>—</strong>
                <span class="stat-detail">Successfully settled</span>
            </article>
            <article class="stat-card">
                <span class="stat-label">UNPAID / OVERDUE</span>
                <strong data-stat-open>—</strong>
                <span class="stat-detail">Awaiting payment</span>
            </article>
        </section>

        <section class="invoice-section">
            <div class="section-heading">
                <div>
                    <h2>Recent invoices</h2>
                    <p>Invoices across all properties and billing periods.</p>
                </div>
                <button class="refresh-button" type="button" data-refresh-button>Refresh list</button>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Tenant</th>
                            <th>Property / room</th>
                            <th>Period</th>
                            <th>Due date</th>
                            <th>Amount</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody data-invoice-rows>
                        <tr><td class="table-message" colspan="6">Loading invoices…</td></tr>
                    </tbody>
                </table>
            </div>
        </section>
        <footer>Simple, thoughtful management for your boarding house.</footer>
    </main>
</body>
</html>
