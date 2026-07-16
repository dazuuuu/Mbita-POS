#!/usr/bin/env python3
"""Fix href=public_path(...) missing PHP echo tags."""
import re, os

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))

def fix(content):
    orig = content
    content = re.sub(
        r'href=public_path\("([^"]*)"\)',
        r'href="<?php echo public_path(\'\1\'); ?>"',
        content
    )
    content = re.sub(
        r"href=public_path\('([^']*)'\)",
        r"href=\"<?php echo public_path('\\1'); ?>\"",
        content
    )
    return content, content != orig

for dp, dns, fns in os.walk(ROOT):
    dns[:] = [d for d in dns if d not in {'.git', 'vendor', 'node_modules', 'scripts'}]
    for fn in fns:
        if not fn.endswith('.php'):
            continue
        fp = os.path.join(dp, fn)
        with open(fp, encoding='utf-8') as f:
            o = f.read()
        if 'href=public_path' not in o:
            continue
        n, ch = fix(o)
        if ch:
            with open(fp, 'w', encoding='utf-8') as f:
                f.write(n)
            print(os.path.relpath(fp, ROOT))
