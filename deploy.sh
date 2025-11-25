#!/bin/bash

# Social Media Platform Production Deployment Script
set -e

echo "🚀 Starting production deployment..."

# Check if .env file exists
if [ ! -f "backend/.env" ]; then
    echo "❌ .env file not found. Please create it from .env.example"
    exit 1
fi

# Build and start containers
echo "📦 Building Docker containers..."
docker-compose down
docker-compose build --no-cache
docker-compose up -d

# Wait for MySQL to be ready
echo "⏳ Waiting for MySQL to be ready..."
sleep 30

# Run database migrations
echo "🗄️ Running database migrations..."
docker-compose exec app php artisan migrate --force

# Clear and cache configuration
echo "⚙️ Optimizing application..."
docker-compose exec app php artisan config:cache
docker-compose exec app php artisan route:cache
docker-compose exec app php artisan view:cache
docker-compose exec app php artisan event:cache

# Generate application key if not set
echo "🔑 Checking application key..."
docker-compose exec app php artisan key:generate --force

# Set proper permissions
echo "🔒 Setting permissions..."
docker-compose exec app chown -R www-data:www-data /var/www/html/storage
docker-compose exec app chown -R www-data:www-data /var/www/html/bootstrap/cache

# Run queue workers
echo "⚡ Starting queue workers..."
docker-compose exec -d app php artisan queue:restart

# Health check
echo "🏥 Running health check..."
sleep 10
if curl -f http://localhost/api/system/health > /dev/null 2>&1; then
    echo "✅ Application is healthy and running!"
else
    echo "❌ Health check failed. Check logs:"
    docker-compose logs app
    exit 1
fi

echo "🎉 Deployment completed successfully!"
echo "📊 Access your application at: http://localhost"
echo "📈 System dashboard: http://localhost/system"

# Show running containers
echo "🐳 Running containers:"
docker-compose ps