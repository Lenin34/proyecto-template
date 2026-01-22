#!/usr/bin/env python3
"""
Script para verificar que todos los FormTypes estén correctamente configurados para multi-tenant
"""

import os
import re

def verificar_formtype(file_path):
    """Verificar un FormType específico"""
    print(f"\n🔍 Verificando {os.path.basename(file_path)}...")
    
    with open(file_path, 'r', encoding='utf-8') as f:
        content = f.read()
    
    # Verificar si usa EntityType
    uses_entity_type = 'EntityType::class' in content
    
    if not uses_entity_type:
        print(f"   ✅ No usa EntityType - OK")
        return True
    
    print(f"   📋 Usa EntityType - verificando configuración...")
    
    # Verificar si tiene TenantManager inyectado
    has_tenant_manager_import = 'use App\\Service\\TenantManager;' in content
    has_tenant_manager_property = 'private TenantManager $tenantManager;' in content
    has_tenant_manager_constructor = 'public function __construct(TenantManager $tenantManager)' in content
    
    print(f"   - Import TenantManager: {'✅' if has_tenant_manager_import else '❌'}")
    print(f"   - Property TenantManager: {'✅' if has_tenant_manager_property else '❌'}")
    print(f"   - Constructor TenantManager: {'✅' if has_tenant_manager_constructor else '❌'}")
    
    # Verificar si todos los EntityType tienen 'em' configurado
    entity_type_blocks = re.findall(r'->add\([^,]+,\s*EntityType::class,\s*\[(.*?)\]', content, re.DOTALL)
    
    all_have_em = True
    for i, block in enumerate(entity_type_blocks):
        has_em = "'em' =>" in block
        print(f"   - EntityType #{i+1} tiene 'em': {'✅' if has_em else '❌'}")
        if not has_em:
            all_have_em = False
    
    # Resultado final
    is_correct = (has_tenant_manager_import and has_tenant_manager_property and 
                  has_tenant_manager_constructor and all_have_em)
    
    print(f"   - Estado: {'✅ CORRECTO' if is_correct else '❌ NECESITA CORRECCIÓN'}")
    
    return is_correct

def main():
    print("🔧 VERIFICACIÓN DE FORMTYPES - CONFIGURACIÓN MULTI-TENANT")
    print("=" * 60)
    
    form_dir = 'src/Form'
    
    if not os.path.exists(form_dir):
        print(f"❌ Directorio {form_dir} no encontrado")
        return False
    
    # Obtener todos los archivos PHP en src/Form
    form_files = []
    for file in os.listdir(form_dir):
        if file.endswith('.php'):
            form_files.append(os.path.join(form_dir, file))
    
    print(f"📁 Encontrados {len(form_files)} FormTypes")
    
    results = []
    
    # Verificar cada FormType
    for form_file in sorted(form_files):
        result = verificar_formtype(form_file)
        results.append((os.path.basename(form_file), result))
    
    # Resumen final
    print("\n" + "=" * 60)
    print("📋 RESUMEN DE VERIFICACIÓN")
    print("=" * 60)
    
    total_forms = len(results)
    correct_forms = sum(1 for _, result in results if result)
    
    print(f"✅ FormTypes correctos: {correct_forms}/{total_forms}")
    
    if correct_forms < total_forms:
        print(f"\n⚠️  FormTypes que necesitan corrección:")
        for filename, result in results:
            if not result:
                print(f"   - {filename}")
    
    if correct_forms == total_forms:
        print("\n🎉 ¡TODOS LOS FORMTYPES ESTÁN CORRECTAMENTE CONFIGURADOS!")
        print("\n📝 Configuración multi-tenant completada:")
        print("   ✅ TenantManager inyectado en todos los FormTypes que lo necesitan")
        print("   ✅ EntityManager del tenant correcto configurado en EntityType")
        print("   ✅ Consultas de base de datos apuntan al tenant correcto")
    else:
        print(f"\n🔧 Faltan {total_forms - correct_forms} FormTypes por corregir")
    
    return correct_forms == total_forms

if __name__ == "__main__":
    success = main()
    exit(0 if success else 1)
