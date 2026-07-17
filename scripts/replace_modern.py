#!/usr/bin/env python3
import re, os
ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
SKIP = {'.git', 'vendor', 'node_modules', 'scripts'}

def go(content):
    n = 0
    def sub(m):
        nonlocal n; n += 1
        q, p = m.group(1), m.group(2)
        return f"public_path({q}{p}{q})"
    for prefix in ('/Curlz/public/', '/Modern/public/'):
        content = content.replace(prefix, '___PUBLICPATH___')
    # restore via public_path - do direct replace
    content2 = re.sub(r"___PUBLICPATH___([^\s'\"<>]+)", lambda m: f"<?php echo public_path('{m.group(1)}'); ?>" if False else f"' + public_path('{m.group(1)}') + '", content)
    # simpler: replace /Modern/public/X with public_path('X') in quotes
    content, c = re.subn(r"'/Modern/public/([^']*)'", r"public_path('\1')", content)
    n += c
    content, c = re.subn(r'"/Modern/public/([^"]*)"', r'public_path("\1")', content)
    n += c
    # DOCUMENT_ROOT paths -> ROOT_PATH
    content, c = re.subn(
        r"\$_SERVER\['DOCUMENT_ROOT'\]\s*\.\s*'/Modern/public/",
        "ROOT_PATH . '/public/",
        content
    )
    n += c
    content, c = re.subn(
        r"\$_SERVER\['DOCUMENT_ROOT'\]\s*\.\s*'/Curlz/public/",
        "ROOT_PATH . '/public/",
        content
    )
    n += c
    return content, n

for dp, dns, fns in os.walk(ROOT):
    dns[:] = [d for d in dns if d not in SKIP]
    for fn in fns:
        if not fn.endswith('.php'): continue
        fp = os.path.join(dp, fn)
        if 'paths.php' in fp: continue
        with open(fp, encoding='utf-8') as f: o = f.read()
        if '/Modern/' not in o: continue
        n, _ = go(o)
        with open(fp, 'w', encoding='utf-8') as f: f.write(n)
        print(os.path.relpath(fp, ROOT))
