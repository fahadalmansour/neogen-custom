# NeoGen Store SEO Critical Fixes (WordPress/WooCommerce)

This document outlines critical steps to diagnose and resolve indexability and trust issues for the NeoGen Store, which is identified as a WordPress/WooCommerce platform.

## 1. Diagnose and Resolve Indexability Issues

The primary reason search engines might not be indexing neogen.store is often due to misconfiguration. Follow these steps:

### A. Check Live `robots.txt`

1.  **Access:** Open your web browser and navigate to `https://neogen.store/robots.txt`.
2.  **Verify Content:**
    *   **Missing or `Disallow: /`:** If the file is missing, or if it contains `Disallow: /` (which blocks all crawlers), this is a critical issue.
        *   **Fix:** If you use an SEO plugin (like Yoast SEO or Rank Math), generate/update the `robots.txt` through its settings to ensure it allows crawling. If you don't use a plugin, you'll need to create a `robots.txt` file in your WordPress root directory (via FTP/SFTP or your hosting file manager) with appropriate rules (e.g., `User-agent: * Allow: /`).
    *   **Correct Configuration Example:**
        ```
        User-agent: *
        Disallow: /wp-admin/
        Allow: /wp-admin/admin-ajax.php

        Sitemap: https://neogen.store/sitemap.xml
        ```
3.  **Note:** WordPress can sometimes generate a virtual `robots.txt` if no physical file exists. The key is what Googlebot *sees*.

### B. WordPress Search Engine Visibility Setting

1.  **Access:** Log into your WordPress Admin Dashboard.
2.  **Navigate:** Go to `Settings` > `Reading`.
3.  **Check Box:** Look for the option "Search Engine Visibility" and ensure the checkbox next to "Discourage search engines from indexing this site" is **UNCHECKED**. If it's checked, uncheck it and save changes. This is a very common cause of de-indexing.

### C. Inspect `noindex` Meta Tags

1.  **Access:** Visit your live homepage (`https://neogen.store/`) and a few important category/product pages in your browser.
2.  **View Source:** Right-click anywhere on the page and select "View Page Source" (or similar, depending on your browser).
3.  **Search:** Use your browser's search function (Ctrl+F or Cmd+F) to look for `<meta name="robots" content="noindex,nofollow">` or `<meta name="robots" content="noindex">`.
4.  **Identify Cause:**
    *   If found, this tag explicitly tells search engines NOT to index the page.
    *   **Fix:** This is often caused by an SEO plugin (Yoast, Rank Math), theme settings, or custom code. You'll need to navigate to the respective plugin/theme SEO settings for that specific page/post type and ensure "Allow search engines to show this Post in search results?" or similar options are enabled.

### D. Google Search Console (GSC) & Bing Webmaster Tools (BWT)

1.  **Verify Ownership:** Ensure your `neogen.store` property is verified in both GSC and BWT.
2.  **URL Inspection:** For the homepage and key pages, use the "URL Inspection" tool in GSC and BWT.
    *   This tool will show you how Google/Bing sees the page, including if it's indexed, crawled, any errors, and the canonical URL.
    *   Pay close attention to "Crawled as:" and "Indexing allowed?" status.

---

## Next Steps

Once you've performed these diagnostic steps, please provide the following information:

1.  The exact content of your live `https://neogen.store/robots.txt` file.
2.  Confirmation of the status of the "Discourage search engines from indexing this site" checkbox in WordPress Settings > Reading.
3.  Whether you found any `<meta name="robots" content="noindex,nofollow">` tags on your homepage or key category/product pages and, if so, which plugin or theme setting seems to be causing it.

This information is crucial for formulating precise fixes.
