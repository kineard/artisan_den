.PHONY: dev start stop help check-db seed

# Default port
PORT ?= 8001

# Check and start PostgreSQL if needed
check-db:
	@echo "Checking PostgreSQL..."
	@if ! pg_isready -h localhost -U postgres >/dev/null 2>&1; then \
		echo "Starting PostgreSQL..."; \
		sudo service postgresql start 2>/dev/null || sudo systemctl start postgresql 2>/dev/null || true; \
		sleep 2; \
		echo "PostgreSQL started."; \
	else \
		echo "PostgreSQL is already running."; \
	fi

# Start development server
dev: check-db
	@echo "Starting PHP development server on port $(PORT)..."
	@php -S localhost:$(PORT) -t .

# Alias for dev
start: dev

# Stop server (if running in background)
stop:
	@echo "Stopping PHP server..."
	@pkill -f "php -S localhost:$(PORT)" || echo "No PHP server running on port $(PORT)"

# Seed database with sample data
seed: check-db
	@echo "Seeding database with sample data..."
	@php seed-data.php

# Migrate inventory schema updates
migrate-inventory: check-db
	@echo "Running inventory schema updates..."
	@PGPASSWORD=artisan_pass_123 psql -h localhost -U artisan_user -d artisan_den -f database/migrate-inventory-updates.sql

# Migrate inventory schema updates
migrate-inventory: check-db
	@echo "Running inventory schema updates..."
	@PGPASSWORD=artisan_pass_123 psql -h localhost -U artisan_user -d artisan_den -f database/migrate-inventory-updates.sql || echo "Migration may have already run"

# Fix schema and seed inventory data (recommended)
seed-inventory: check-db
	@echo "Fixing schema and seeding inventory data..."
	@php fix-and-seed.php

# Show help
help:
	@echo "Available commands:"
	@echo "  make dev           - Start PostgreSQL (if needed) and PHP development server on port $(PORT)"
	@echo "  make start         - Alias for 'make dev'"
	@echo "  make stop          - Stop the PHP development server"
	@echo "  make seed          - Seed database with sample KPI data"
	@echo "  make seed-inventory - Seed database with inventory, products, and vendors"
	@echo "  make help          - Show this help message"
	@echo ""
	@echo "To use a different port: PORT=8080 make dev"
