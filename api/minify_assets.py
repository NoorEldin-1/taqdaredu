import re
import os

def minify_css(file_path):
    print(f"Minifying {file_path}...")
    try:
        with open(file_path, 'r', encoding='utf-8') as f:
            css = f.read()

        # Remove comments
        css = re.sub(r'/\*[\s\S]*?\*/', '', css)
        # Remove whitespace around braces/semicolons
        css = re.sub(r'\s*([\{\};,:])\s*', r'\1', css)
        # normalize whitespace
        css = re.sub(r'\s+', ' ', css)
        # remove spaces around selectors
        css = re.sub(r'\s*([>+~])\s*', r'\1', css)
        
        minified_path = file_path.replace('.css', '.min.css')
        if minified_path == file_path:
             minified_path = file_path + ".min.css"
             
        with open(minified_path, 'w', encoding='utf-8') as f:
            f.write(css)
            
        print(f"Saved minified file to {minified_path}")
        return minified_path
    except Exception as e:
        print(f"Error minifying {file_path}: {e}")
        return None

files_to_minify = [
    'assets/frontend/default-new/css/style.css',
    'assets/frontend/default-new/css/new-style.css',
    'assets/frontend/default-new/css/responsive.css',
    'assets/frontend/default-new/css/custom.css',
    'assets/frontend/default-new/css/h-2-carousel.css',
    'assets/frontend/default-new/css/nice-select.css',
    'assets/frontend/default-new/css/slick-theme.css',
    'assets/frontend/default-new/css/slick.css',
    'assets/frontend/default-new/css/rtl.css',
    'assets/global/tagify/tagify.css',
    'assets/global/toastr/toastr.css'
]

base_dir = os.getcwd()

for rel_path in files_to_minify:
    abs_path = os.path.join(base_dir, rel_path)
    if os.path.exists(abs_path):
        minify_css(abs_path)
    else:
        print(f"File not found: {abs_path}")
