
import os
import requests
from PIL import Image

# Define paths
BASE_DIR = r'c:\xampp\htdocs\myco_uk'
ASSETS_IMG_DIR = os.path.join(BASE_DIR, 'assets', 'frontend', 'default-new', 'image')
SYSTEM_UPLOAD_DIR = os.path.join(BASE_DIR, 'uploads', 'system')
THUMBNAIL_DIR = os.path.join(BASE_DIR, 'uploads', 'thumbnails', 'course_thumbnails')
OPTIMIZED_THUMB_DIR = os.path.join(THUMBNAIL_DIR, 'optimized')
USER_IMG_DIR = os.path.join(BASE_DIR, 'uploads', 'user_image')
OPTIMIZED_USER_IMG_DIR = os.path.join(USER_IMG_DIR, 'optimized')

# Ensure directories exist
for d in [OPTIMIZED_THUMB_DIR, OPTIMIZED_USER_IMG_DIR, ASSETS_IMG_DIR]:
    os.makedirs(d, exist_ok=True)

def resize_and_convert(input_path, output_path, max_width, format='WEBP', quality=85):
    try:
        if not os.path.exists(input_path):
            print(f"Skipping (not found): {input_path}")
            return False
            
        with Image.open(input_path) as img:
            # Resize if bigger than max_width
            if img.width > max_width:
                ratio = max_width / img.width
                new_height = int(img.height * ratio)
                img = img.resize((max_width, new_height), Image.Resampling.LANCZOS)
                #print(f"Resizing to {max_width}x{new_height}")
            
            # Convert to RGB (handle PNG transparency or CMYK)
            if img.mode in ('RGBA', 'LA') or (img.mode == 'P' and 'transparency' in img.info):
                # For WEBP we can keep alpha, but for JPEG we need white background
                if format.upper() == 'JPEG':
                     background = Image.new('RGB', img.size, (255, 255, 255))
                     # Handle P mode with transparency
                     if img.mode == 'P':
                         img = img.convert('RGBA')
                     background.paste(img, mask=img.split()[3] if len(img.split()) > 3 else None)
                     img = background
                else: 
                     # Ensure valid mode for WebP
                     img = img.convert('RGBA')
            elif img.mode == 'CMYK':
                img = img.convert('RGB')
            
            img.save(output_path, format=format, quality=quality)
            print(f"Saved: {os.path.basename(output_path)}")
            return True
    except Exception as e:
        print(f"Error processing {input_path}: {e}")
        return False

# 1. User Images
print("--- Optimizing User Images ---")
for filename in os.listdir(USER_IMG_DIR):
    if filename.lower().endswith(('.jpg', '.jpeg', '.png')):
        file_path = os.path.join(USER_IMG_DIR, filename)
        # Create WebP in optimized folder
        name, _ = os.path.splitext(filename)
        webp_path = os.path.join(OPTIMIZED_USER_IMG_DIR, f"{name}.webp")
        resize_and_convert(file_path, webp_path, 220, 'WEBP')
        # Also ensure JPG optimized exists for fallback
        jpg_path = os.path.join(OPTIMIZED_USER_IMG_DIR, f"{name}.jpg")
        resize_and_convert(file_path, jpg_path, 220, 'JPEG')

# 2. Course Thumbnails (Correcting gaps)
print("--- Optimizing Course Thumbnails ---")
# Strategy: Look at ALL files in THUMBNAIL_DIR. 
# Also look at files in OPTIMIZED_DIR that are JPG but missing WebP.
all_files = set(os.listdir(THUMBNAIL_DIR))
for filename in all_files:
    if filename.lower().endswith(('.jpg', '.jpeg', '.png')):
        file_path = os.path.join(THUMBNAIL_DIR, filename)
        name, _ = os.path.splitext(filename)
        
        # We need to match the naming convention PHP expects.
        # PHP expects: optimized/NAME.webp
        # But wait, looking at the file list in step 79:
        # course_thumbnail_default-new_121758193033.jpg exists in optimized/
        # course_thumbnail_default-new_121758193033.webp exists in optimized/
        # So we should take the file from source, and save to optimized/[fullname].webp
        
        # Note: the filename in source usually IS the full name including timestamp etc.
        # So just saving as [filename with extension replaced]
        
        target_webp = os.path.join(OPTIMIZED_THUMB_DIR, f"{name}.webp")
        if not os.path.exists(target_webp):
             resize_and_convert(file_path, target_webp, 400, 'WEBP')
             
        # Also ensure JPG exists
        target_jpg = os.path.join(OPTIMIZED_THUMB_DIR, filename) # or f"{name}.jpg" usually source is .jpg
        if not os.path.exists(target_jpg):
             resize_and_convert(file_path, target_jpg, 400, 'JPEG')

# 3. System Images (Banner & Logo)
print("--- Optimizing System Images ---")
# Specifically target the big banner found in logs: bd97d45...
# And the logo f9bd58e...
for filename in os.listdir(SYSTEM_UPLOAD_DIR):
    if filename.lower().endswith(('.jpg', '.jpeg', '.png')):
        file_path = os.path.join(SYSTEM_UPLOAD_DIR, filename)
        if os.path.getsize(file_path) > 50 * 1024: # > 50KB or just all?
            # Create _optimized.webp version
            name, _ = os.path.splitext(filename)
            output_webp = os.path.join(SYSTEM_UPLOAD_DIR, f"{name}_optimized.webp")
            resize_and_convert(file_path, output_webp, 1920, 'WEBP')
            
            # Create mobile version for responsive usage
            output_mobile = os.path.join(SYSTEM_UPLOAD_DIR, f"{name}_mobile.webp")
            resize_and_convert(file_path, output_mobile, 600, 'WEBP')

print("Optimization Complete.")
