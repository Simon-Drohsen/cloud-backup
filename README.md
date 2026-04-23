# Cloud Backup

This project is based on Pimcore/Symfony and is developed locally with Docker.

## Requirements

- Docker Desktop (including Docker Compose)
- Node.js `>= 22` (see `package.json`)
- Corepack enabled (project uses Yarn `4.5.0`)

```bash
corepack enable
```

## macOS/Linux Setup

1. Clone the project.
2. Run the setup script.
3. Continue with the General section below (hosts entry and development).

```bash
git clone <REPO-URL> cloud-backup
cd cloud-backup
chmod +x mount/mac_linux.sh
./mount/mac_linux.sh
```

## Windows Setup

Use PowerShell for the setup script.

1. Clone the project.
2. Run the Windows setup script from PowerShell.
3. Continue with the General section below (hosts entry and development).

```powershell
git clone <REPO-URL> cloud-backup
cd cloud-backup
./mount/windows.ps1
```

If PowerShell blocks the script, you may need to change the execution policy:

```powershell
Set-ExecutionPolicy -Scope Process -ExecutionPolicy Bypass
./mount/windows.ps1
```

## General

### Hosts entry and HTTPS (required)

The application must be accessed via `https://cloud-backup.dev.local`.
HTTP is redirected to HTTPS.

Add this line to your hosts file:

```text
127.0.0.1 cloud-backup.dev.local
```

- macOS/Linux: `/etc/hosts`
- Windows: `C:\Windows\System32\drivers\etc\hosts` (edit as Administrator)

### Development

```bash
yarn watch
```

Use this for a one-time asset build:

```bash
yarn dev
```

### Useful URLs

- [Application](https://cloud-backup.dev.local)
- [Adminer (DB UI)](http://localhost:2001)
- [Mailpit (Mail UI)](http://localhost:8025)

### Useful commands

```bash
docker compose down
docker compose up -d
yarn phpstan
yarn ecs
```

Note: setup scripts expect these files to exist:
`.env.local`,
`var/config/admin_system_settings/admin_system_settings.yaml`,
`var/config/system_settings/system_settings.yaml`.
