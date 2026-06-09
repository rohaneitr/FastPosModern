import os

def replace_in_files(directory, replacements, ext='.tsx'):
    for root, dirs, files in os.walk(directory):
        for file in files:
            if file.endswith(ext) or file.endswith('.ts'):
                filepath = os.path.join(root, file)
                with open(filepath, 'r', encoding='utf-8', errors='ignore') as f:
                    content = f.read()
                
                changed = False
                for old_str, new_str in replacements.items():
                    if old_str in content:
                        content = content.replace(old_str, new_str)
                        changed = True
                        
                if changed:
                    with open(filepath, 'w', encoding='utf-8') as f:
                        f.write(content)
                    print(f"Updated: {filepath}")

if __name__ == '__main__':
    base_dir = r'c:\Users\Rohan\Desktop\FastPosModern\client\src'
    
    replacements = {
        "@/components/profile/ProfileSettings": "@/features/iam/components/ProfileSettings",
        "@/components/ImpersonationGuard": "@/features/iam/components/ImpersonationGuard"
    }
    
    replace_in_files(base_dir, replacements)
