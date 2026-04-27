<?php
/**
 * NeoGen email styles.
 *
 * Routed via wc_get_template filter map. Woo includes this file
 * inside <style> tags at the top of every email it sends. If
 * Premmerger / emogrifier / a similar inliner is wired in, these
 * styles get folded into element-level style="" attributes for
 * Outlook / Apple-Mail compatibility.
 *
 * Style overrides target Woo's default email markup classes so
 * the order-details table, totals, address blocks, and notices
 * all render in the operator-console aesthetic.
 *
 * @var WC_Email $email
 * @version 10.7.0 (NeoGen reconciled against upstream WC 10.7.0)
 */

defined('ABSPATH') || exit;
?>
/* NeoGen email — operator-console aesthetic. */

#wrapper, body { background-color: #050505 !important; }

#template_container, #template_body, #body_content, #template_header {
  background-color: #0b0a08 !important;
  border-color: rgba(0, 209, 255, 0.16) !important;
  border-radius: 6px;
}

#body_content_inner { color: #cfc9bb !important; }

#body_content table, #body_content td {
  background-color: #0b0a08 !important;
  color: #cfc9bb !important;
  border-color: rgba(229, 227, 221, 0.08) !important;
}

#body_content h1, #body_content h2, #body_content h3 {
  color: #e5e3dd !important;
  font-family: 'Chakra Petch', 'IBM Plex Sans', sans-serif;
  font-weight: 500;
  letter-spacing: 0.02em;
  text-transform: uppercase;
  margin: 0 0 12px 0;
}

#body_content h2 { font-size: 18px; }
#body_content h3 { font-size: 14px; color: #00d1ff; letter-spacing: 0.12em; }

#body_content p { margin: 0 0 12px 0; line-height: 1.65; font-family: 'Tajawal','Arial',sans-serif; font-size: 14px; }

/* Order-details table */
#body_content table.td {
  border-collapse: collapse !important;
  width: 100% !important;
  border: 1px solid rgba(0, 209, 255, 0.16) !important;
  font-family: 'IBM Plex Mono', 'Courier New', monospace !important;
  font-size: 12px !important;
}

#body_content table.td th {
  text-align: left;
  padding: 10px 14px !important;
  font-size: 10px !important;
  letter-spacing: 0.18em !important;
  text-transform: uppercase !important;
  color: #8f8a7e !important;
  background-color: #050505 !important;
  border-bottom: 1px solid rgba(0, 209, 255, 0.16) !important;
}

#body_content table.td td {
  padding: 12px 14px !important;
  vertical-align: top;
  color: #e5e3dd !important;
  border-bottom: 1px solid rgba(229, 227, 221, 0.08) !important;
  font-family: 'IBM Plex Mono', 'Courier New', monospace !important;
  font-size: 13px !important;
}

#body_content table.td tfoot th {
  background-color: #050505 !important;
  color: #8f8a7e !important;
}

#body_content table.td tfoot td {
  background-color: #050505 !important;
  text-align: right;
  font-variant-numeric: tabular-nums;
}

#body_content table.td tr.order-total td,
#body_content table.td tr.order-total th {
  font-size: 14px !important;
  color: #00d1ff !important;
  font-weight: 700 !important;
}

/* Address columns */
#addresses {
  background-color: #050505 !important;
  border: 1px solid rgba(0, 209, 255, 0.16) !important;
  border-radius: 4px;
  padding: 18px !important;
  margin: 20px 0 !important;
  width: 100% !important;
}

#addresses td {
  background: transparent !important;
  border: 0 !important;
  padding: 0 12px !important;
  vertical-align: top;
}

#addresses h2 {
  color: #00d1ff !important;
  font-family: 'IBM Plex Mono', 'Courier New', monospace !important;
  font-size: 10px !important;
  letter-spacing: 0.22em !important;
  text-transform: uppercase !important;
  margin-bottom: 8px !important;
}

#addresses address {
  font-style: normal;
  color: #cfc9bb !important;
  font-family: 'Tajawal', 'Arial', sans-serif !important;
  font-size: 13px !important;
  line-height: 1.55;
}

/* Buttons / links inside email body */
#body_content a {
  color: #00d1ff !important;
  text-decoration: none;
  border-bottom: 1px solid rgba(0, 209, 255, 0.34);
}

/* Woo notices (rare in customer emails but present in admin) */
.woocommerce-notices-wrapper { display: none !important; }
