import re
import os

def minify_js(file_path):
    print(f"Minifying {file_path}...")
    try:
        with open(file_path, 'r', encoding='utf-8') as f:
            js = f.read()

        # Safer JS Minification
        # Remove comment blocks (non-greedy)
        js = re.sub(r'/\*[\s\S]*?\*/', '', js)
        
        # Remove full line comments (ignoring indentation)
        # ^\s*//.*$
        js = re.sub(r'^\s*//.*$', '', js, flags=re.MULTILINE)
        
        # Normalize whitespace (safe-ish, preserve newlines to avoid breaking missing semicolons)
        # We replace multiple spaces with single space, but keep newlines for safety if user relied on ASI
        js = re.sub(r'[ \t]+', ' ', js)
        
        # Remove space around delimiters
        js = re.sub(r'\s*([\{\}\(\)\[\];,:])\s*', r'\1', js)
        
        minified_path = file_path.replace('.js', '.min.js')
        if minified_path == file_path:
             minified_path = file_path + ".min.js"
             
        with open(minified_path, 'w', encoding='utf-8') as f:
            f.write(js)
            
        print(f"Saved minified file to {minified_path}")
        return minified_path
    except Exception as e:
        print(f"Error minifying {file_path}: {e}")
        return None

files_to_minify = [
    'assets/frontend/default-new/js/script.js',
    'assets/frontend/default-new/js/berli.js',
    'assets/frontend/default-new/js/course.js',
    'assets/frontend/default-new/js/script-2.js',
    'assets/global/tagify/jquery.tagify.js'
]

base_dir = os.getcwd()

for rel_path in files_to_minify:
    abs_path = os.path.join(base_dir, rel_path)
    if os.path.exists(abs_path):
        minify_js(abs_path)
    else:
        print(f"File not found: {abs_path}")
