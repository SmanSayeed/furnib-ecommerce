<style>
    * { font-family: DejaVu Sans, sans-serif; }
    body { color: #1b1b18; font-size: 12px; margin: 0; }
    .inv { padding: 24px 28px; page-break-inside: avoid; }
    .inv-title { text-align: center; font-size: 18px; letter-spacing: 1px; margin-bottom: 10px; }
    .head { width: 100%; }
    .head-left { vertical-align: top; }
    .head-right { vertical-align: top; text-align: right; }
    .ono { font-size: 15px; font-weight: bold; }
    .web { font-weight: bold; font-size: 12px; margin: 2px 0 6px; }
    .logo { max-height: 44px; max-width: 200px; margin-bottom: 4px; }
    .brand { font-size: 20px; font-weight: bold; color: #ea580c; }
    .muted { color: #6b7280; font-size: 11px; }
    .barcode { margin-top: 4px; }
    .info { width: 100%; margin-top: 14px; }
    .info-col { vertical-align: top; width: 50%; line-height: 1.55; font-size: 11px; }
    .info-h { font-weight: bold; font-size: 13px; margin-bottom: 3px; }
    .items { width: 100%; border-collapse: collapse; margin-top: 16px; }
    .items th, .items td { border: 1px solid #d1d5db; padding: 6px 8px; text-align: left; font-size: 11px; }
    .items th { background: #f3f4f6; font-weight: bold; }
    .right { text-align: right; }
    .c-sl { width: 26px; } .c-qty { width: 58px; } .c-sku { width: 84px; } .c-attr { width: 90px; }
    .foot { width: 100%; margin-top: 14px; }
    .foot-note { vertical-align: top; font-size: 11px; width: 58%; }
    .foot-totals { vertical-align: top; width: 42%; }
    .totals { width: 100%; border-collapse: collapse; }
    .totals td { border: 1px solid #d1d5db; padding: 5px 8px; font-size: 11px; }
    .totals td:first-child { text-align: right; font-weight: bold; width: 58%; }
    .totals .payable td { font-weight: bold; font-size: 13px; }
    .sep { border: 0; border-top: 2px dashed #9ca3af; margin: 10px 40px; }
    /* Bulk: force a new A4 page after every second invoice (2 per page). */
    .page-break { page-break-after: always; height: 0; }

    /* --- Two-up bulk invoices: compress each invoice to fit half an A4 page so
       exactly two print per sheet. Fonts/paddings are tightened; the layout is
       identical to the single invoice. --- */
    .two-up .half { page-break-inside: avoid; }
    .two-up .inv { padding: 12px 24px; }
    .two-up .inv-title { font-size: 14px; margin-bottom: 4px; }
    .two-up .ono { font-size: 13px; }
    .two-up .web { margin: 1px 0 3px; }
    .two-up .logo { max-height: 34px; }
    .two-up .brand { font-size: 16px; }
    .two-up .info { margin-top: 8px; }
    .two-up .info-col { line-height: 1.35; font-size: 10px; }
    .two-up .info-h { font-size: 11px; margin-bottom: 1px; }
    .two-up .items { margin-top: 8px; }
    .two-up .items th, .two-up .items td { padding: 3px 6px; font-size: 10px; }
    .two-up .foot { margin-top: 8px; }
    .two-up .foot-note { font-size: 10px; }
    .two-up .totals td { padding: 3px 6px; font-size: 10px; }
    .two-up .totals .payable td { font-size: 11px; }
</style>
