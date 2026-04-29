<?php
/**
 * v1.36.10 — generate AR titles for products that lack `_ng_ar_title`.
 *
 * After v1.36.9 extracted AR fragments from pipe-split titles (89/288),
 * 199 products still have EN-only titles. The site renders AR by default
 * (R6 spec, RTL) so EN-only product cards feel out of place.
 *
 * This pass uses a curated EN→AR dictionary (brand transliterations +
 * product-type translations) to generate AR titles, leaving model
 * numbers in Latin (standard practice in AR tech e-commerce).
 *
 * Each generated title is stamped with:
 *   _ng_ar_title              the generated AR string
 *   _ng_ar_title_source       'auto-generated'
 *
 * Admin can review the auto-generated set in wp-admin → search by meta
 * `_ng_ar_title_source = auto-generated`, then overwrite with a human
 * AR copy if needed.
 *
 * Truth-rule note: this is not full translation, it's mechanical
 * substitution from a curated dictionary. The `_source` tag preserves
 * traceability so humans can flag and re-translate.
 *
 * Run via:
 *   wp eval-file /tmp/neogen-generate-ar-titles.php --skip-plugins=litespeed-cache --user=1
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }
if ( ! function_exists( 'wc_get_product' ) ) { WP_CLI::error( 'WC not loaded' ); }

// Dictionary — exact-match phrase → AR. Order matters (longer phrases first).
$DICT = array(
    // Multi-word product types FIRST (longer match wins)
    '3D Printer'             => 'طابعة ثلاثية الأبعاد',
    'Capture Card'           => 'كرت تسجيل',
    'Stream Deck'            => 'ستريم ديك',
    'Robot Vacuum'           => 'روبوت تنظيف',
    'Pool Cleaning Robot'    => 'روبوت تنظيف مسابح',
    'Pool Cleaner'           => 'روبوت تنظيف مسابح',
    'Window Cleaning Robot'  => 'روبوت تنظيف زجاج',
    'Window Cleaner'         => 'روبوت تنظيف زجاج',
    'Robotic Pool Cleaner'   => 'روبوت تنظيف مسابح',
    'Robotic Lawn Mower'     => 'جزّازة عشب آلية',
    'Robot Mower'            => 'جزّازة عشب آلية',
    'Lawn Mower'             => 'جزّازة عشب',
    'Power Supply'           => 'مزود طاقة',
    'Mid-Tower'              => 'كيس متوسط',
    'Full-Tower'             => 'كيس كبير',
    'Mini-Tower'             => 'كيس صغير',
    'ATX Case'               => 'كيس ATX',
    'Gaming Chair'           => 'كرسي قيمنق',
    'Gaming Light'           => 'إضاءة قيمنق',
    'Gaming Headset'         => 'سماعة قيمنق',
    'Gaming Mouse'           => 'ماوس قيمنق',
    'Gaming Keyboard'        => 'كيبورد قيمنق',
    'USB Microphone'         => 'ميكروفون USB',
    'Founders Edition'       => 'فاوندرز إديشن',
    'Wireless Headset'       => 'سماعة لاسلكية',
    'Access Point'           => 'نقطة وصول',
    'Smart Home Hub'         => 'هاب منزل ذكي',
    'Smart Plug'             => 'قابس ذكي',
    'Smart Bulb'             => 'لمبة ذكية',
    'Action Camera'          => 'كاميرا أكشن',
    'Mechanical Keyboard'    => 'كيبورد ميكانيكي',
    'Magnetic Switch'        => 'سويتش مغناطيسي',
    'AIO Cooler'             => 'مبرد مائي',
    'Liquid Cooler'          => 'مبرد مائي',
    'CPU Cooler'             => 'مبرد معالج',
    'Lighting Panels'        => 'بانلات إضاءة',
    'Gift Card'              => 'بطاقة هدية',

    // Brands (transliterations)
    'ASUS ROG'        => 'اسوس روج',
    'NVIDIA'          => 'إنفيديا',
    'Gigabyte AORUS'  => 'جيجابايت AORUS',
    'Gigabyte'        => 'جيجابايت',
    'Corsair'         => 'كورسير',
    'HyperX'          => 'هايبر إكس',
    'SteelSeries'     => 'ستيل سيريز',
    'Razer'           => 'رايزر',
    'Logitech G'      => 'لوجيتك G',
    'Logitech'        => 'لوجيتك',
    'Microsoft'       => 'مايكروسوفت',
    'Sony'            => 'سوني',
    'Apple'           => 'آبل',
    'Samsung Odyssey' => 'سامسونج Odyssey',
    'Samsung'         => 'سامسونج',
    'LG UltraGear'    => 'إل جي UltraGear',
    'LG '             => 'إل جي ',
    'Dell Alienware'  => 'ديل Alienware',
    'Dell OptiPlex'   => 'ديل OptiPlex',
    'Dell PowerEdge'  => 'ديل PowerEdge',
    'Dell'            => 'ديل',
    'HPE ProLiant'    => 'HPE ProLiant',
    'Lenovo ThinkStation' => 'لينوفو ThinkStation',
    'Lenovo'          => 'لينوفو',
    'NZXT'            => 'NZXT',
    'Lian Li'         => 'ليان لي',
    'Cooler Master'   => 'كولر ماستر',
    'Thermaltake'     => 'ثيرمالتيك',
    'Fractal Design'  => 'فراكتال ديزاين',
    'Noctua'          => 'نوكتوا',
    'be quiet!'       => 'بي كويت',
    'Crucial'         => 'كروشال',
    'Western Digital' => 'ويسترن ديجيتال',
    'Seagate'         => 'سيجيت',
    'Synology'        => 'سينولوجي',
    'QNAP'            => 'كيو ناب',
    'Asustor'         => 'اسوستور',
    'Ubiquiti UniFi'  => 'يوبيكويتي UniFi',
    'Ubiquiti'        => 'يوبيكويتي',
    'TP-Link'         => 'تي بي لينك',
    'Netgate'         => 'نتجيت',
    'MikroTik'        => 'ميكروتيك',
    'Mikrotik'        => 'ميكروتيك',
    'Fortinet'        => 'فورتي نت',
    'Cisco'           => 'سيسكو',
    'NETGEAR'         => 'نت جير',
    'Aruba'           => 'أروبا',
    'DJI'             => 'دي جي آي',
    'Insta360'        => 'إنستا 360',
    'Roborock'        => 'روبوروك',
    'Ecovacs'         => 'إيكوفاكس',
    'Dreame'          => 'دريم',
    'Eufy'            => 'يوفي',
    'eufy'            => 'يوفي',
    'Bambu Lab'       => 'بامبو لاب',
    'Creality'        => 'كريالتي',
    'Anycubic'        => 'اني كيوبك',
    'Phrozen'         => 'فروزن',
    'PHROZEN'         => 'فروزن',
    'Elegoo'          => 'إيليجو',
    'BIQU'            => 'BIQU',
    'Beatbot'         => 'بيت بوت',
    'HOBOT'           => 'هوبوت',
    'Hobot'           => 'هوبوت',
    'Mammotion'       => 'ماموشن',
    'Worx'            => 'ووركس',
    'Maytronics Dolphin' => 'مايترونيكس دولفين',
    'Maytronics'      => 'مايترونيكس',
    'Dolphin'         => 'دولفين',
    'AVerMedia'       => 'أفرميديا',
    'Elgato'          => 'إلجاتو',
    'Sennheiser'      => 'سنهايزر',
    'Govee'           => 'جوفي',
    'Keychron'        => 'كيكرون',
    'JSAUX'           => 'جيه ساكس',
    'Steam Deck'      => 'ستيم ديك',
    'ROG Ally'        => 'ROG Ally',
    'CalDigit'        => 'كال ديجيت',
    'Anker'           => 'انكر',
    'Aukey'           => 'أوكي',
    'Belkin'          => 'بيلكين',
    'Meta Quest'      => 'ميتا كويست',
    'Xbox Live'       => 'إكس بوكس لايف',
    'Xbox Elite'      => 'إكس بوكس إيليت',
    'Xbox Game Pass'  => 'إكس بوكس قيم باس',
    'Xbox'            => 'إكس بوكس',
    'PlayStation Plus' => 'بلايستيشن بلس',
    'PlayStation Store' => 'بلايستيشن ستور',
    'PlayStation'     => 'بلايستيشن',
    'DualSense'       => 'دوال سنس',
    'Steam Wallet'    => 'محفظة ستيم',
    'Steam'           => 'ستيم',
    'Razer Gold'      => 'رايزر قولد',
    'Roblox'          => 'روبلكس',
    'Free Fire'       => 'فري فاير',
    'Mobile Legends'  => 'موبايل ليجندز',
    'PUBG Mobile UC'  => 'ببجي موبايل UC',
    'PUBG Mobile'     => 'ببجي موبايل',
    'Google Play'     => 'قوقل بلاي',
    'iTunes'          => 'آيتونز',
    'App Store'       => 'متجر التطبيقات',
    'Spotify Premium' => 'سبوتيفاي بريميوم',
    'Spotify'         => 'سبوتيفاي',
    'Disney+'         => 'ديزني بلس',
    'Netflix'         => 'نتفلكس',
    'Adobe Creative Cloud' => 'أدوبي كرييتف كلاود',
    'Adobe'           => 'أدوبي',
    'Kaspersky'       => 'كاسبرسكي',
    'Office'          => 'أوفيس',
    'Windows 11'      => 'ويندوز 11',
    'Windows 10'      => 'ويندوز 10',
    'Windows'         => 'ويندوز',
    'Home Assistant'  => 'هوم أسيستنت',
    'Raspberry Pi'    => 'رازبيري باي',
    'Cable Matters'   => 'كيبل ماترز',
    'Cat6a'           => 'Cat6a',
    'Cat6'            => 'Cat6',
    '8BitDo'          => '8BitDo',
    '8Bitdo'          => '8BitDo',
    'TESmart'         => 'TESmart',
    'Govee DreamView' => 'جوفي DreamView',

    // Single-word terms (last)
    'Wireless'        => 'لاسلكي',
    'Bluetooth'       => 'بلوتوث',
    'Camera'          => 'كاميرا',
    'Drone'           => 'درون',
    'Microphone'      => 'ميكروفون',
    'Webcam'          => 'كاميرا ويب',
    'Monitor'         => 'شاشة',
    'Headset'         => 'سماعة',
    'Headphones'      => 'سماعات',
    'Mouse'           => 'ماوس',
    'Keyboard'        => 'كيبورد',
    'Speakers'        => 'سماعات صوت',
    'Router'          => 'راوتر',
    'Switch'          => 'سويتش',
    'Server'          => 'سيرفر',
    'Cable'           => 'كابل',
    'Adapter'         => 'محول',
    'Charger'         => 'شاحن',
    'Dock'            => 'دوك',
    'Controller'      => 'يد تحكم',
    'Filament'        => 'فيلامنت',
    'Doorbell'        => 'جرس باب',
);

global $wpdb;
$missing = $wpdb->get_results(
    "SELECT p.ID, p.post_title FROM {$wpdb->posts} p
      WHERE p.post_type='product' AND p.post_status IN ('publish','draft')
        AND p.ID NOT IN (SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key='_ng_ar_title' AND meta_value!='')"
);

WP_CLI::log( '=== v1.36.10 generate AR titles ===' );
WP_CLI::log( 'Products missing AR title: ' . count( $missing ) );

$generated = 0; $low_coverage = 0;
foreach ( $missing as $r ) {
    $en = $r->post_title;
    $ar = $en;

    // Strip trailing " - Available at NeoGen Store" if present.
    $ar = preg_replace( '/\s*-\s*Available at NeoGen Store\s*$/i', '', $ar );

    // Apply dictionary substitutions.
    $en_remaining = $ar;
    foreach ( $DICT as $en_phrase => $ar_phrase ) {
        $ar = str_replace( $en_phrase, $ar_phrase, $ar );
    }

    // Coverage: count Arabic chars vs total non-space chars.
    $total_chars = strlen( preg_replace( '/\s+/', '', $ar ) );
    $ar_chars    = preg_match_all( '/[\x{0600}-\x{06FF}]/u', $ar );
    $coverage    = $total_chars > 0 ? ( $ar_chars / $total_chars ) : 0;

    // If coverage is super low (< 15% Arabic), it means dictionary
    // didn't match much — skip rather than save garbage.
    if ( $coverage < 0.15 ) {
        $low_coverage++;
        continue;
    }

    update_post_meta( $r->ID, '_ng_ar_title', $ar );
    update_post_meta( $r->ID, '_ng_ar_title_source', 'auto-generated' );
    $generated++;

    if ( $generated <= 15 ) {
        WP_CLI::log( sprintf( '  #%-4d  %-50s  ->  %s', $r->ID, mb_substr( $en, 0, 50 ), $ar ) );
    }
}

if ( $generated > 15 ) {
    WP_CLI::log( '  ... +' . ( $generated - 15 ) . ' more' );
}

WP_CLI::log( "Generated:           $generated" );
WP_CLI::log( "Skipped (low cov):   $low_coverage" );

// Final coverage
$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='product' AND post_status IN ('publish','draft')" );
$with  = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key='_ng_ar_title' AND meta_value!=''" );
WP_CLI::log( "Total AR-title coverage: $with / $total (" . round( $with / max( $total, 1 ) * 100, 1 ) . '%)' );
