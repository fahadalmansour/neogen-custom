#!/usr/bin/env python3
"""
v1.37.2 — bulk AR description backfill via local Ollama (qwen3:8b).

Runs from a LAN box. Pulls products missing _ng_ar_description via the
NeoGen REST surface, generates a 60–120 word Arabic description (or full
spec body when --full) using Ollama at 192.168.8.106:11434, validates,
posts back.

Usage:
  # dry-run (no writes), preview 1 product
  ./ngs-ollama-bulk-descriptions.py --limit 1 --dry-run

  # real run, 5 products, sequential
  ./ngs-ollama-bulk-descriptions.py --limit 5

  # overnight full sweep, 3 workers
  ./ngs-ollama-bulk-descriptions.py --limit 999 --workers 3 \\
      --log /tmp/ngs-pricer/descriptions.log

  # generate FULL long-form descriptions including spec tables
  ./ngs-ollama-bulk-descriptions.py --limit 5 --full

Truth-rule guards (server-side, in mu-plugins/neogen-rest-content.php):
  - source must be 'manual' or 'ollama-*'
  - output must contain Arabic codepoints (U+0600-06FF)
  - manual edits are locked (HTTP 409 on overwrite attempt)
  - pre-write snapshot stamped to _ng_ar_description_pre_n8n
"""

from __future__ import annotations
import argparse
import concurrent.futures as cf
import json
import re
import sys
import time
from datetime import datetime
from typing import Optional
from urllib import request, parse, error

# --- config defaults ---
WP_BASE = 'https://neogen.store/wp-json/neogen/v1'
WP_USER = 'n8n-bot'
WP_PASS = 'woi18IhzUpiGVpWWr2ThpcNH'  # TODO: read from ~/.config/neogen/.env
OLLAMA_URL = 'http://192.168.8.106:11434/api/generate'
MODEL = 'qwen2.5:7b'  # qwen3:8b reasoning ate the budget; coder variant emitted HTML boilerplate; this is the right tool.

# --- prompt templates ---
SHORT_PROMPT = """/no_think
أنت تكتب وصفًا تسويقيًا قصيرًا لمنتج إلكتروني في متجر سعودي.
المهمة: اكتب فقرة عربية واحدة (60-100 كلمة) بأسلوب احترافي محايد، بدون عناوين أو تعداد نقطي، بدون رموز تعبيرية.
اترك أسماء العلامات التجارية وأرقام الموديل بالحروف اللاتينية.

اسم المنتج: {title}
ملخص المرجع (الإنجليزية): {body}

اكتب الفقرة العربية فقط دون أي مقدمات أو شروحات."""

FULL_PROMPT = """/no_think
أنت تكتب وصفًا تفصيليًا لمنتج إلكتروني في متجر سعودي.
المهمة: ترجم وأعد كتابة الوصف الكامل بالعربية الفصحى الحديثة بتنسيق HTML نظيف.
الهيكل المطلوب:
  1. فقرة افتتاحية واحدة (40-60 كلمة)
  2. قسم "<strong>لماذا هذا المنتج؟</strong>" مع 4-6 نقاط في <ul><li>
  3. قسم "<strong>المواصفات التقنية:</strong>" بصيغة جدول HTML <table>
اترك أسماء العلامات التجارية وأرقام الموديل بالحروف اللاتينية. لا ترجم وحدات القياس التقنية مثل GHz, GB, dBi, MHz. حافظ على الأرقام كما هي.

اسم المنتج: {title}

النص الإنجليزي الأصلي:
{body}

أعد فقط HTML العربي دون مقدمات أو ملاحظات."""


def http_get(url: str) -> dict:
    auth = parse.quote(f'{WP_USER}:{WP_PASS}')
    req = request.Request(url, headers={'Accept': 'application/json'})
    import base64
    token = base64.b64encode(f'{WP_USER}:{WP_PASS}'.encode()).decode()
    req.add_header('Authorization', f'Basic {token}')
    with request.urlopen(req, timeout=30) as r:
        return json.loads(r.read())


def http_post(url: str, body: dict) -> dict:
    import base64
    token = base64.b64encode(f'{WP_USER}:{WP_PASS}'.encode()).decode()
    req = request.Request(
        url, data=json.dumps(body).encode(),
        method='POST',
        headers={
            'Authorization': f'Basic {token}',
            'Content-Type': 'application/json',
            'Accept': 'application/json',
        }
    )
    try:
        with request.urlopen(req, timeout=30) as r:
            return json.loads(r.read())
    except error.HTTPError as e:
        return {'error': f'HTTP {e.code}', 'body': e.read().decode()[:300]}


def ollama_generate(prompt: str, num_predict: int = 350, timeout: int = 180) -> str:
    payload = {
        'model': MODEL,
        'prompt': prompt,
        'stream': False,
        'options': {
            'temperature': 0.25,
            'top_p': 0.9,
            'num_predict': num_predict,
            # qwen3 reasoning OFF — prevents <think> from eating the token budget
            # on harder prompts which was causing empty .response output.
            'think': False,
        },
    }
    req = request.Request(
        OLLAMA_URL, data=json.dumps(payload).encode(),
        method='POST',
        headers={'Content-Type': 'application/json'}
    )
    with request.urlopen(req, timeout=timeout) as r:
        return json.loads(r.read())['response'].strip()


def validate_ar(text: str, full: bool) -> Optional[str]:
    if not text:
        return 'empty response'
    # Strip qwen reasoning tags + LLM preambles
    text = re.sub(r'<think>.*?</think>', '', text, flags=re.S).strip()
    text = re.sub(r'^(translation\s*:|sure[,!]?\s*[a-z\s]*:|here\s+is[^:]*:)\s*', '', text, flags=re.I)
    # Strip code-fence wrappers like ```html ... ``` that some models add.
    text = re.sub(r'^```(?:html)?\s*\n?', '', text)
    text = re.sub(r'\n?```\s*$', '', text)
    text = text.strip(' \t\n\r"\'`')
    ar_chars = len(re.findall(r'[؀-ۿ]', text))
    # For ratio, count Arabic vs (Arabic + Latin letters) — ignore HTML tags,
    # numbers, punctuation, brand names. This rejects English content but
    # tolerates HTML markup, model numbers, units (GHz/GB), and brand names.
    latin_chars = len(re.findall(r'[A-Za-z]{4,}', text))  # words of 4+ Latin letters
    min_ar = 60 if full else 30
    if ar_chars < min_ar:
        return f'too few Arabic chars ({ar_chars} < {min_ar})'
    # If there are 4-letter+ Latin words, they should be brand names. Reject
    # only when the count is excessive vs Arabic.
    if latin_chars > ar_chars / 4:
        return f'too much Latin prose ({latin_chars} long-Latin-words vs {ar_chars} AR chars)'
    return None


def clean_ar(text: str) -> str:
    text = re.sub(r'<think>.*?</think>', '', text, flags=re.S).strip()
    text = re.sub(r'^(translation\s*:|sure[,!]?\s*[a-z\s]*:|here\s+is[^:]*:)\s*', '', text, flags=re.I)
    text = re.sub(r'^```(?:html)?\s*\n?', '', text)
    text = re.sub(r'\n?```\s*$', '', text)
    # Some models emit full HTML doctypes — extract just the <body> content.
    m = re.search(r'<body[^>]*>(.*?)</body>', text, flags=re.S | re.I)
    if m:
        text = m.group(1).strip()
    # Strip <!DOCTYPE>, <html>, <head>, <meta>, <title> if no body wrapper.
    text = re.sub(r'<!DOCTYPE[^>]*>', '', text, flags=re.I)
    text = re.sub(r'</?(?:html|head|meta|title|body)[^>]*>', '', text, flags=re.I)
    return text.strip(' \t\n\r"\'`')


def process_one(item: dict, full: bool, dry_run: bool, log) -> dict:
    pid = item['id']
    title = item['title_en']
    body = (item.get('body_en') or '')[:2000 if full else 800]
    prompt = (FULL_PROMPT if full else SHORT_PROMPT).format(title=title, body=body)
    started = time.time()
    try:
        raw = ollama_generate(prompt, num_predict=900 if full else 350, timeout=240 if full else 120)
    except Exception as e:
        log(f'#{pid} OLLAMA_ERR {e}')
        return {'id': pid, 'status': 'ollama_err', 'err': str(e)[:120]}
    elapsed = time.time() - started
    err = validate_ar(raw, full)
    if err:
        log(f'#{pid} VALIDATE_ERR {err}  raw={raw[:80]!r}')
        return {'id': pid, 'status': 'validate_err', 'err': err, 'raw_sample': raw[:120]}
    cleaned = clean_ar(raw)
    if dry_run:
        log(f'#{pid} DRY_RUN  ({elapsed:.1f}s)  preview:\n  {cleaned[:200]}{"..." if len(cleaned)>200 else ""}')
        return {'id': pid, 'status': 'dry_run', 'preview': cleaned[:200]}
    resp = http_post(
        f'{WP_BASE}/products/{pid}/ar-description',
        {'ar_description': cleaned, 'source': f'ollama-{MODEL}-bulk' + ('-full' if full else '')}
    )
    if 'error' in resp:
        log(f'#{pid} POST_ERR  {resp["error"]}  body={resp.get("body", "")[:120]}')
        return {'id': pid, 'status': 'post_err', 'err': resp['error']}
    log(f'#{pid} APPLIED  ({elapsed:.1f}s)  {len(cleaned)}c  {title[:50]}')
    return {'id': pid, 'status': 'applied', 'len': len(cleaned)}


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument('--limit', type=int, default=5)
    ap.add_argument('--workers', type=int, default=1)
    ap.add_argument('--full', action='store_true', help='Generate long-form HTML body, not short paragraph')
    ap.add_argument('--dry-run', action='store_true')
    ap.add_argument('--log', default=None, help='Tee output to a logfile too')
    ap.add_argument('--batch-size', type=int, default=20, help='REST page size per fetch')
    args = ap.parse_args()

    log_fh = open(args.log, 'a') if args.log else None
    def log(msg):
        line = f'[{datetime.now().isoformat(timespec="seconds")}] {msg}'
        print(line, flush=True)
        if log_fh:
            log_fh.write(line + '\n')
            log_fh.flush()

    log(f'config: model={MODEL} limit={args.limit} workers={args.workers} full={args.full} dry_run={args.dry_run}')

    applied = 0
    consecutive_fetch_errs = 0
    while applied < args.limit:
        try:
            page = http_get(f'{WP_BASE}/products?missing=ar_description&limit={args.batch_size}')
            consecutive_fetch_errs = 0
        except Exception as e:
            consecutive_fetch_errs += 1
            log(f'FETCH_ERR ({consecutive_fetch_errs}): {e}')
            if consecutive_fetch_errs >= 5:
                log('giving up after 5 consecutive fetch errors')
                break
            time.sleep(15)
            continue
        items = page.get('items', [])
        if not items:
            log('queue empty — done')
            break
        remaining = args.limit - applied
        batch = items[:remaining]
        log(f'batch: {len(batch)} products')
        if args.workers <= 1:
            results = [process_one(it, args.full, args.dry_run, log) for it in batch]
        else:
            with cf.ThreadPoolExecutor(max_workers=args.workers) as ex:
                results = list(ex.map(lambda it: process_one(it, args.full, args.dry_run, log), batch))
        applied_now = sum(1 for r in results if r['status'] == 'applied')
        applied += applied_now
        log(f'batch done: applied={applied_now}/{len(batch)}  cumulative={applied}/{args.limit}')
        if args.dry_run:
            break  # don't loop forever in dry-run, REST still returns same items
        # tiny pause for ollama recovery
        time.sleep(1)

    log(f'finished: applied={applied}')
    if log_fh:
        log_fh.close()


if __name__ == '__main__':
    main()
