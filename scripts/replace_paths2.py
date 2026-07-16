#!/usr/bin/env python3
import re, os

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
SKIP = {'.git', 'vendor', 'node_modules'}

def process(content, filepath):
    if 'paths.php' in filepath or 'replace_paths' in filepath:
        return content, 0
    n = 0
    def sub(m):
        nonlocal n
        n += 1
        q = m.group(1)
        path = m.group(2)
        return f"public_path({q}{path}{q})"
    new = re.sub(r"""(['"])/Curlz/public/([^'"]*)\1""", sub, content)
    # header Location with concatenation after path
    def subh(m):
        nonlocal n
        n += 1
        path = m.group(1)
        tail = m.group(2)
        return f"header('Location: ' . public_path('{path}'){tail})"
    new = re.sub(
        r"header\(\s*'Location:\s*/Curlz/public/([^'?]+)(\?[^']*)?'(\s*\.\s*[^)]+)\s*\)",
        subh, new
    )
    def subh2(m):
        nonlocal n
        n += 1
        full = m.group(1)
        return f"header('Location: ' . public_path('{full}'))"
    new = re.sub(
        r"header\(\s*'Location:\s*/Curlz/public/([^']+?)'\s*\)",
        subh2, new
    )
    # href="/Curlz/public/..."
    def suba(m):
        nonlocal n
        n += 1
        path = m.group(1)
        extra = m.group(2) or ''
        if extra.startswith('?') and '<?php' in extra:
            # href="/Curlz/public/foo?x=<?php echo ... ?>"
            base = path
            return f'href="<?php echo public_path(\'{base}\'); ?>?{extra[1:]}"'
        return f'href="<?php echo public_path(\'{path}{extra}\'); ?>"'
    new = re.sub(r'href="/Curlz/public/([^"?]+)(\?[^"]*)?"', suba, new)
    # redirect()
    new2, c = re.subn(r"redirect\(\s*'/Curlz/public/([^']*)'\s*\)", r"redirect(public_path('\1'))", new)
    n += c
    new = new2
    # define BASE_URL
    new2, c = re.subn(
        r"define\(\s*'BASE_URL'\s*,\s*'/Curlz/public'\s*\)",
        "define('BASE_URL', rtrim(public_path(''), '/'))", new)
    n += c
    return new2, n

total = 0
for dp, dns, fns in os.walk(ROOT):
    dns[:] = [d for d in dns if d not in SKIP]
    for fn in fns:
        if not fn.endswith('.php'): continue
        fp = os.path.join(dp, fn)
        with open(fp, encoding='utf-8') as f: orig = f.read()
        if '/Curlz/' not in orig: continue
        new, n = process(orig, fp)
        if new != orig:
            with open(fp, 'w', encoding='utf-8') as f: f.write(new)
            print(os.path.relpath(fp, ROOT), n)
            total += 1
print('files:', total)
