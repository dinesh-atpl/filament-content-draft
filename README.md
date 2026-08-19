# Filament Content Draft

Auto-save draft and recovery plugin for Filament 5.x.

## Installation

```bash
composer require konectar/filament-content-draft
php artisan vendor:publish --tag="content-draft-config"
php artisan vendor:publish --tag="content-draft-migrations"
php artisan migrate
