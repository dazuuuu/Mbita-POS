#!/usr/bin/env python3
"""Replace hardcoded /Curlz/public/... paths with public_path() calls."""
import re
import os

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
SKIP_DIRS = {'.git', 'vendor', 'node_modules'}

# Match quoted /Curlz/public/... strings
PATTERN = re.compile(r'''(['"])/Curlz/public/([^'"]*)\1''')

def transform(content: str) -> tuple[str, int]:
    count = 0
    def repl(m):
        nonlocal count
        count += 1
        path = m.group(2)
        quote = m.group(1)
        return f"public_path({quote}{path}{quote})"
    return PATTERN.sub(repl, content), count

def main():
    total = 0
    files_changed = 0
    for dirpath, dirnames, filenames in os.walk(ROOT):
        dirnames[:] = [d for d in dirnames if d not in SKIP_DIRS]
        for fn in filenames:
            if not fn.endswith('.php'):
                continue
            fp = os.path.join(dirpath, fn)
            rel = os.path.relpath(fp, ROOT)
            if rel == 'scripts/replace_paths.py':
                continue
            with open(fp, 'r', encoding='utf-8') as f:
                orig = f.read()
            new, n = transform(orig)
            if n:
                with open(fp, 'w', encoding='utf-8') as f:
                    f.write(new)
                print(f"  {rel}: {n} replacements")
                total += n
                files_changed += 1
    print(f"\nDone: {total} replacements in {files_changed} files")

if __name__ == '__main__':
    main()
