<?php
/**
 * One-off: populate About (page 30) + Contact (page 31) with the
 * NeoGen Store v1.31.0 page content. Idempotent — overwrites whatever
 * is in post_content.
 *
 * Run via:
 *   wp eval-file /tmp/neogen-about-contact-content.php --skip-plugins=litespeed-cache --user=1
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

// Page IDs from earlier inventory.
$about_id   = 30;
$contact_id = 31;

// ---------- ABOUT PAGE (id 30) ----------
$about_html = <<<'HTML'
<section class="ng-page-hero ng-page-hero--about">
  <div class="ng-container">
    <div class="ng-page-hero-kicker">01 · <b>من نحن</b></div>
    <h1 class="ng-page-hero-h1">متجر تقني سعودي.<br><span class="accent">لمن يبني.</span></h1>
    <p class="ng-page-hero-copy">
      NeoGen Store هو متجر تقني سعودي معتمد، يخدم محترفي الشبكات، الهوم لاب، البيوت الذكية، والألعاب. نختار المنتجات بناءً على الموثوقية، قابلية الإصلاح، وتوفّر القطع داخل المملكة — لا إعلانات لامعة، ولا أرقام مفبركة.
    </p>
  </div>
</section>

<section class="ng-page-section ng-about-principles">
  <div class="ng-container">
    <div class="ng-section-head">
      <div>
        <div class="ng-section-label">02 · <b>المبادئ</b></div>
        <h2 class="ng-section-h">كيف نختار. <span class="accent">كيف نشحن.</span></h2>
      </div>
    </div>
    <div class="ng-principles-grid">
      <article class="ng-principle">
        <div class="ng-principle-num">01</div>
        <h3 class="ng-principle-title">منتجات مختارة</h3>
        <p>كل منتج في الكتالوج يخدم استخدامًا تقنيًا واضحًا — شبكة جادة، هوم لاب، بيت ذكي، أو محطة ألعاب. لا منتجات لمجرد التواجد.</p>
      </article>
      <article class="ng-principle">
        <div class="ng-principle-num">02</div>
        <h3 class="ng-principle-title">مواصفات بدون مبالغة</h3>
        <p>نكتب المواصفات كما هي من المصنّع. لا "خارق" ولا "ثوري" — فقط الأرقام والقدرات الفعلية.</p>
      </article>
      <article class="ng-principle">
        <div class="ng-principle-num">03</div>
        <h3 class="ng-principle-title">شحن من المملكة</h3>
        <p>المنتجات مخزّنة داخل السعودية. شحن خلال 2-5 أيام عمل عبر سمسا أو أرامكس، مع رقم تتبّع.</p>
      </article>
      <article class="ng-principle">
        <div class="ng-principle-num">04</div>
        <h3 class="ng-principle-title">خدمة تنفيذ</h3>
        <p>للمشاريع الكبيرة — شبكة مكتبية، هوم لاب كامل، بيت ذكي. نختار، نشحن، ونركّب بالموقع.</p>
      </article>
    </div>
  </div>
</section>

<section class="ng-page-section ng-about-numbers">
  <div class="ng-container">
    <div class="ng-numbers-grid">
      <div class="ng-number"><div class="ng-number-value" dir="ltr">210</div><div class="ng-number-label">منتج في الكتالوج</div></div>
      <div class="ng-number"><div class="ng-number-value" dir="ltr">12</div><div class="ng-number-label">فئة مهيّأة</div></div>
      <div class="ng-number"><div class="ng-number-value" dir="ltr">2–5</div><div class="ng-number-label">أيام شحن</div></div>
      <div class="ng-number"><div class="ng-number-value" dir="ltr">14</div><div class="ng-number-label">يوم إرجاع</div></div>
      <div class="ng-number"><div class="ng-number-value" dir="ltr">12</div><div class="ng-number-label">شهر ضمان</div></div>
    </div>
  </div>
</section>

<section class="ng-page-section ng-about-disclosure-band">
  <div class="ng-container">
    <div class="ng-section-head">
      <div>
        <div class="ng-section-label">03 · <b>هوية المنشأة</b></div>
        <h2 class="ng-section-h">معتمدون. <span class="accent">موثّقون.</span></h2>
      </div>
    </div>
    <div class="ng-disclosure-grid">
      <div class="ng-disclosure-card">
        <div class="ng-disclosure-label">سجل تجاري</div>
        <div class="ng-disclosure-value" dir="ltr">7053130576</div>
      </div>
      <div class="ng-disclosure-card">
        <div class="ng-disclosure-label">هيئة الزكاة والضريبة والجمارك</div>
        <div class="ng-disclosure-value" dir="ltr">3145127947</div>
      </div>
      <div class="ng-disclosure-card">
        <div class="ng-disclosure-label">هيئة الغرف التجارية</div>
        <div class="ng-disclosure-value" dir="ltr">1238532</div>
      </div>
      <div class="ng-disclosure-card">
        <div class="ng-disclosure-label">الضريبة على القيمة المضافة</div>
        <div class="ng-disclosure-value">15% شاملة</div>
      </div>
    </div>
    <p class="ng-about-cities">الرياض · جدة · الدمام</p>
  </div>
</section>
HTML;

// ---------- CONTACT PAGE (id 31) ----------
$contact_html = <<<'HTML'
<section class="ng-page-hero ng-page-hero--contact">
  <div class="ng-container">
    <div class="ng-page-hero-kicker">07 · <b>مكتب الخدمة</b></div>
    <h1 class="ng-page-hero-h1">أرسل لنا <span class="accent">مواصفاتك.</span><br>نرجع لك بخطة.</h1>
    <p class="ng-page-hero-copy">
      شبكة لمكتب، هوم لاب، بيت ذكي، أو محطة ألعاب — اشرح الاحتياج بإيجاز، ونرد عليك خلال يوم عمل بمخطط مفصّل، قائمة مكوّنات، وتقدير زمن الشحن والتركيب.
    </p>
  </div>
</section>

<section class="ng-page-section ng-contact-grid-section">
  <div class="ng-container">
    <div class="ng-contact-grid">
      <form class="ng-contact-form" action="#" method="post" data-ng-form="contact" novalidate>
        <div class="ng-contact-field">
          <label for="ng-name">اسمك</label>
          <input type="text" id="ng-name" name="name" required autocomplete="name">
        </div>
        <div class="ng-contact-field">
          <label for="ng-email">بريدك الإلكتروني</label>
          <input type="email" id="ng-email" name="email" required autocomplete="email">
        </div>
        <div class="ng-contact-field">
          <label for="ng-phone">الجوّال</label>
          <input type="tel" id="ng-phone" name="phone" autocomplete="tel" placeholder="+966...">
        </div>
        <div class="ng-contact-field">
          <label for="ng-type">نوع المشروع</label>
          <select id="ng-type" name="project_type" required>
            <option value="">— اختر —</option>
            <option value="network">شبكة مكتبية</option>
            <option value="homelab">هوم لاب</option>
            <option value="smart-home">بيت ذكي</option>
            <option value="gaming">محطة ألعاب</option>
            <option value="services">خدمة تنفيذ مخصّصة</option>
          </select>
        </div>
        <div class="ng-contact-field ng-contact-field--full">
          <label for="ng-spec">المواصفات</label>
          <textarea id="ng-spec" name="spec" rows="6" required placeholder="اشرح الاحتياج بإيجاز — الحجم، الميزانية، الموعد..."></textarea>
        </div>
        <div class="ng-contact-actions">
          <button type="submit" class="btn btn-primary">
            أرسل المواصفات
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6"/></svg>
          </button>
          <p class="ng-contact-note">نرد خلال يوم عمل. لا سبام.</p>
        </div>
        <div class="ng-contact-status" role="status" aria-live="polite"></div>
      </form>

      <aside class="ng-contact-sidebar">
        <a class="ng-contact-channel ng-contact-channel--whatsapp" href="https://wa.me/966570131122" target="_blank" rel="noopener noreferrer">
          <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M20.5 3.5A11 11 0 0 0 3 17l-1 5 5.2-1.4A11 11 0 1 0 20.5 3.5zM12 21a9 9 0 0 1-4.6-1.3l-.3-.2-3 .8.8-3-.2-.3A9 9 0 1 1 12 21zm5-7c-.3-.1-1.7-.8-2-.9-.3-.1-.5-.1-.7.2l-.9 1.1c-.2.2-.3.2-.6.1-1-.5-2-.8-2.9-2-.2-.4 0-.4.2-.6l.7-.8c.1-.2.1-.4 0-.6l-1-2.3c-.2-.5-.5-.4-.7-.4h-.6c-.2 0-.5.1-.7.3-.3.2-1 .9-1 2.3 0 1.4 1 2.7 1.2 2.9.2.2 2.1 3.1 5 4.3 1.7.7 2.4.7 3.3.6.5-.1 1.7-.7 2-1.3.2-.6.2-1.2.2-1.3 0-.1-.3-.2-.5-.3z"/></svg>
          <div>
            <div class="ng-contact-channel-label">واتساب</div>
            <div class="ng-contact-channel-detail" dir="ltr">+966 57 013 1122</div>
          </div>
        </a>

        <a class="ng-contact-channel" href="mailto:hello@neogen.store">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>
          <div>
            <div class="ng-contact-channel-label">بريد إلكتروني</div>
            <div class="ng-contact-channel-detail" dir="ltr">hello@neogen.store</div>
          </div>
        </a>

        <div class="ng-contact-meta">
          <div class="ng-contact-meta-row">
            <span class="ng-contact-meta-label">المدن</span>
            <span class="ng-contact-meta-value">الرياض · جدة · الدمام</span>
          </div>
          <div class="ng-contact-meta-row">
            <span class="ng-contact-meta-label">ساعات الرد</span>
            <span class="ng-contact-meta-value">الأحد - الخميس · 9 ص - 6 م</span>
          </div>
          <div class="ng-contact-meta-row">
            <span class="ng-contact-meta-label">سجل تجاري</span>
            <span class="ng-contact-meta-value" dir="ltr">7053130576</span>
          </div>
          <div class="ng-contact-meta-row">
            <span class="ng-contact-meta-label">الضريبة</span>
            <span class="ng-contact-meta-value">15% شاملة</span>
          </div>
        </div>

        <div class="ng-contact-steps">
          <h3>ماذا يحدث بعد الإرسال</h3>
          <ol>
            <li><b>1.</b> نستلم طلبك ونراجع الاحتياج.</li>
            <li><b>2.</b> نرد بخطة تنفيذ مفصّلة خلال يوم عمل.</li>
            <li><b>3.</b> نشحن أو نركّب حسب الاتفاق.</li>
          </ol>
        </div>
      </aside>
    </div>
  </div>
</section>
HTML;

// Update both pages.
$about_post = get_post( $about_id );
if ( ! $about_post ) {
    WP_CLI::error( "About page (id $about_id) not found." );
}
$contact_post = get_post( $contact_id );
if ( ! $contact_post ) {
    WP_CLI::error( "Contact page (id $contact_id) not found." );
}

$res_about = wp_update_post( array(
    'ID'           => $about_id,
    'post_content' => $about_html,
    'post_status'  => 'publish',
), true );
if ( is_wp_error( $res_about ) ) {
    WP_CLI::error( 'About update failed: ' . $res_about->get_error_message() );
}
WP_CLI::log( "About (id $about_id) updated. Bytes: " . strlen( $about_html ) );

$res_contact = wp_update_post( array(
    'ID'           => $contact_id,
    'post_content' => $contact_html,
    'post_status'  => 'publish',
), true );
if ( is_wp_error( $res_contact ) ) {
    WP_CLI::error( 'Contact update failed: ' . $res_contact->get_error_message() );
}
WP_CLI::log( "Contact (id $contact_id) updated. Bytes: " . strlen( $contact_html ) );
