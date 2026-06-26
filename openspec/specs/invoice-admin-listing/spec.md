# invoice-admin-listing Specification

## Purpose
TBD - created by archiving change admin-operations-console. Update Purpose after archive.
## Requirements
### Requirement: Invoice list (order-derived)
The system SHALL provide an admin invoice list (gated `orders.view`) derived from orders, each row showing the invoice number (order number), customer, total, payment status, and order date, with keyword search (order number / customer name / mobile), a payment-status filter, a created-date range with presets, and sorting by date and total.

#### Scenario: Filter invoices by paid status
- **WHEN** an authorized user filters the invoice list by payment status = paid
- **THEN** only orders with payment_status `paid` are listed as invoices

#### Scenario: Filter invoices by date range
- **WHEN** an authorized user selects a custom date range
- **THEN** only invoices for orders created within that range are listed

### Requirement: Invoice PDF download from the list
The system SHALL let an authorized user download the PDF invoice for any row directly from the invoice list, reusing the existing per-order invoice route.

#### Scenario: Download a row's invoice
- **WHEN** an authorized user triggers the download action on an invoice row
- **THEN** the system returns that order's invoice PDF

