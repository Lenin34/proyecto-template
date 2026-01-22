# Symfony 7.2 Multi-Tenant Template

Esta es una plantilla maestra para aplicaciones Symfony 7.2 con arquitectura Multi-Tenant, Dockerizada y lista para producción.

## 🚀 Características

- **Arquitectura Multi-Tenant**: Soporte nativo para múltiples bases de datos por tenant + base de datos maestra.
- **Docker Full Stack**: MySQL 8, Symfony 7.2 (Apache/PHP 8.x), phpMyAdmin, Mailpit, Mercure.
- **Configuración Profesional**:
  - Entity Managers separados configuración dinámica en `doctrine.yaml`.
  - Scripts de entrada (`docker-entrypoint.sh`) optimizados para migraciones automáticas por tenant.
- **Seguridad**: JWT (LexikJWTAuthenticationBundle) preconfigurado.
- **Frontend**: Webpack Encore listo para usar.
- **CI/CD**: Workflow de GitHub Actions incluido.

## 📋 Requisitos

- Docker y Docker Compose
- Git
- Make (opcional, para usar el Makefile si decides agregarlo)

## 🛠️ Instalación rápida

1. **Clonar el repositorio**
   ```bash
   git clone <url-del-repo>
   cd proyecto-template
   ```

2. **Ejecutar setup inicial**
   ```bash
   ./setup.sh
   ```

3. **Configurar entorno**
   - Edita `.env` (para local)
   - Edita `.docker/env/docker.env` (para contenedores)

4. **Levantar aplicación**
   ```bash
   docker compose up -d --build
   ```

5. **Acceder**
   - Web: http://localhost:8000
   - phpMyAdmin: http://localhost:8080
   - Mailpit: http://localhost:8025

## 🏛️ Arquitectura Multi-Tenant

La aplicación está configurada para conectarse dinámicamente a diferentes bases de datos según el tenant.
La configuración reside en `config/packages/doctrine.yaml`.

Los tenants por defecto son:
- `master` (Base de datos central)
- `tenant_a`
- `tenant_b`
- `tenant_c`

Puedes agregar más tenants editando `doctrine.yaml` y las variables de entorno correspondientes.

## 📦 Estructura del Proyecto

- `.docker/`: Configuración de Docker.
- `config/`: Configuración de Symfony.
- `src/Entity/Master`: Entidades de la base de datos maestra.
- `src/Entity/App`: Entidades de los tenants.
- `.github/workflows`: Pipelines de CI/CD.

## �� Contribución

Si deseas contribuir, por favor crea un Fork y envía un Pull Request.

## 📄 Licencia

MIT
# proyecto-template
