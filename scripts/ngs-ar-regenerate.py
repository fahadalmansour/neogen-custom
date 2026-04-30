#!/usr/bin/env python3
"""
v1.37.4b — strict-prompt regenerator for products flagged by the audit.

Reads /tmp/ngs-pricer/ar-audit.json. For every flagged title or description
(skipping source=manual), regenerates via qwen2.5:14b with a stricter
prompt + stricter validator. Up to 3 retries per item before giving up.

Items that still fail after 3 tries are emitted to
/tmp/ngs-pricer/ar-needs-human.csv for admin review.

Usage:
  ./ngs-ar-regenerate.py --kind titles --limit 5 --dry-run
  ./ngs-ar-regenerate.py --kind titles --limit 999
  ./ngs-ar-regenerate.py --kind descriptions --limit 999 --log /tmp/ngs-pricer/regen.log
"""

import argparse, base64, csv, json, re, sys, time
from datetime import datetime
from urllib import request, error

WP_BASE = 'https://neogen.store/wp-json/neogen/v1'
WP_USER = 'n8n-bot'
WP_PASS = 'woi18IhzUpiGVpWWr2ThpcNH'
OLLAMA_URL = 'http://192.168.8.106:11434/api/generate'
MODEL = 'qwen2.5:14b'

FORBIDDEN = re.compile(r'[Ѐ-ӿͰ-Ͽ一-鿿぀-ゟ゠-ヿ가-힯ऀ-ॿ]')

TITLE_PROMPT = """مهمتك: ترجمة عنوان منتج إلكتروني إلى العربية الفصحى الحديثة.

قواعد صارمة:
- لا تستخدم أي حرف من السيريلية أو اليونانية أو الصينية أو اليابانية أو الكورية أو الديفاناغارية. ممنوع تمامًا.
- اترك أسماء العلامات التجارية وأرقام الموديل بالحروف اللاتينية الإنجليزية فقط (مثل: NVIDIA RTX 5090، Logitech G Pro، Synology DS1621+).
- اترك مسافة واضحة بين الكلمات اللاتينية والكلمات العربية. لا تلصق حرفًا إنجليزيًا بحرف عربي أبدًا.
- لا تترجم وحدات القياس التقنية (GHz, GB, MHz, dBi, mAh, GbE).
- اكتب سطرًا واحدًا فقط. لا علامات اقتباس. لا مقدمة.

العنوان الإنجليزي: {title}

العنوان العربي:"""

DESC_PROMPT = """مهمتك: كتابة وصف منتج بالعربية الفصحى الحديثة بتنسيق HTML.

قواعد صارمة:
- ممنوع تمامًا استخدام أي حرف من السيريلية أو اليونانية أو الصينية أو اليابانية أو الكورية. أعد الكتابة من الصفر إذا كنت تستخدم أيًا منها.
- اترك أسماء العلامات التجارية وأرقام الموديل بالحروف اللاتينية الإنجليزية فقط، مع مسافة واضحة قبل وبعد كل كلمة لاتينية.
- لا تلصق حرفًا إنجليزيًا بحرف عربي أبدًا. مثال صحيح: "كرت Gigabyte AORUS RTX 5090". مثال خاطئ: "كرتGigabyte".
- الهيكل: فقرة افتتاحية واحدة (40-60 كلمة)، ثم قسم "<strong>لماذا هذا المنتج؟</strong>" مع 4-6 نقاط في <ul><li>، ثم قسم "<strong>المواصفات التقنية:</strong>" بصيغة <table>.
- لا تترجم وحدات القياس التقنية (GHz, GB, dBi).

اسم المنتج: {title}
النص الإنجليزي الأصلي: {body}

أعد فقط HTML العربي."""


def http_get(url):
    token = base64.b64encode(f'{WP_USER}:{WP_PASS}'.encode()).decode()
    req = request.Request(url, headers={'Authorization': f'Basic {token}'})
    with request.urlopen(req, timeout=30) as r:
        return json.loads(r.read())


def http_post(url, body):
    token = base64.b64encode(f'{WP_USER}:{WP_PASS}'.encode()).decode()
    req = request.Request(url, data=json.dumps(body).encode(), method='POST',
        headers={'Authorization': f'Basic {token}', 'Content-Type': 'application/json'})
    try:
        with request.urlopen(req, timeout=30) as r:
            return json.loads(r.read())
    except error.HTTPError as e:
        return {'error': f'HTTP {e.code}', 'body': e.read().decode()[:300]}


def ollama(prompt, num_predict, timeout):
    body = {'model': MODEL, 'prompt': prompt, 'stream': False,
            'options': {'temperature': 0.15, 'top_p': 0.9, 'num_predict': num_predict}}
    req = request.Request(OLLAMA_URL, data=json.dumps(body).encode(), method='POST',
                          headers={'Content-Type': 'application/json'})
    with request.urlopen(req, timeout=timeout) as r:
        return json.loads(r.read())['response'].strip()


def clean(text, full):
    text = re.sub(r'<think>.*?</think>', '', text, flags=re.S).strip()
    text = re.sub(r'^(translation\s*:|sure[,!]?\s*[a-z\s]*:|here\s+is[^:]*:|العنوان\s+العربي\s*:?)\s*', '', text, flags=re.I)
    text = re.sub(r'^```(?:html)?\s*\n?', '', text)
    text = re.sub(r'\n?```\s*$', '', text)
    if full:
        m = re.search(r'<body[^>]*>(.*?)</body>', text, flags=re.S | re.I)
        if m: text = m.group(1).strip()
        text = re.sub(r'<!DOCTYPE[^>]*>', '', text, flags=re.I)
        text = re.sub(r'</?(?:html|head|meta|title|body)[^>]*>', '', text, flags=re.I)
    else:
        text = text.split('\n')[0]
    return text.strip(' \t\n\r"\'`،,،.')


def validate(text, kind):
    if not text: return 'empty'
    if FORBIDDEN.search(text):
        m = FORBIDDEN.search(text)
        return f'forbidden script at offset {m.start()}: {m.group()!r}'
    ar_chars = len(re.findall(r'[؀-ۿ]', text))
    if kind == 'title' and ar_chars < 3: return f'too few AR ({ar_chars})'
    if kind == 'description' and ar_chars < 60: return f'too few AR ({ar_chars})'
    return None


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument('--kind', choices=['titles','descriptions'], required=True)
    ap.add_argument('--limit', type=int, default=999)
    ap.add_argument('--dry-run', action='store_true')
    ap.add_argument('--audit', default='/tmp/ngs-pricer/ar-audit.json')
    ap.add_argument('--log')
    args = ap.parse_args()

    log_fh = open(args.log, 'a') if args.log else None
    def log(m):
        line = f'[{datetime.now().isoformat(timespec="seconds")}] {m}'
        print(line, flush=True)
        if log_fh: log_fh.write(line+'\n'); log_fh.flush()

    audit = json.load(open(args.audit))
    flagged_key = 'flagged_titles' if args.kind == 'titles' else 'flagged_descriptions'
    items = audit[flagged_key]
    items = [it for it in items if it.get('source') != 'manual']
    log(f'regenerate {args.kind}: {len(items)} flagged (after manual filter), processing up to {args.limit}')

    needs_human = []
    applied = 0; skipped = 0
    for it in items[:args.limit]:
        pid = it['pid']
        # Re-fetch product info to get title (and body for desc kind)
        prod = http_get(f'{WP_BASE}/products?missing=ar_description&limit=1') if args.kind == 'descriptions' else None
        # Actually simpler: query the WP REST product endpoint for title/body. Using DB-cached data from audit's pid lookup is enough — but we don't have it. Pull a quick fetch.
        # We instead piggyback: use the audit's stored AR (we only need EN title/body for the prompt).
        # Workaround: look up via a tiny SSH wp-cli call. Not ideal but acceptable.
        import subprocess
        r = subprocess.run(['ssh','-p','21098','fsalmansour@162.254.39.146',
            f"cd /home/fsalmansour/neogen.store && wp post get {pid} --field=post_title --skip-plugins=litespeed-cache 2>/dev/null"],
            capture_output=True, text=True, timeout=30)
        title_en = r.stdout.strip()
        if not title_en:
            log(f'#{pid} CANT_FETCH_TITLE  skip')
            skipped += 1
            continue
        body_en = ''
        if args.kind == 'descriptions':
            r2 = subprocess.run(['ssh','-p','21098','fsalmansour@162.254.39.146',
                f"cd /home/fsalmansour/neogen.store && wp post get {pid} --field=post_content --skip-plugins=litespeed-cache 2>/dev/null"],
                capture_output=True, text=True, timeout=30)
            body_en = (r2.stdout or '')[:1500]

        prompt = TITLE_PROMPT.format(title=title_en) if args.kind == 'titles' else DESC_PROMPT.format(title=title_en, body=body_en)
        kind_param = 'title' if args.kind == 'titles' else 'description'
        success = False
        for attempt in range(3):
            try:
                t0 = time.time()
                raw = ollama(prompt, num_predict=120 if args.kind=='titles' else 900, timeout=90 if args.kind=='titles' else 240)
                cleaned = clean(raw, full=(args.kind=='descriptions'))
                err = validate(cleaned, kind_param)
                if err:
                    log(f'#{pid} attempt={attempt+1} REJECT  {err}  raw={cleaned[:80]!r}')
                    continue
                if args.dry_run:
                    log(f'#{pid} DRY ({time.time()-t0:.1f}s) {title_en[:40]} -> {cleaned[:120]}')
                    success = True
                    break
                endpoint = f'{WP_BASE}/products/{pid}/ar-{kind_param}'
                resp = http_post(endpoint, {f'ar_{kind_param}': cleaned, 'source': f'ollama-{MODEL}-regen-v1'})
                if 'error' in resp:
                    log(f'#{pid} POST_ERR {resp["error"]} {resp.get("body","")[:100]}')
                    continue
                log(f'#{pid} APPLIED ({time.time()-t0:.1f}s) {title_en[:40]}')
                applied += 1
                success = True
                break
            except Exception as e:
                log(f'#{pid} attempt={attempt+1} OLLAMA_ERR {e}')
                time.sleep(2)
        if not success:
            needs_human.append({'pid': pid, 'title_en': title_en, 'last_issues': it.get('issues', [])})

    if needs_human and not args.dry_run:
        with open('/tmp/ngs-pricer/ar-needs-human.csv', 'a', newline='') as f:
            w = csv.DictWriter(f, fieldnames=['pid','kind','title_en','last_issues'])
            if f.tell() == 0: w.writeheader()
            for r in needs_human:
                r['kind'] = args.kind
                r['last_issues'] = ','.join(r['last_issues']) if isinstance(r.get('last_issues'),list) else str(r.get('last_issues',''))
                w.writerow(r)

    log(f'finished: applied={applied}  skipped={skipped}  needs_human={len(needs_human)}')
    if log_fh: log_fh.close()


if __name__ == '__main__':
    main()
