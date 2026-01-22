#!/bin/bash

# Check if we're on main branch
if [ "$(git rev-parse --abbrev-ref HEAD)" = "main" ]; then
    echo "Deploying to production..."
    
    # Copy .env.prod to .env if it exists
    if [ -f .env.prod ]; then
        cp .env.prod .env
        echo "Production environment file copied successfully"
    else
        echo "Error: .env.prod file not found"
        exit 1
    fi
    
    # Create and set permissions for critical directories
    echo "Setting up directory permissions..."
    
    # Array of directories that need specific permissions
    directories=(
        "var/cache"
        "var/log"
        "public/uploads"
        "public/build"
        "var/sessions"
    )
    
    # Create directories and set permissions
    for dir in "${directories[@]}"; do
        if [ ! -d "$dir" ]; then
            mkdir -p "$dir"
            echo "Created directory: $dir"
        fi
        chmod -R 777 "$dir"
        echo "Set permissions for: $dir"
    done
    
    # Set proper ownership if running as root (common in deployment scenarios)
    # Replace www-data:www-data with your web server user:group if different
    if [ "$(id -u)" = "0" ]; then
        chown -R www-data:www-data .
        echo "Updated ownership of files to www-data"
    fi
    
    # Additional deployment steps
    composer install --no-dev --optimize-autoloader

    # Clear and set proper permissions for cache and logs
    php bin/console cache:clear --env=prod
    php bin/console cache:warmup --env=prod

    # Database migrations for multi-tenant architecture
    echo "Running database migrations for all tenants..."

    # Array of tenant entity managers
    tenant_managers=("ts" "rs" "SNT" "issemym")

    # Run migrations for Master database
    echo "Migrating Master database..."
    php bin/console doctrine:migrations:migrate --em=Master --no-interaction --env=prod || {
        echo "Warning: Master migrations failed or no migrations to execute"
    }

    # Run migrations for each tenant
    for tenant in "${tenant_managers[@]}"; do
        echo "Migrating tenant: $tenant..."
        php bin/console doctrine:migrations:migrate --em=$tenant --no-interaction --env=prod || {
            echo "Warning: Migrations for tenant $tenant failed or no migrations to execute"
        }
    done

    echo "Database migrations completed!"

    # Final permission adjustment for cache and logs after Symfony commands
    chmod -R 775 var/cache var/log

    echo "Deployment completed successfully!"
else
    echo "Not on main branch, using development environment"
fi 