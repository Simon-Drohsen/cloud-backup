#!/usr/bin/env bash

if [ -d "vendor/" ] && [ ! -f ".env.local" ]
then
  echo "File '.env.local' does not exist, please create it before running this script."
  exit 0
fi

if [ ! -f "var/config/admin_system_settings/admin_system_settings.yaml" ]
then
  echo "File 'var/config/admin_system_settings/admin_system_settings.yaml' does not exist, please create it before running this script."
  exit 0
fi

if [ ! -f "var/config/system_settings/system_settings.yaml" ]
then
  echo "File 'var/config/system_settings/system_settings.yaml' does not exist, please create it before running this script."
  exit 0
fi

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
