[English](./README.md) | [Português](./README.pt-BR.md)

 # <a href="https://devmasnaodev.github.io/cloud-engine-docs/" alt="Cloud Engine" target="_blank">
   <img src="./.github/assets/cloud-engine-logo-text.svg" alt="Cloud Engine" width="620">
 </p>
 </a>
 
 <p>
   <a href="https://devmasnaodev.github.io/cloud-engine-docs/">Documentation</a> •
   <a href="#getting-started">Getting Started</a>
 </p>

Cloud Engine is a developer-focused platform for managing WordPress VPS engines such as EasyEngine and WordOps. It is being built to centralize server provisioning, engine operations, and site lifecycle management behind a modern Laravel-based application.

## Read the documentation
https://devmasnaodev.github.io/cloud-engine-docs/

> [!WARNING]
> **Early-stage project:** Cloud Engine is still under active development and is **not ready for production use**. Use it only for testing, evaluation, and controlled environments.

## Getting Started

This project uses **DDEV** as the local development environment.

### Prerequisites

1. Install **Docker**.
2. Install **DDEV**.

### Development setup

1. Clone the repository.
2. Start the DDEV environment:
   ```bash
   ddev start
   ```
3. Install project dependencies and bootstrap the app:
   ```bash
   ddev composer run setup
   ```
4. Start the development services:
   ```bash
   ddev composer run dev
   ```
5. Open the application:
   ```bash
   ddev launch
   ```

### Useful development commands

```bash
ddev composer run dev:ssr
ddev artisan test
ddev exec npm run lint
ddev exec npm run types
```

## Documentation

Project documentation is maintained in a dedicated repository:

**https://github.com/devmasnaodev/cloud-engine-docs**

Use that repository for broader architecture notes, guides, and future operational documentation.

## Contributing

Contributions, feedback, and early testing are welcome. For now, prefer small, focused changes and keep development aligned with the current architecture and DDEV workflow.
