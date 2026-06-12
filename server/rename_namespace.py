import os
import sys

def replace_in_files(directory, old_str, new_str, ext='.php'):
    for root, dirs, files in os.walk(directory):
        for file in files:
            if file.endswith(ext):
                filepath = os.path.join(root, file)
                with open(filepath, 'r', encoding='utf-8', errors='ignore') as f:
                    content = f.read()
                
                if old_str in content:
                    content = content.replace(old_str, new_str)
                    with open(filepath, 'w', encoding='utf-8') as f:
                        f.write(content)
                    print(f"Updated: {filepath}")

if __name__ == '__main__':
    base_dir = r'c:\Users\Rohan\Desktop\FastPosModern\server'
    dirs_to_check = ['app', 'database', 'routes', 'config', 'bootstrap']
    
    for d in dirs_to_check:
        path = os.path.join(base_dir, d)
        if os.path.exists(path):
            replace_in_files(path, 'App\\Domain\\IAM', 'App\\Modules\\IAM')
            # Also replace forward slash usages if any in routes/configs
            replace_in_files(path, 'App/Domain/IAM', 'App/Modules/IAM')
