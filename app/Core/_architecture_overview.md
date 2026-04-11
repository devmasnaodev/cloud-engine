# Cloud Engine - Core Architecture Overview

## Architecture Pattern

This project follows **Domain-Driven Design (DDD)** principles with a clean architecture approach.

## Directory Structure

```
app/Core/
├─ Provisioning/          # Server provisioning engines (EasyEngine, etc.)
├─ Management/            # Server management domain (recipes, tasks, etc.)
├─ Servers/               # Server connection and execution domain
├─ Security/              # Security and credentials management
└─ Drivers/               # Infrastructure drivers (SSH, etc.)
```

## Communication Flow

```
Controller → Executor → Normalizer → Engine → SSHDriver → Remote VPS
                ↓
           CommandAuditLog
```

## Key Principles

1. **Direct SSH Connection**: Laravel connects directly to VPS via SSH (no agents)
2. **Security First**: All credentials encrypted at rest, decrypted only in-memory
3. **Command Safety**: All commands sanitized via CommandNormalizer and escapeshellarg
4. **Audit Trail**: Every command execution logged with full details
5. **Engine Abstraction**: Support for multiple provisioning engines via interface

## Default Stack

- **Provisioning Engine**: EasyEngine
- **SSH Library**: phpseclib
- **Encryption**: Laravel's native encryption
- **Audit Storage**: Database (CommandAuditLog)

## Extension Points

- Add new engines by implementing `ProvisioningEngineInterface`
- Add new drivers by following the SSHDriver pattern
- Extend audit logging via CommandAuditLog
