# Push → live deploy (TalkAI Pilot)

## Flow

1. You push to `main` on GitHub  
2. GitHub Actions SSHs to the VPS  
3. VPS runs `git pull` (hard reset to `main`) and reloads services  
4. Live site updates: https://talkaipilot.com / https://mcp.talkaipilot.com

Secrets (`database.php`, `.env`) stay on the server and are **not** overwritten.

## One-command local deploy (Windows)

Shop (PHP):

```powershell
cd C:\xampp\htdocs\mcp
.\scripts\deploy.ps1 "your commit message"
```

MCP server (Node):

```powershell
cd C:\xampp\htdocs\mcp-server
.\scripts\deploy.ps1 "your commit message"
```

Deploy only (no commit/push):

```powershell
.\scripts\deploy.ps1 -DeployOnly
```

Uses SSH key `%USERPROFILE%\.ssh\mcp_vps` → `root@89.116.21.3`.

## GitHub Actions secrets (both repos)

Repo → Settings → Secrets and variables → Actions:

| Secret | Value |
|--------|--------|
| `VPS_HOST` | `89.116.21.3` |
| `VPS_USER` | `root` |
| `VPS_SSH_KEY` | full private key contents of `mcp_vps` |
| `VPS_PORT` | `22` (optional) |

Add the same secrets to:
- https://github.com/VELRK/mcp
- https://github.com/VELRK/mcp-server

## Server scripts

- `/usr/local/bin/deploy-mcp-shop.sh`
- `/usr/local/bin/deploy-mcp-server.sh`
