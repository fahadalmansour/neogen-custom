#!/usr/bin/env python3
"""
v1.37.5 — re-translate AR descriptions from a clean WC CSV export.

The CSV at /Users/fahadalmansour/ngs/wc-export.csv carries the canonical
English Name + Description per SKU (much richer than the legacy WP
post_content for many products — marketing-grade copy with bullet
points and spec tables already formatted).

For each row with a non-empty English Description AND an existing WC
product (matched by SKU), this script:
  1. Sends Name + Description through Ollama qwen2.5:14b with strict
     no-script-bleed prompt.
  2. Validates output (Arabic ≥ 60 chars, no Cyrillic/CJK/Hangul/etc.).
  3. POSTs cleaned AR HTML back to /wp-json/neogen/v1/products/<id>/ar-description
     with source = 'ollama-qwen2.5:14b-csv-v1'.

The v1.37.4 server-side validator catches script bleed at the door, so
even if the model leaks Cyrillic/CJK we get HTTP 422 instead of saving
garbage. Manual edits are also locked server-side.

Usage:
  ./ngs-ar-from-csv.py --limit 5 --dry-run
  ./ngs-ar-from-csv.py --limit 999 --log /tmp/ngs-pricer/csv-regen.log
  ./ngs-ar-from-csv.py --limit 999 --skus NG-ENT-001,NG-ACC-008
"""

from __future__ import annotations
import argparse, base64, csv, json, re, subprocess, sys, time
from datetime import datetime
from urllib import request, error

WP_BASE = 'https://neogen.store/wp-json/neogen/v1'
WP_USER = 'n8n-bot'
WP_PASS = 'woi18IhzUpiGVpWWr2ThpcNH'
OLLAMA_URL = 'http://192.168.8.106:11434/api/generate'
MODEL = 'qwen2.5:14b'
SOURCE_TAG = f'ollama-{MODEL}-csv-v1'

FORBIDDEN = re.compile(r'[Ѐ-ӿͰ-Ͽ一-鿿぀-ゟ゠-ヿ가-힯ऀ-ॿ]')

PROMPT = """مهمتك: ترجمة وصف منتج إلكتروني من الإنجليزية إلى العربية الفصحى الحديثة.

قواعد صارمة:
- ممنوع تمامًا استخدام أي حرف من السيريلية أو اليونانية أو الصينية أو اليابانية أو الكورية أو الديفاناغارية. أعد الكتابة من الصفر إذا استخدمت أيًا منها.
- اترك أسماء العلامات التجارية وأرقام الموديل بالحروف اللاتينية (مثل: Dell PowerEdge R730, Synology DS1621+, NVIDIA RTX 5090).
- اترك مسافة واضحة قبل وبعد كل كلمة لاتينية. لا تلصق حرفًا إنجليزيًا بحرف عربي أبدًا.
- لا تترجم وحدات القياس التقنية (GHz, GB, dBi, MHz, mAh, GbE, RAM, RPM).
- استخدم HTML نظيف: <p>, <strong>, <ul>/<li>, <table>/<tr>/<th>/<td>.
- الهيكل: فقرة افتتاحية واحدة (40-60 كلمة)، ثم قسم "<strong>لماذا هذا المنتج؟</strong>" مع 4-6 نقاط في <ul><li>، ثم قسم "<strong>المواصفات التقنية:</strong>" بصيغة <table>.
- لا تكتب مقدمة أو شرح خارج الـ HTML. أعد فقط HTML العربي.

اسم المنتج: {name}

النص الإنجليزي:
{body}

أعد فقط HTML العربي."""


def http_get_id_by_sku(sku):
    """Look up product ID via SSH wp-cli (one query per call)."""
    r = subprocess.run([
        'ssh', '-p', '21098', 'fsalmansour@162.254.39.146',
        f"cd /home/fsalmansour/neogen.store && wp post list --post_type=product --meta_key=_sku --meta_value={sku} --field=ID --skip-plugins=litespeed-cache 2>/dev/null"
    ], capture_output=True, text=True, timeout=30)
    out = r.stdout.strip()
    return int(out) if out.isdigit() else None


def ollama(prompt, num_predict=900, timeout=240):
    body = {
        'model': MODEL, 'prompt': prompt, 'stream': False,
        'options': {'temperature': 0.15, 'top_p': 0.9, 'num_predict': num_predict},
    }
    req = request.Request(OLLAMA_URL, data=json.dumps(body).encode(), method='POST',
        headers={'Content-Type': 'application/json'})
    with request.urlopen(req, timeout=timeout) as r:
        return json.loads(r.read())['response'].strip()


def clean_ar(text):
    text = re.sub(r'<think>.*?</think>', '', text, flags=re.S).strip()
    text = re.sub(r'^(translation\s*:|sure[,!]?\s*[a-z\s]*:|here\s+is[^:]*:|نص\s+عربي\s*:?|الترجمة\s*:?)\s*', '', text, flags=re.I)
    text = re.sub(r'^```(?:html)?\s*\n?', '', text)
    text = re.sub(r'\n?```\s*$', '', text)
    m = re.search(r'<body[^>]*>(.*?)</body>', text, flags=re.S | re.I)
    if m: text = m.group(1).strip()
    text = re.sub(r'<!DOCTYPE[^>]*>', '', text, flags=re.I)
    text = re.sub(r'</?(?:html|head|meta|title|body)[^>]*>', '', text, flags=re.I)
    return text.strip(' \t\n\r"\'`')


def validate(text):
    if not text or len(text) < 50:
        return f'too short ({len(text)})'
    if FORBIDDEN.search(text):
        m = FORBIDDEN.search(text)
        return f'forbidden script {m.group()!r} at offset {m.start()}'
    ar_chars = len(re.findall(r'[؀-ۿ]', text))
    if ar_chars < 60:
        return f'too few AR chars ({ar_chars})'
    latin_words = len(re.findall(r'[A-Za-z]{4,}', text))
    if latin_words > ar_chars / 4:
        return f'too much Latin prose ({latin_words} long-words / {ar_chars} AR)'
    return None


def http_post(url, body):
    token = base64.b64encode(f'{WP_USER}:{WP_PASS}'.encode()).decode()
    req = request.Request(url, data=json.dumps(body).encode(), method='POST',
        headers={'Authorization': f'Basic {token}', 'Content-Type': 'application/json'})
    try:
        with request.urlopen(req, timeout=30) as r:
            return json.loads(r.read())
    except error.HTTPError as e:
        return {'error': f'HTTP {e.code}', 'body': e.read().decode()[:300]}


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument('--csv', default='/Users/fahadalmansour/ngs/wc-export.csv')
    ap.add_argument('--limit', type=int, default=5)
    ap.add_argument('--dry-run', action='store_true')
    ap.add_argument('--skus', help='comma-separated SKUs to limit to')
    ap.add_argument('--log')
    ap.add_argument('--skip-existing', action='store_true',
                    help='Skip products whose ar-description was last set with this same source tag')
    args = ap.parse_args()

    log_fh = open(args.log, 'a') if args.log else None
    def log(m):
        line = f'[{datetime.now().isoformat(timespec="seconds")}] {m}'
        print(line, flush=True)
        if log_fh:
            log_fh.write(line + '\n'); log_fh.flush()

    sku_filter = set(s.strip() for s in args.skus.split(',')) if args.skus else None

    rows = list(csv.DictReader(open(args.csv, encoding='utf-8-sig')))
    candidates = []
    for r in rows:
        sku = (r.get('SKU') or '').strip()
        name = (r.get('Name') or '').strip()
        # CSV uses literal \n in cells — convert to actual newlines.
        desc = (r.get('Description') or '').strip().replace('\\n', '\n')
        if not (sku and name and desc):
            continue
        if sku_filter and sku not in sku_filter:
            continue
        candidates.append({'sku': sku, 'name': name, 'desc': desc})

    log(f'csv-regen: {len(candidates)} candidates with descriptions, processing up to {args.limit}')

    applied = 0
    needs_human = []
    for cand in candidates[:args.limit]:
        pid = http_get_id_by_sku(cand['sku'])
        if not pid:
            log(f"{cand['sku']} NO_PID")
            continue
        prompt = PROMPT.format(name=cand['name'], body=cand['desc'][:2500])
        success = False
        for attempt in range(3):
            t0 = time.time()
            try:
                raw = ollama(prompt)
            except Exception as e:
                log(f"#{pid} {cand['sku']} attempt={attempt+1} OLLAMA_ERR {e}")
                time.sleep(3); continue
            cleaned = clean_ar(raw)
            err = validate(cleaned)
            if err:
                log(f"#{pid} {cand['sku']} attempt={attempt+1} REJECT {err} sample={cleaned[:80]!r}")
                continue
            elapsed = time.time() - t0
            if args.dry_run:
                log(f"#{pid} {cand['sku']} DRY ({elapsed:.1f}s) {cleaned[:120]}...")
                success = True; break
            resp = http_post(f"{WP_BASE}/products/{pid}/ar-description",
                             {'ar_description': cleaned, 'source': SOURCE_TAG})
            if 'error' in resp:
                log(f"#{pid} {cand['sku']} POST_ERR {resp['error']} {resp.get('body','')[:120]}")
                continue
            log(f"#{pid} {cand['sku']} APPLIED ({elapsed:.1f}s) {len(cleaned)}c {cand['name'][:40]}")
            applied += 1
            success = True; break
        if not success:
            needs_human.append({'sku': cand['sku'], 'pid': pid, 'name': cand['name']})
        time.sleep(0.5)

    log(f'finished: applied={applied} needs_human={len(needs_human)}')
    if needs_human:
        with open('/tmp/ngs-pricer/csv-regen-needs-human.csv', 'a', newline='') as f:
            w = csv.DictWriter(f, fieldnames=['sku', 'pid', 'name'])
            if f.tell() == 0:
                w.writeheader()
            for r in needs_human:
                w.writerow(r)
    if log_fh:
        log_fh.close()


if __name__ == '__main__':
    main()
