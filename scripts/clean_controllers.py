import os
import re

def clean_controllers(directory):
    count_files = 0
    count_lines = 0
    
    print(f"Iniciando limpieza en: {directory}")
    
    for root, dirs, files in os.walk(directory):
        for file in files:
            if file.endswith(".php"):
                file_path = os.path.join(root, file)
                
                with open(file_path, 'r', encoding='utf-8') as f:
                    lines = f.readlines()
                
                new_lines = []
                modified = False
                
                for line in lines:
                    # Patrón para detectar setCurrentTenant
                    if '->setCurrentTenant(' in line and ('$this->tenantManager' in line or '$tenantManager' in line):
                        print(f"  [Eliminando] {file}: {line.strip()}")
                        modified = True
                        count_lines += 1
                        continue
                    
                    new_lines.append(line)
                
                if modified:
                    with open(file_path, 'w', encoding='utf-8') as f:
                        f.writelines(new_lines)
                    count_files += 1

    print(f"\nResumen:")
    print(f"Archivos modificados: {count_files}")
    print(f"Líneas eliminadas: {count_lines}")

if __name__ == "__main__":
    target_dir = "/home/masoftcode/Github/app-ctm/src/Controller"
    clean_controllers(target_dir)
