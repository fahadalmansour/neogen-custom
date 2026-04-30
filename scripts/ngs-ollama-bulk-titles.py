#!/usr/bin/env python3
"""
v1.37.3 — bulk AR title backfill via local Ollama (qwen3:8b).

Companion to ngs-ollama-bulk-descriptions.py. Same REST surface, same
auth, but a much shorter prompt + payload for product title translation.

~3-6s/product. 85 missing → ~7-9 min.

Usage:
  ./ngs-ollama-bulk-titles.py --limit 5 --dry-run
  ./ngs-ollama-bulk-titles.py --limit 999
"""

from __future__ import annotations
import argparse, json, re, time
from datetime import datetime
from urllib import request, error
import base64

WP_BASE = 'https://neogen.store/wp-json/neogen/v1'
WP_USER = 'n8n-bot'
WP_PASS = 'woi18IhzUpiGVpWWr2ThpcNH'
OLLAMA_URL = 'http://192.168.8.106:11434/api/generate'
MODEL = 'qwen2.5:7b'  # qwen3 had reasoning-mode token-budget issues

PROMPT = """مهمتك ترجمة عنوان منتج إلكتروني إلى العربية الفصحى.
القواعد:
- اترك أسماء العلامات التجارية وأرقام الموديل بالحروف اللاتينية (مثل: NVIDIA RTX 5090، Logitech MX).
- لا ترجم وحدات القياس التقنية (GHz, GB, MHz, dBi, mAh, GbE).
- اكتب سطرًا واحدًا فقط، بدون علامات اقتباس، بدون مقدمة أو شرح.

العنوان الإنجليزي: {title}

العنوان العربي:"""


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


def ollama(prompt):
    body = {'model': MODEL, 'prompt': prompt, 'stream': False,
            'options': {'temperature': 0.15, 'num_predict': 80, 'stop': ['\n\n']}}
    req = request.Request(OLLAMA_URL, data=json.dumps(body).encode(), method='POST',
                          headers={'Content-Type': 'application/json'})
    with request.urlopen(req, timeout=60) as r:
        return json.loads(r.read())['response'].strip()


def clean(text):
    text = re.sub(r'<think>.*?</think>', '', text, flags=re.S).strip()
    text = re.sub(r'^(translation\s*:|sure[,!]?\s*[a-z\s]*:|here\s+is[^:]*:|العنوان\s+العربي\s*:?)\s*', '', text, flags=re.I)
    text = text.split('\n')[0].strip(' \t\n\r"\'`،,،.')
    return text


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument('--limit', type=int, default=5)
    ap.add_argument('--dry-run', action='store_true')
    ap.add_argument('--log')
    args = ap.parse_args()

    log_fh = open(args.log, 'a') if args.log else None
    def log(m):
        line = f'[{datetime.now().isoformat(timespec="seconds")}] {m}'
        print(line, flush=True)
        if log_fh: log_fh.write(line+'\n'); log_fh.flush()

    log(f'titles: model={MODEL} limit={args.limit} dry_run={args.dry_run}')
    applied = 0
    while applied < args.limit:
        page = http_get(f'{WP_BASE}/products?missing=ar_title&limit=20')
        items = page.get('items', [])
        if not items:
            log('queue empty — done'); break
        for it in items:
            if applied >= args.limit: break
            pid = it['id']; title = it['title_en']
            t0 = time.time()
            try:
                raw = ollama(PROMPT.format(title=title))
            except Exception as e:
                log(f'#{pid} OLLAMA_ERR {e}'); continue
            ar = clean(raw)
            ar_chars = len(re.findall(r'[؀-ۿ]', ar))
            if ar_chars < 3:
                log(f'#{pid} VALIDATE_ERR no AR  raw={raw[:100]!r}'); continue
            elapsed = time.time()-t0
            if args.dry_run:
                log(f'#{pid} DRY ({elapsed:.1f}s) {title[:40]} → {ar}'); continue
            resp = http_post(f'{WP_BASE}/products/{pid}/ar-title',
                {'ar_title': ar, 'source': f'ollama-{MODEL}-bulk'})
            if 'error' in resp:
                log(f'#{pid} POST_ERR {resp["error"]} {resp.get("body","")[:100]}'); continue
            log(f'#{pid} APPLIED ({elapsed:.1f}s) → {ar}')
            applied += 1
        if args.dry_run: break
        time.sleep(0.5)

    log(f'finished: applied={applied}')
    if log_fh: log_fh.close()


if __name__ == '__main__':
    main()
