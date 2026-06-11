# PhilDesigns Clean Invoices

**Contributors:** phildesigns  
**Tags:** invoices, billing, clients, time-tracking, pdf  
**Requires at least:** 6.7  
**Tested up to:** 7.0  
**Stable tag:** 1.0.0  
**Requires PHP:** 7.4  
**License:** GPLv2 or later  
**License URI:** https://www.gnu.org/licenses/gpl-2.0.html

## Description

Create, send, and track client invoices from your WordPress dashboard. Clean Invoices handles the full billing workflow — build invoices with line items, generate PDFs, email clients, collect partial or deposit payments, and track billable hours without relying on any third-party invoice service.

## Tested On
* Firefox
* Safari
* Chrome
* Opera
* MS Edge

## Website
https://www.phildesigns.com/

## Installation
1. Upload `clean-invoices` to the `/wp-content/plugins/` directory.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Go to **Invoices → Settings** and fill in your company details, logo, and email preferences.
4. Go to **Invoices → Clients** and add your clients.
5. Go to **Invoices → Add New** to create your first invoice — select a client, add line items, and save to auto-assign an invoice number.
6. Use the **Send & Actions** sidebar to preview the PDF, send the invoice, record a payment, or send a receipt.
7. To track time, go to **Invoices → Time Tracker**, start the timer, then use **Add to Invoice** to append logged hours as a line item.

## Key Features
- Invoice creation and management with configurable prefix and auto-incrementing numbers
- Line items with description, optional sub-detail, quantity, rate, and auto-calculated amounts
- Tax rate (per-invoice or default), optional shipping, subtotal, and total
- Invoice statuses: Draft, Sent, Partial, Paid, Overdue with colour-coded badges
- PDF generation, browser preview, and download
- Send invoice to client by email with PDF attached; test email sends only to you
- Customisable invoice and receipt email templates with placeholder tokens
- BCC on all outgoing invoice emails
- Payment history per invoice — record deposits, installments, and payments individually
- Send payment receipt email to client once an invoice is marked Paid
- Time tracker with start/stop timer, persistent state, inline editing, and entry merging
- Admin bar quick-timer accessible from any page in the dashboard or front end
- Client management — name, company, email, phone, and address
- CSV export of all invoices for a selected year
- Sales and revenue report with monthly breakdown
- Dashboard widget showing outstanding invoices and year-to-date revenue

## Changelog

### 1.0.0
- Initial release
