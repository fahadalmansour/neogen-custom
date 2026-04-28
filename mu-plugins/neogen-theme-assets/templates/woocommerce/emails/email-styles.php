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
/* NeoGen email — Sky + Cool White (R6). Brand bar at top of
   template_header keeps the locked dark logo environment; the
   rest of the email is now light. */

#wrapper, body { background-color: #F8FAFC !important; }

#template_container, #template_body, #body_content {
  background-color: #FFFFFF !important;
  border-color: rgba(56, 189, 248, 0.16) !important;
  border-radius: 14px;
}

#body_content_inner { color: #334155 !important; }

#body_content table, #body_content td {
  background-color: #FFFFFF !important;
  color: #334155 !important;
  border-color: rgba(15, 23, 42, 0.08) !important;
}

#body_content h1, #body_content h2, #body_content h3 {
  color: #0F172A !important;
  font-family: 'Chakra Petch', 'IBM Plex Sans', sans-serif;
  font-weight: 600;
  letter-spacing: 0.02em;
  text-transform: uppercase;
  margin: 0 0 12px 0;
}

#body_content h2 { font-size: 18px; }
#body_content h3 { font-size: 14px; color: #38BDF8; letter-spacing: 0.12em; }

#body_content p { margin: 0 0 12px 0; line-height: 1.65; font-family: 'Tajawal','Arial',sans-serif; font-size: 14px; }

/* Order-details table */
#body_content table.td {
  border-collapse: collapse !important;
  width: 100% !important;
  border: 1px solid rgba(56, 189, 248, 0.16) !important;
  font-family: 'IBM Plex Mono', 'Courier New', monospace !important;
  font-size: 12px !important;
}

#body_content table.td th {
  text-align: left;
  padding: 10px 14px !important;
  font-size: 10px !important;
  letter-spacing: 0.18em !important;
  text-transform: uppercase !important;
  color: #64748B !important;
  background-color: #F1F5F9 !important;
  border-bottom: 1px solid rgba(56, 189, 248, 0.16) !important;
}

#body_content table.td td {
  padding: 12px 14px !important;
  vertical-align: top;
  color: #0F172A !important;
  border-bottom: 1px solid rgba(15, 23, 42, 0.08) !important;
  font-family: 'IBM Plex Mono', 'Courier New', monospace !important;
  font-size: 13px !important;
}

#body_content table.td tfoot th {
  background-color: #F8FAFC !important;
  color: #64748B !important;
}

#body_content table.td tfoot td {
  background-color: #F8FAFC !important;
  text-align: right;
  font-variant-numeric: tabular-nums;
}

#body_content table.td tr.order-total td,
#body_content table.td tr.order-total th {
  font-size: 14px !important;
  color: #38BDF8 !important;
  font-weight: 700 !important;
}

/* Address columns */
#addresses {
  background-color: #F1F5F9 !important;
  border: 1px solid rgba(56, 189, 248, 0.16) !important;
  border-radius: 8px;
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
  color: #38BDF8 !important;
  font-family: 'IBM Plex Mono', 'Courier New', monospace !important;
  font-size: 10px !important;
  letter-spacing: 0.22em !important;
  text-transform: uppercase !important;
  margin-bottom: 8px !important;
}

#addresses address {
  font-style: normal;
  color: #334155 !important;
  font-family: 'Tajawal', 'Arial', sans-serif !important;
  font-size: 13px !important;
  line-height: 1.55;
}

/* Buttons / links inside email body */
#body_content a {
  color: #38BDF8 !important;
  text-decoration: none;
  border-bottom: 1px solid rgba(56, 189, 248, 0.34);
}

/* Woo notices (rare in customer emails but present in admin) */
.woocommerce-notices-wrapper { display: none !important; }
