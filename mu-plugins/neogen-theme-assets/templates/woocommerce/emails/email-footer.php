<?php
/**
 * NeoGen email footer.
 *
 * Routed via wc_get_template filter map. Closes the table chain
 * opened in email-header.php and adds the brand identity strip
 * (CR / ZATCA / CSC) plus contact info.
 * @version 10.4.0 (NeoGen reconciled against upstream WC 10.7.0)
 */

defined('ABSPATH') || exit;

$cr = function_exists('ng_cr') ? ng_cr() : [
    'brand_en'     => 'NeoGen Store',
    'cr'           => '7053130576',
    'email'        => 'support@neogen.store',
    'phone_mobile' => '',
    'website'      => 'https://neogen.store/',
    'regulatory'   => [],
];
?>
                  </div>
                </td>
              </tr>
            </table>
          </td>
        </tr>
      </table>
    </td>
  </tr>

  <!-- Identity strip -->
  <tr>
    <td align="center" valign="top">
      <table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color:#050505;border-top:1px dashed rgba(0,209,255,0.16);">
        <tr>
          <td style="padding:18px 28px;font-family:'IBM Plex Mono','Courier New',monospace;font-size:10px;letter-spacing:0.14em;color:#8f8a7e;text-transform:uppercase;text-align:center;">
            CR <span style="color:#00d1ff;font-weight:700;letter-spacing:0.06em;"><?php echo esc_html($cr['cr']); ?></span>
            <?php foreach ($cr['regulatory'] as $r) : ?>
              &nbsp;·&nbsp; <?php echo esc_html(strtoupper($r['key'])); ?> <span style="color:#00d1ff;font-weight:700;letter-spacing:0.06em;"><?php echo esc_html($r['number']); ?></span>
            <?php endforeach; ?>
            &nbsp;·&nbsp; VAT 15% INCLUDED
          </td>
        </tr>
      </table>
    </td>
  </tr>

  <!-- Contact strip -->
  <tr>
    <td align="center" valign="top">
      <table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color:#050505;">
        <tr>
          <td style="padding:14px 28px 22px 28px;font-family:'IBM Plex Mono','Courier New',monospace;font-size:11px;letter-spacing:0.08em;color:#cfc9bb;text-align:center;">
            <?php if (!empty($cr['email'])) : ?>
              <a href="mailto:<?php echo esc_attr($cr['email']); ?>" style="color:#00d1ff;text-decoration:none;"><?php echo esc_html($cr['email']); ?></a>
            <?php endif; ?>
            <?php if (!empty($cr['phone_mobile'])) : ?>
              &nbsp;&nbsp;·&nbsp;&nbsp;
              <a href="tel:<?php echo esc_attr(preg_replace('/\s+/', '', $cr['phone_mobile'])); ?>" style="color:#00d1ff;text-decoration:none;"><?php echo esc_html($cr['phone_mobile']); ?></a>
            <?php endif; ?>
            <?php if (!empty($cr['website'])) : ?>
              &nbsp;&nbsp;·&nbsp;&nbsp;
              <a href="<?php echo esc_url($cr['website']); ?>" style="color:#00d1ff;text-decoration:none;"><?php echo esc_html(preg_replace('#^https?://#', '', rtrim($cr['website'], '/'))); ?></a>
            <?php endif; ?>
          </td>
        </tr>
      </table>
    </td>
  </tr>

  <!-- Voice close -->
  <tr>
    <td align="center" valign="top">
      <table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color:#050505;border-top:1px dashed rgba(0,209,255,0.16);">
        <tr>
          <td style="padding:14px 28px;font-family:'Chakra Petch','IBM Plex Sans',sans-serif;font-size:11px;letter-spacing:0.28em;color:#8f8a7e;text-transform:uppercase;text-align:center;">
            TECHNOLOGY
            <span style="display:inline-block;width:18px;height:1px;background:#00d1ff;opacity:0.55;vertical-align:middle;margin:0 10px;"></span>
            AS IT SHOULD BE
            <span style="display:inline-block;width:18px;height:1px;background:#00d1ff;opacity:0.55;vertical-align:middle;margin:0 10px;"></span>
            SHIPPED FROM KSA
          </td>
        </tr>
      </table>
    </td>
  </tr>

</table>
</td>
</tr>
</table>
</body>
</html>
