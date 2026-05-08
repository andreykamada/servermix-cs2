# ServerMIX CS2

Sistema completo para servidor Counter-Strike 2 MIX 5v5 com website integrado, sistema de skins, plugins customizados e integração Steam.

---

## Features

- Sistema de skins integrado
- Website conectado ao servidor
- Integração Steam OpenID
- Sistema de Loadout TR/CT
- Plugin X1 Draft MIX
- MatchZy integrado
- Sistema de ranking futuro
- API futura para estatísticas
- Estrutura preparada para React futuramente

---

## Tecnologias Utilizadas

### Website
- PHP
- JavaScript
- CSS
- MySQL
- Steam API

### Servidor CS2
- CounterStrikeSharp
- Metamod
- MatchZy
- WeaponPaints
- SimpleAdmin

### Desenvolvimento
- Git
- GitHub
- VS Code
- XAMPP

---

# Estrutura do Projeto

```txt
servermix-cs2-rep/
│
├── api/                # APIs futuras do website/ranking
├── cs2-plugins/        # Plugins do servidor CS2
├── database/           # Estrutura SQL e backups
├── docs/               # Documentação
├── website/            # Website principal
│
└── README.md
```

---

# Website

Estrutura principal do website:

```txt
website/htdocs/
│
├── css/
├── js/
├── img/
├── imports/
├── pages/
├── src/
├── translation/
└── tools/
```

---

# Plugins Utilizados

## Plugins principais

- MatchZy
- WeaponPaints
- PlayerSettings
- MenuManagerCore
- CS2-SimpleAdmin

---

# Plugin Customizado

## X1 Draft Plugin

Plugin próprio desenvolvido para:

- Escolha automática/manual de capitães
- X1 entre capitães
- Sistema de picks de players
- X1 entre os players escolhidos com pick e ban do capitão
- Sistema de bans de mapas
- Changelevel do mapa escolhido automaticamente
- Integração via HUD do MenuManagerCore
- Integração futura com ranking

---

# Configuração Local

## Requisitos

- PHP 8+
- MySQL/MariaDB
- XAMPP
- SteamCMD
- .NET SDK
- CounterStrikeSharp

---

# Estrutura Git

Branches principais:

```txt
main = produção estável
dev = desenvolvimento principal
feature/* = funcionalidades isoladas
```

---

# Futuras Implementações

- Ranking competitivo
- API REST
- React frontend parcial
- Dashboard administrativo
- Estatísticas de partidas
- Histórico de jogadores
- Integração MatchZy API

---

# Autor

Andrei Kamada

---

# Licença

Projeto privado.
