#!/usr/bin/env python3
"""Fix ___PUBLICPATH___ placeholders left by a bad replace pass."""
import re, os

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))

def fix(content):
    changed = False

    def repl_redirect(m):
        return f"redirect(public_path('{m.group(1)}'))"
    c = content
    c2 = re.sub(r"redirect\('___PUBLICPATH___([^']+)'\)", repl_redirect, c)
    if c2 != c: changed = True; c = c2

    c2 = re.sub(
        r"\$_SERVER\['DOCUMENT_ROOT'\]\s*\.\s*'___PUBLICPATH___",
        "ROOT_PATH . '/public/",
        c
    )
    if c2 != c: changed = True; c = c2

    c2 = re.sub(r"'___PUBLICPATH___([^']+)'", r"public_path('\1')", c)
    if c2 != c: changed = True; c = c2

    c2 = re.sub(r'"___PUBLICPATH___([^"]+)"', r'public_path("\1")', c)
    if c2 != c: changed = True; c = c2

    c2 = re.sub(r'href="___PUBLICPATH___([^"]+)"', r'href="<?php echo public_path(\'\1\'); ?>"', c)
    if c2 != c: changed = True; c = c2

    c2 = re.sub(
        r"fetch\('___PUBLICPATH___([^']+)'",
        r"fetch(<?php echo json_encode(public_path('\1')); ?>",
        c
    )
    if c2 != c: changed = True; c = c2

    c2 = re.sub(r"var apiEndpoint\s*=\s*'___PUBLICPATH___([^']+)'",
                r"var apiEndpoint  = <?php echo json_encode(public_path('\1')); ?>", c)
    if c2 != c: changed = True; c = c2

    c2 = c.replace('___PUBLICPATH___', '/public/')
    if c2 != c: changed = True; c = c2

    return c, changed

for dp, _, fns in os.walk(ROOT):
    if '.git' in dp or 'vendor' in dp or 'scripts' in dp:
        continue
    for fn in fns:
        if not fn.endswith('.php'):
            continue
        fp = os.path.join(dp, fn)
        with open(fp, encoding='utf-8') as f:
            orig = f.read()
        if '___PUBLICPATH___' not in orig:
            continue
        new, ch = fix(orig)
        if ch:
            with open(fp, 'w', encoding='utf-8') as f:
                f.write(new)
            print(os.path.relpath(fp, ROOT))
