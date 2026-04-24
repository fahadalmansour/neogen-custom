<?php
/**
 * NeoGen /legal/ page template.
 *
 * Routed via template_include in mu-plugins/neogen-theme.php when
 * ?neogen_page=legal. Guarded by NG_RENDER_LEGAL_PAGE sentinel so
 * direct-require at mu-plugin boot is a clean no-op.
 *
 * Renders the active MOC commercial-register record as an operator-
 * console identity readout. All data pulls from ng_cr() — the single
 * source of truth defined in neogen-theme.php.
 */

defined('ABSPATH') || exit;
if (!defined('NG_RENDER_LEGAL_PAGE')) return;
if (!function_exists('get_header')) return;

get_header();

$cr = ng_cr();
?>

<main class="ng-legal-page">

  <section class="ng-legal-hero">
    <div class="ng-legal-bg" aria-hidden="true">
      <svg viewBox="-50 -50 100 100">
        <path d="M0 -44 L9 -26 L35 -35 L26 -9 L44 0 L26 9 L35 35 L9 26 L0 44 L-9 26 L-35 35 L-26 9 L-44 0 L-26 -9 L-35 -35 L-9 -26 Z"/>
      </svg>
    </div>
    <div class="ng-legal-inner">
      <div class="ng-legal-kicker">
        <span class="led on" aria-hidden="true"></span>
        <span>01 · LEGAL · DISCLOSURE</span>
      </div>
      <h1 class="ng-legal-h1">
        <span class="ar">هوية المنشأة</span>
        <span class="en">ESTABLISHMENT IDENTITY</span>
      </h1>
      <p class="ng-legal-lede">
        All data below is reproduced verbatim from the active Ministry of Commerce register
        for CR <b><?php echo esc_html( $cr['cr'] ); ?></b>. Verify at any time via
        <a href="<?php echo esc_url( $cr['verify_url'] ); ?>" rel="noopener">eservices.mc.gov.sa</a>.
      </p>
    </div>
  </section>

  <section class="ng-legal-section">
    <div class="ng-legal-inner">

      <!-- IDENTITY -->
      <div class="ng-legal-block">
        <div class="ng-legal-block-head">
          <span>// IDENTITY</span>
          <span>MOC REGISTER</span>
        </div>
        <dl class="ng-legal-readout">
          <div class="row">
            <dt>LEGAL NAME · AR</dt>
            <dd class="ar"><?php echo esc_html( $cr['legal_name_ar'] ); ?></dd>
          </div>
          <div class="row">
            <dt>LEGAL NAME · EN</dt>
            <dd><?php echo esc_html( $cr['legal_name_en'] ); ?></dd>
          </div>
          <div class="row">
            <dt>BRAND</dt>
            <dd><span class="ar"><?php echo esc_html( $cr['brand_ar'] ); ?></span> · <?php echo esc_html( $cr['brand_en'] ); ?></dd>
          </div>
          <div class="row">
            <dt>OWNER</dt>
            <dd><?php echo esc_html( $cr['owner'] ); ?></dd>
          </div>
          <div class="row">
            <dt>ENTITY TYPE</dt>
            <dd><span class="ar"><?php echo esc_html( $cr['entity_type_ar'] ); ?></span> · <?php echo esc_html( $cr['entity_type'] ); ?></dd>
          </div>
          <div class="row">
            <dt>REGISTER TYPE</dt>
            <dd><?php echo esc_html( $cr['register_type'] ); ?></dd>
          </div>
          <div class="row">
            <dt>CR · UNIFIED NATIONAL NUMBER</dt>
            <dd class="mono-bold"><?php echo esc_html( $cr['cr'] ); ?></dd>
          </div>
          <div class="row">
            <dt>STATUS</dt>
            <dd class="status">
              <span class="led on" aria-hidden="true"></span>
              <span><span class="ar"><?php echo esc_html( $cr['status_ar'] ); ?></span> · <?php echo esc_html( strtoupper( $cr['status'] ) ); ?></span>
            </dd>
          </div>
          <div class="row">
            <dt>REGISTERED</dt>
            <dd><?php echo esc_html( $cr['registered_ad'] ); ?> · <span class="ar"><?php echo esc_html( $cr['registered_ah'] ); ?>هـ</span></dd>
          </div>
          <div class="row">
            <dt>ANNUAL CONFIRMATION DUE</dt>
            <dd><?php echo esc_html( $cr['next_conf_ad'] ); ?> · <span class="ar"><?php echo esc_html( $cr['next_conf_ah'] ); ?>هـ</span></dd>
          </div>
          <div class="row">
            <dt>CAPITAL</dt>
            <dd><?php echo esc_html( $cr['capital_sar'] ); ?> <small>SAR</small></dd>
          </div>
          <div class="row">
            <dt>AUTHORITY</dt>
            <dd>
              <span class="ar"><?php echo esc_html( $cr['authority_ar'] ); ?></span>
              · <a href="<?php echo esc_url( $cr['authority_url'] ); ?>" rel="noopener"><?php echo esc_html( $cr['authority'] ); ?></a>
            </dd>
          </div>
        </dl>
      </div>

      <!-- REGULATORY REGISTRATIONS -->
      <div class="ng-legal-block">
        <div class="ng-legal-block-head">
          <span>// REGULATORY REGISTRATIONS</span>
          <span>SAUDI AUTHORITIES</span>
        </div>
        <div class="ng-legal-regs">
          <!-- CR primary -->
          <div class="ng-legal-reg">
            <div class="ng-legal-reg-head">
              <div class="ar"><?php echo esc_html( $cr['authority_ar'] ); ?></div>
              <div class="en"><?php echo esc_html( strtoupper( $cr['authority'] ) ); ?></div>
            </div>
            <div class="ng-legal-reg-num">
              <span class="k">CR · UNIFIED NATIONAL NUMBER</span>
              <span class="v"><?php echo esc_html( $cr['cr'] ); ?></span>
            </div>
            <a class="ng-legal-reg-link" href="<?php echo esc_url( $cr['authority_url'] ); ?>" rel="noopener" target="_blank">
              VERIFY · MC.GOV.SA
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6"/></svg>
            </a>
          </div>
          <!-- Additional bodies -->
          <?php foreach ( $cr['regulatory'] as $r ) : ?>
          <div class="ng-legal-reg">
            <div class="ng-legal-reg-head">
              <div class="ar"><?php echo esc_html( $r['authority_ar'] ); ?></div>
              <div class="en"><?php echo esc_html( strtoupper( $r['authority_en'] ) ); ?></div>
            </div>
            <div class="ng-legal-reg-num">
              <span class="k"><?php echo esc_html( $r['label'] ); ?></span>
              <span class="v"><?php echo esc_html( $r['number'] ); ?></span>
            </div>
            <a class="ng-legal-reg-link" href="<?php echo esc_url( $r['url'] ); ?>" rel="noopener" target="_blank">
              VERIFY · <?php echo esc_html( strtoupper( wp_parse_url( $r['url'], PHP_URL_HOST ) ) ); ?>
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6"/></svg>
            </a>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- CONTACT -->
      <div class="ng-legal-block">
        <div class="ng-legal-block-head">
          <span>// CONTACT</span>
          <span>MOC-REGISTERED</span>
        </div>
        <div class="ng-legal-tiles">
          <div class="ng-legal-tile">
            <div class="k">LANDLINE</div>
            <a class="v" href="tel:<?php echo esc_attr( preg_replace('/\s+/', '', $cr['phone_landline']) ); ?>"><?php echo esc_html( $cr['phone_landline'] ); ?></a>
          </div>
          <div class="ng-legal-tile">
            <div class="k">MOBILE</div>
            <a class="v" href="tel:<?php echo esc_attr( preg_replace('/\s+/', '', $cr['phone_mobile']) ); ?>"><?php echo esc_html( $cr['phone_mobile'] ); ?></a>
          </div>
          <div class="ng-legal-tile">
            <div class="k">EMAIL</div>
            <a class="v" href="mailto:<?php echo esc_attr( $cr['email'] ); ?>"><?php echo esc_html( $cr['email'] ); ?></a>
          </div>
        </div>
      </div>

      <!-- ACTIVITIES -->
      <div class="ng-legal-block">
        <div class="ng-legal-block-head">
          <span>// REGISTERED ACTIVITIES</span>
          <span>ISIC v4</span>
        </div>
        <div class="ng-legal-activities">
          <?php foreach ( $cr['activities'] as $a ) : ?>
          <div class="ng-legal-activity">
            <div class="code"><?php echo esc_html( $a['code'] ); ?></div>
            <div class="ar"><?php echo esc_html( $a['ar'] ); ?></div>
            <div class="en"><?php echo esc_html( strtoupper( $a['en'] ) ); ?></div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- VERIFY BAND -->
      <div class="ng-legal-verify">
        <div class="ng-legal-verify-inner">
          <div class="k">VERIFY INDEPENDENTLY</div>
          <p>
            The register above is a live government record. Scan the QR code on the
            original MOC certificate or visit the verification portal directly and
            enter CR <b><?php echo esc_html( $cr['cr'] ); ?></b>.
          </p>
          <a class="btn btn-ghost" href="<?php echo esc_url( $cr['verify_url'] ); ?>" rel="noopener" target="_blank">
            OPEN MOC VERIFICATION PORTAL
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6"/></svg>
          </a>
        </div>
      </div>

      <?php
      /**
       * Extension point — VAT / physical address / etc. arrive here
       * via add_action('neogen_legal_extra', ...) in a future commit.
       *
       * Output is captured and run through wp_kses_post so callbacks
       * that emit raw HTML (the typical pattern for lawyer-supplied
       * legal copy) cannot ship <script> or other dangerous tags.
       * The allowed tags are bounded by wp_kses_post — the same set
       * WordPress accepts in post_content. That covers everything a
       * legal page needs (h2/h3, p, ul/ol, a, strong, em, blockquote)
       * without giving callbacks a script-injection channel.
       */
      ob_start();
      do_action('neogen_legal_extra', $cr);
      echo wp_kses_post(ob_get_clean());
      ?>

      <!-- VOICE CLOSE -->
      <div class="ng-legal-voice">
        TECHNOLOGY
        <span class="sep"></span>
        AS IT SHOULD BE
        <span class="sep"></span>
        SHIPPED FROM KSA
      </div>

    </div>
  </section>

</main>

<?php
get_footer();
