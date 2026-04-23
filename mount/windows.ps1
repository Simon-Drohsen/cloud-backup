$ErrorActionPreference = "Stop"

function Fail-WithMessage {
    param(
        [string]$Message
    )
    Write-Host $Message
    exit 0
}

if ((Test-Path "vendor") -and -not (Test-Path ".env.local")) {
    Fail-WithMessage "File '.env.local' does not exist. Please create it before running this script."
}

if (-not (Test-Path "var/config/admin_system_settings/admin_system_settings.yaml")) {
    Fail-WithMessage "File 'var/config/admin_system_settings/admin_system_settings.yaml' does not exist. Please create it before running this script."
}

if (-not (Test-Path "var/config/system_settings/system_settings.yaml")) {
    Fail-WithMessage "File 'var/config/system_settings/system_settings.yaml' does not exist. Please create it before running this script."
}

docker compose up -d
docker compose exec php composer install --prefer-dist --no-security-blocking
docker compose exec php php vendor/bin/pimcore-install --no-interaction
docker compose exec php php bin/console pimcore:bundle:install PimcoreAdminBundle --no-post-change-commands
docker compose exec php php bin/console pimcore:bundle:install PimcoreApplicationLoggerBundle --no-post-change-commands
docker compose exec php php bin/console pimcore:bundle:install PimcoreSimpleBackendSearchBundle --no-post-change-commands
docker compose exec php php bin/console pimcore:bundle:install PimcoreTinymceBundle --no-post-change-commands
docker compose down
docker compose up -d
corepack enable
yarn install
yarn dev
