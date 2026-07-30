import os
from PIL import Image

def convert_to_webp(directory, filename):
    file_path = os.path.join(directory, filename)
    if not os.path.exists(file_path):
        print(f"File not found: {file_path}")
        return

    output_path = os.path.join(directory, os.path.splitext(filename)[0] + "_optimized.webp")
    
    try:
        with Image.open(file_path) as img:
            img.save(output_path, "WEBP", quality=80)
            print(f"Converted {filename} to {os.path.basename(output_path)}")
            # print size difference
            original_size = os.path.getsize(file_path)
            new_size = os.path.getsize(output_path)
            print(f"Original: {original_size/1024:.2f}KB, New: {new_size/1024:.2f}KB")
    except Exception as e:
        print(f"Error converting {filename}: {e}")

assets_dir = r"c:\xampp\htdocs\myco_uk\assets\frontend\default-new\image"
convert_to_webp(assets_dir, "image_1.png")
convert_to_webp(assets_dir, "image_2.png")
