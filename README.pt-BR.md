[English](./README.md) | [Português](./README.pt-BR.md)

 # <a href="https://devmasnaodev.github.io/cloud-engine-docs/" alt="Cloud Engine" target="_blank">
   <img src="./.github/assets/cloud-engine-logo-text.svg" alt="Cloud Engine" width="620">
 </p>
 </a>
 
 <p>
   <a href="https://devmasnaodev.github.io/cloud-engine-docs/">Documentação</a> •
   <a href="#comece-aqui">Comece aqui</a>
 </p>

Cloud Engine é uma plataforma voltada para desenvolvedores para gerenciar engines WordPress em VPS, como EasyEngine e WordOps. O projeto está sendo construído para centralizar provisionamento de servidores, operações das engines e o ciclo de vida dos sites em uma aplicação moderna baseada em Laravel.

> [!WARNING]
> **Projeto em estágio inicial:** o Cloud Engine ainda está em desenvolvimento ativo e **não está pronto para uso em produção**. Utilize apenas para testes, avaliação e ambientes controlados.

## Comece aqui

Este projeto usa **DDEV** como ambiente local de desenvolvimento.

### Pré-requisitos

1. Instale o **Docker**.
2. Instale o **DDEV**.

### Configuração do ambiente

1. Clone o repositório.
2. Inicie o ambiente DDEV:

   ```bash
   ddev start
   ```
3. Instale as dependências do projeto e faça o bootstrap da aplicação:

   ```bash
   ddev composer run setup
   ```
4. Inicie os serviços de desenvolvimento:

   ```bash
   ddev composer run dev
   ```
5. Abra a aplicação:

   ```bash
   ddev launch
   ```

### Simular o ambiente de produção ou uso local

   ```bash
   ddev exec npm run build && ddev artisan optimize && ddev artisan queue:work --tries=3 --timeout=90
   ```

### Comandos úteis para desenvolvimento

```bash
ddev composer run dev:ssr
ddev artisan test
ddev exec npm run lint
ddev exec npm run types
```

## Documentação

A documentação do projeto é mantida em um repositório dedicado:

**https://github.com/devmasnaodev/cloud-engine-docs**

Use esse repositório para documentação mais ampla de arquitetura, guias e futuras instruções operacionais.

## Contribuindo

Contribuições, feedbacks e testes iniciais são bem-vindos. Neste momento, prefira mudanças pequenas e objetivas, sempre alinhadas com a arquitetura atual e com o fluxo de desenvolvimento via DDEV.
