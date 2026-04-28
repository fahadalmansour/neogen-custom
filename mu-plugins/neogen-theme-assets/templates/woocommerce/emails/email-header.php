<?php
/**
 * NeoGen email header.
 *
 * Routed via wc_get_template filter map. Called by Woo's
 * woocommerce_email_header($heading) at the top of every customer
 * email. Produces the brand bar with the NeoGen lockup + heading.
 *
 * Email-safe: all-table layout, no flexbox, no JS, no web fonts.
 *
 * @var string $email_heading
 * @var string $additional_content
 * @version 10.7.0 (NeoGen reconciled against upstream WC 10.7.0)
 */

defined('ABSPATH') || exit;

$cr  = function_exists('ng_cr') ? ng_cr() : ['brand_en' => 'NeoGen Store', 'brand_ar' => 'نيوجين ستور'];
$dir = is_rtl() ? 'rtl' : 'ltr';
?><!DOCTYPE html>
<html dir="<?php echo esc_attr($dir); ?>" lang="<?php echo esc_attr(get_locale()); ?>">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<meta name="color-scheme" content="only light" />
<meta name="supported-color-schemes" content="light" />
<title><?php echo esc_html(get_bloginfo('name', 'display')); ?></title>
</head>
<body <?php echo is_rtl() ? 'rightmargin' : 'leftmargin'; ?>="0" topmargin="0" marginwidth="0" marginheight="0" style="background-color:#F8FAFC;margin:0;padding:0;">
<table border="0" cellpadding="0" cellspacing="0" height="100%" width="100%" id="outer_wrapper" style="background-color:#F8FAFC;">
<tr>
<td align="center" valign="top">
<div id="template_header_image"></div>
<table border="0" cellpadding="0" cellspacing="0" width="600" id="template_container" style="background-color:#FFFFFF;border:1px solid rgba(56,189,248,0.16);border-radius:14px;margin:24px auto;overflow:hidden;">

  <!-- Brand bar — kept dark by design: this is the locked logo
       environment. Colors inside the lockup (NG monogram + NEO/GEN
       wordmark) match the brand kit and must not change. -->
  <tr>
    <td align="center" valign="top">
      <table border="0" cellpadding="0" cellspacing="0" width="100%" id="template_header" style="background-color:#050505;border-bottom:1px solid rgba(56,189,248,0.34);">
        <tr>
          <td id="header_wrapper" style="padding:24px 28px;">
            <table border="0" cellpadding="0" cellspacing="0" width="100%">
              <tr>
                <td align="<?php echo is_rtl() ? 'right' : 'left'; ?>" valign="middle" style="font-family:'IBM Plex Mono','Courier New',monospace;font-size:11px;letter-spacing:0.18em;color:#38BDF8;text-transform:uppercase;">
                  // TRANSMISSION RECEIVED
                </td>
                <td align="<?php echo is_rtl() ? 'left' : 'right'; ?>" valign="middle">
                  <span style="font-family:'Major Mono Display','Courier New',monospace;font-size:24px;letter-spacing:0.06em;color:#cfc9bb;">N<span style="color:#00d1ff;">G</span></span>
                  <span style="display:inline-block;width:1px;height:18px;background:#00d1ff;opacity:0.5;margin:0 10px;vertical-align:middle;"></span>
                  <span style="font-family:'Chakra Petch','IBM Plex Sans',sans-serif;font-size:22px;letter-spacing:0.02em;color:#cfc9bb;font-weight:300;">NEO</span><span style="font-family:'Chakra Petch','IBM Plex Sans',sans-serif;font-size:22px;letter-spacing:0.02em;color:#00d1ff;font-weight:700;">GEN</span>
                </td>
              </tr>
            </table>
          </td>
        </tr>
      </table>
    </td>
  </tr>

  <!-- Heading -->
  <tr>
    <td align="center" valign="top">
      <table border="0" cellpadding="0" cellspacing="0" width="100%" id="template_header_heading">
        <tr>
          <td id="header_heading_wrapper" style="padding:32px 28px 16px 28px;">
            <h1 style="font-family:'Tajawal','Arial',sans-serif;font-size:28px;font-weight:600;line-height:1.2;color:#0F172A;margin:0 0 6px 0;text-align:<?php echo is_rtl() ? 'right' : 'left'; ?>;">
              <?php echo wp_kses_post($email_heading); ?>
            </h1>
            <div style="font-family:'IBM Plex Mono','Courier New',monospace;font-size:10px;letter-spacing:0.22em;color:#64748B;text-transform:uppercase;text-align:<?php echo is_rtl() ? 'right' : 'left'; ?>;">
              <?php echo esc_html(strtoupper($cr['brand_en'])); ?> · <?php echo esc_html(date_i18n('d M Y · H:i', current_time('timestamp'))); ?>
            </div>
          </td>
        </tr>
      </table>
    </td>
  </tr>

  <!-- Body wrapper -->
  <tr>
    <td align="center" valign="top">
      <table border="0" cellpadding="0" cellspacing="0" width="100%" id="template_body">
        <tr>
          <td valign="top" id="body_content" style="background-color:#FFFFFF;">
            <table border="0" cellpadding="20" cellspacing="0" width="100%">
              <tr>
                <td valign="top" style="padding:24px 28px 16px 28px;font-family:'Tajawal','Arial',sans-serif;font-size:15px;color:#334155;line-height:1.65;">
                  <div id="body_content_inner">
