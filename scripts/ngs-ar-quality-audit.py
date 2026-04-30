#!/usr/bin/env python3
"""
v1.37.4a — AR-content quality audit (read-only).

Walks every product carrying _ng_ar_title or _ng_ar_description, flags
script-bleed issues (Cyrillic / Greek / CJK / Hangul / Devanagari, and
Latin-glued-to-Arabic mid-word). Writes JSON to /tmp/ngs-pricer/ar-audit.json.

No writes. Safe to run anytime.

Usage:
  ./ngs-ar-quality-audit.py
  ./ngs-ar-quality-audit.py --output /tmp/ngs-pricer/ar-audit.json
"""

import argparse
import json
import re
import subprocess
import sys

SSH_HOST = 'fsalmansour@162.254.39.146'
SSH_PORT = '21098'
WP_PATH = '/home/fsalmansour/neogen.store'

ISSUE_RES = [
    ('cyrillic',  re.compile(r'[Ѐ-ӿ]')),
    ('greek',     re.compile(r'[Ͱ-Ͽ]')),
    ('cjk_han',   re.compile(r'[一-鿿]')),
    ('hiragana',  re.compile(r'[぀-ゟ]')),
    ('katakana',  re.compile(r'[゠-ヿ]')),
    ('hangul',    re.compile(r'[가-힯]')),
    ('devanagari',re.compile(r'[ऀ-ॿ]')),
    ('latin_glued_ar', re.compile(r'[A-Za-z][؀-ۿ]|[؀-ۿ][A-Za-z]')),
]

def run_query(sql):
    cmd = [
        'ssh', '-p', SSH_PORT, SSH_HOST,
        f"cd {WP_PATH} && wp db query \"{sql}\" --skip-plugins=litespeed-cache --skip-themes 2>&1 | /usr/bin/tail -n +2",
    ]
    r = subprocess.run(cmd, capture_output=True, text=True, timeout=60)
    out = r.stdout
    # strip mysql deprecation header line
    out = '\n'.join(l for l in out.splitlines() if not l.startswith('mysql:') and not l.startswith('** WARN') and not l.startswith('** This'))
    return out


def find_issues(text):
    if not text: return []
    found = []
    for name, regex in ISSUE_RES:
        if regex.search(text):
            found.append(name)
    return found


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument('--output', default='/tmp/ngs-pricer/ar-audit.json')
    args = ap.parse_args()

    print('Fetching AR titles…', flush=True)
    titles_raw = run_query(
        "SELECT p.ID, COALESCE(pmt.meta_value,''), COALESCE(pms.meta_value,'')"
        " FROM wpud_posts p"
        " LEFT JOIN wpud_postmeta pmt ON pmt.post_id=p.ID AND pmt.meta_key='_ng_ar_title'"
        " LEFT JOIN wpud_postmeta pms ON pms.post_id=p.ID AND pms.meta_key='_ng_ar_title_source'"
        " WHERE p.post_type='product' AND p.post_status IN ('publish','draft')"
    )
    titles = {}
    for line in titles_raw.splitlines():
        if not line.strip() or '\t' not in line: continue
        cols = line.split('\t')
        if len(cols) >= 3 and cols[0].isdigit():
            titles[int(cols[0])] = {'ar': cols[1], 'source': cols[2]}

    print('Fetching AR descriptions…', flush=True)
    descs_raw = run_query(
        "SELECT p.ID, COALESCE(pmd.meta_value,''), COALESCE(pms.meta_value,'')"
        " FROM wpud_posts p"
        " LEFT JOIN wpud_postmeta pmd ON pmd.post_id=p.ID AND pmd.meta_key='_ng_ar_description'"
        " LEFT JOIN wpud_postmeta pms ON pms.post_id=p.ID AND pms.meta_key='_ng_ar_description_source'"
        " WHERE p.post_type='product' AND p.post_status IN ('publish','draft')"
    )
    descs = {}
    for line in descs_raw.splitlines():
        if not line.strip() or '\t' not in line: continue
        cols = line.split('\t')
        if not cols[0].isdigit(): continue
        pid = int(cols[0])
        # AR body may legitimately contain tabs; rejoin everything except first + last
        if len(cols) >= 3:
            descs[pid] = {'ar': '\t'.join(cols[1:-1]), 'source': cols[-1]}
        elif len(cols) == 2:
            descs[pid] = {'ar': cols[1], 'source': ''}

    flagged_t, flagged_d, all_pids = [], [], set(titles) | set(descs)
    by_issue = {}
    for pid in sorted(all_pids):
        t = titles.get(pid, {})
        d = descs.get(pid, {})
        # Skip manual sources
        if t.get('source') == 'manual':
            t_issues = []
        else:
            t_issues = find_issues(t.get('ar', ''))
        if d.get('source') == 'manual':
            d_issues = []
        else:
            d_issues = find_issues(d.get('ar', ''))
        for iss in t_issues:
            by_issue.setdefault(iss, set()).add(pid)
        for iss in d_issues:
            by_issue.setdefault(f'desc_{iss}', set()).add(pid)
        if t_issues:
            flagged_t.append({'pid': pid, 'source': t.get('source',''), 'ar': t.get('ar',''), 'issues': t_issues})
        if d_issues:
            flagged_d.append({'pid': pid, 'source': d.get('source',''), 'ar_excerpt': d.get('ar','')[:200], 'issues': d_issues})

    out = {
        'totals': {
            'products_with_ar_title': len(titles),
            'products_with_ar_desc': len(descs),
            'flagged_titles': len(flagged_t),
            'flagged_descriptions': len(flagged_d),
        },
        'by_issue': {k: len(v) for k, v in by_issue.items()},
        'flagged_titles': flagged_t,
        'flagged_descriptions': flagged_d,
    }
    open(args.output, 'w').write(json.dumps(out, ensure_ascii=False, indent=2))
    print('\n=== AUDIT SUMMARY ===')
    print(json.dumps(out['totals'], indent=2))
    print('\n=== ISSUES BY TYPE ===')
    for k, v in sorted(out['by_issue'].items(), key=lambda x: -x[1]):
        print(f'  {k}: {v}')
    print(f'\nWrote {args.output}')

if __name__ == '__main__':
    main()
