<?php
/**
 * NeoGen override for the customer-processing-order email.
 *
 * This is the receipt sent the moment payment clears (most common
 * email a customer receives after checkout). Routed via the
 * wc_get_template filter map.
 *
 * Header + footer + styles are themed via separate overrides; this
 * file owns the body markup between them.
 *
 * @var WC_Order $order
 * @var bool     $sent_to_admin
 * @var bool     $plain_text
 * @var string   $email_heading
 * @var string   $additional_content
 * @var WC_Email $email
 */

defined('ABSPATH') || exit;

do_action('woocommerce_email_header', $email_heading, $email); ?>

<p style="font-family:'Tajawal','Arial',sans-serif;font-size:15px;color:#e5e3dd;line-height:1.7;margin:0 0 16px 0;">
  <?php
  /* translators: %s: Customer first name */
  printf(esc_html__('Hi %s,', 'woocommerce'), esc_html($order->get_billing_first_name()));
  ?>
</p>

<p style="font-family:'Tajawal','Arial',sans-serif;font-size:15px;color:#cfc9bb;line-height:1.7;margin:0 0 8px 0;">
  <span style="display:inline-block;width:8px;height:8px;background-color:#3fe88f;border-radius:50%;vertical-align:middle;margin-right:8px;"></span>
  <?php esc_html_e('Thanks for your order — we received it and it\'s now being processed.', 'woocommerce'); ?>
</p>

<p style="font-family:'Tajawal','Arial',sans-serif;font-size:15px;color:#cfc9bb;line-height:1.7;direction:rtl;text-align:right;margin:0 0 24px 0;">
  استلمنا طلبك بنجاح وجاري تجهيزه. سنرسل لك إشعاراً آخر عند الشحن.
</p>

<?php
/*
 * @hooked WC_Emails::order_details() Shows the order details table.
 * @hooked WC_Structured_Data::generate_order_data() Generates structured data.
 * @hooked WC_Structured_Data::output_structured_data() Outputs structured data.
 */
do_action('woocommerce_email_order_details', $order, $sent_to_admin, $plain_text, $email);

/*
 * @hooked WC_Emails::order_meta() Shows order meta data.
 */
do_action('woocommerce_email_order_meta', $order, $sent_to_admin, $plain_text, $email);

/*
 * @hooked WC_Emails::customer_details() Shows customer details
 * @hooked WC_Emails::email_address() Shows email address
 */
do_action('woocommerce_email_customer_details', $order, $sent_to_admin, $plain_text, $email);
?>

<?php if ($additional_content) : ?>
<div style="margin-top:24px;padding-top:18px;border-top:1px dashed rgba(0,209,255,0.16);font-family:'Tajawal','Arial',sans-serif;font-size:14px;color:#cfc9bb;line-height:1.65;">
  <?php echo wp_kses_post(wpautop(wptexturize($additional_content))); ?>
</div>
<?php endif; ?>

<p style="margin-top:28px;padding-top:18px;border-top:1px dashed rgba(0,209,255,0.16);font-family:'IBM Plex Mono','Courier New',monospace;font-size:11px;letter-spacing:0.12em;color:#8f8a7e;text-transform:uppercase;">
  <?php
  $cr = function_exists('ng_cr') ? ng_cr() : ['email' => 'support@neogen.store'];
  printf(
    /* translators: %s — support email link */
    esc_html__('Questions about this order? Reply to this email or write to %s.', 'neogen'),
    '<a href="mailto:' . esc_attr($cr['email']) . '" style="color:#00d1ff;text-decoration:none;border-bottom:1px solid rgba(0,209,255,0.34);">' . esc_html($cr['email']) . '</a>'
  );
  ?>
</p>

<?php do_action('woocommerce_email_footer', $email); ?>
