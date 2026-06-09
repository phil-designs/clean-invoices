# Clean Invoices

Tags: invoices, billing, clients, time-tracking, pdf
Requires at least: 5.9
Tested up to: 6.9.1
License: GPL-2.0-or-later

## Description

Create, send, and track client invoices directly from your WordPress dashboard. Clean Invoices handles the full billing workflow — build invoices with line items, generate PDFs, email clients, collect partial or deposit payments, and track billable hours — without relying on any third-party invoice service.

## Tested on 
* Firefox 
* Safari
* Chrome
* Opera
* MS Edge

## Website 
https://phildesigns.com/

## Installation 
1. Upload `clean-invoices` to the `/wp-content/plugins/` directory
2. Activate the plugin through the **Plugins** menu in WordPress
3. Go to **Invoices → Settings** and fill in your company details, logo, and email preferences
4. Go to **Invoices → Clients** and add your clients
5. Go to **Invoices → Add New** to create your first invoice — select a client, add line items, and save to auto-assign an invoice number
6. Use the **Send & Actions** sidebar to preview the PDF, send the invoice, record a payment, or send a receipt
7. To track time, go to **Invoices → Time Tracker** — start the timer, stop it when done, then use **Add to Invoice** on any entry to append it as a line item

## Highlights
* Partial & deposit payments — record deposits, installments, and payments individually; status updates automatically from Sent → Partial → Paid as the balance is paid down
* Time tracker with admin bar quick-timer — start and stop a timer from any page, then push logged hours directly onto an invoice as a line item
* Auto-assigned invoice numbers — prefix + sequential number (e.g. INV-0001) assigned on first save, with the number used as the post title

## Key Features
- Invoice creation and management with configurable prefix and auto-incrementing numbers
- Line items with description, optional sub-detail, quantity, rate, and auto-calculated amounts
- Tax rate (per-invoice or default), optional shipping, subtotal, and total
- Invoice statuses: Draft, Sent, Partial, Paid, Overdue — with colour-coded badges in the list view
- PDF generation, browser preview, and download
- Send invoice to client by email with PDF attached; test email sends only to you
- Customisable invoice and receipt email templates with placeholder tags
- BCC on all outgoing invoice emails
- Payment history table per invoice — view, add, and remove individual payment records
- Send a payment receipt email to the client once an invoice is marked Paid
- Time tracker with start/stop timer, persistent state across page loads, inline editing, and entry merging
- Admin bar quick-timer accessible from any page in the dashboard or front-end
- Client management — name, company, email, phone, and address
- CSV export of all invoices for a selected year
- Settings for company info, logo, Venmo/Zelle handles, invoice numbering, default tax rate, payment terms, thank-you message, and check payment instructions

## Changelog 

Version 1.0.0
• Initial release.
• Invoice creation and management with auto-assigned, prefixed invoice numbers.
• Line items with quantity, rate, sub-detail, and auto-calculated totals including tax and optional shipping.
• PDF generation and download via FPDF.
• Send invoices to clients by email with PDF attached; test email and BCC support.
• Invoice status tracking: Draft, Sent, Partial, Paid, Overdue.
• Partial and deposit payment tracking with automatic status recalculation.
• Payment receipt emails.
• Time tracker with start/stop timer, persistent state, inline editing, merge, and add-to-invoice workflow.
• Admin bar quick-timer accessible from any page.
• Client management post type.
• CSV export by year.
• Configurable settings for company info, invoice numbering, tax, payment terms, and email/receipt templates.
