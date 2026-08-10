# UML — Layer7

**Pack:** [`pack-produto-layer7.md`](pack-produto-layer7.md) · **PRD:** [`prd-layer7.md`](prd-layer7.md) · **Catálogo:** [`catalogo-funcionalidades.md`](catalogo-funcionalidades.md)  
**Classificação:** Canónico · **Arquitectura:** [`../01-architecture/target-architecture.md`](../01-architecture/target-architecture.md)  
**Core:** [`../core/README.md`](../core/README.md)

> Mermaid de fronteiras e sequências. Não gera código. Reflecte o estado real.

---

## Índice

1. [Classes (visão lógica)](#1-classes-visão-lógica)
2. [Activação de licença](#2-activação-de-licença)
3. [Reportar erro (GUI)](#3-reportar-erro-gui)
4. [Classificação e enforcement](#4-classificação-e-enforcement)
5. [MITM opt-in (lab)](#5-mitm-opt-in-lab)
6. [Limites do modelo](#6-limites-do-modelo)

---

## 1. Classes (visão lógica)

```mermaid
flowchart TB
  subgraph GUI["Package GUI"]
    Pages["PHP pages + layer7.inc"]
    Report["error_report_* helpers"]
  end

  subgraph Store["Config store"]
    JSON["layer7.json"]
    LIC["layer7.lic"]
    BL["blacklists/"]
  end

  subgraph Runtime["Daemon + engines"]
    D["layer7d"]
    ND["nDPI"]
    Pol["Policy engine"]
  end

  subgraph Enforce["Enforcement"]
    PF["PF tables / rdr"]
  end

  subgraph Cloud["Externos"]
    LS["License server"]
    GH["GitHub Issues"]
  end

  subgraph Addon["Add-ons"]
    ID["Identity map"]
    MITM["tlsproxy MITM"]
  end

  Pages --> JSON
  Pages --> D
  Pages --> PF
  Report -.->|opt-in URL| GH
  D --> JSON
  D --> LIC
  D --> ND
  D --> Pol
  D --> PF
  D --> LS
  D --> ID
  Pages --> MITM
  MITM --> PF
  Pol --> PF
```

| Bloco | Código / docs |
|-------|----------------|
| Package GUI | `package/pfSense-pkg-layer7/files/...` |
| layer7d | `src/layer7d/` |
| License server | `license-server/` |
| MITM | ADR-0026 |
| Identity | ADR-0027 / 0029 |

---

## 2. Activação de licença

```mermaid
sequenceDiagram
    autonumber
    actor Admin
    participant GUI
    participant D as layer7d
    participant LS as License Server
    participant FS as Filesystem

    Admin->>GUI: Introduce license key
    GUI->>D: layer7d --activate KEY
    D->>D: hardware fingerprint
    D->>LS: POST /api/activate
    alt sucesso
        LS-->>D: .lic assinado
        D->>FS: grava layer7.lic
        GUI-->>Admin: Licença activa
    else rejeição
        LS-->>D: 4xx
        GUI-->>Admin: Falha (sem bind)
    end
```

---

## 3. Reportar erro (GUI)

Fluxo único: descrever → pré-visualizar metadados seguros → abrir GitHub **ou** copiar URL.

```mermaid
sequenceDiagram
    autonumber
    actor Op as Operador
    participant Diag as Diagnósticos
    participant Inc as layer7.inc
    participant GH as GitHub Issues

    Op->>Diag: abre Diagnósticos
    Diag->>Inc: safe_context (pkg/daemon/mode/…)
    Note over Inc: Sem .lic, chaves, logs, dumps, IPs
    Op->>Diag: descreve sintoma (opcional)
    Diag-->>Op: pré-visualiza metadados anexados
    alt Abrir issue
        Op->>Diag: Abrir issue no GitHub
        Diag->>Inc: issue_url(ctx, summary)
        Inc-->>Diag: URL issues/new?title&body
        Diag->>GH: redirect (opt-in)
        Note over Op,GH: Login GitHub → completar → Submit
    else Sem internet / fallback
        Op->>Diag: Copiar URL
        Diag-->>Op: mostra URL para colar noutro browser
    end
```

| Anexa | Nunca anexa |
|-------|-------------|
| Versão pacote / daemon | Ficheiro `.lic` |
| Daemon running/stopped | Chaves / senhas |
| enabled · mode · model | Logs brutos / dumps |
| Contagem de interfaces | Hostnames / IPs de clientes |
| Flag MITM (`off` / `configured_on`) | Regras PF completas |

Narrativa: [`pack-produto-layer7.md#como-funciona-reportar-erro`](pack-produto-layer7.md#como-funciona-reportar-erro).

---

## 4. Classificação e enforcement

```mermaid
sequenceDiagram
    autonumber
    participant Cli as Cliente LAN
    participant PF as PF
    participant Cap as layer7d
    participant ND as nDPI
    participant Pol as Policy
    participant Tab as PF tables

    Cli->>PF: fluxo
    PF->>Cap: captura (iface)
    Cap->>ND: classificar
    ND-->>Cap: app / categoria / host
    Cap->>Pol: match (+ Identity?)
    alt block
        Pol->>Tab: add destino
        Tab-->>PF: drop subsequente
    else monitor / allow / tag
        Pol-->>Cap: evento / tag
    end
```

---

## 5. MITM opt-in (lab)

Permanente **NO-GO** sem ficha nomeada + GO. Default OFF.

```mermaid
sequenceDiagram
    autonumber
    actor Op as Operador lab
    participant GUI as MITM GUI
    participant Inc as layer7.inc
    participant Proxy as tlsproxy
    participant PF as PF rdr

    Op->>GUI: source_cidr AND dest_cidr + janela
    Op->>GUI: Activate (só com GO/runbook)
    Inc->>Proxy: start / sync
    Inc->>PF: rdr scoped (proibido from any)
    Note over Inc,PF: failsafe · auto-disable · audit metadados
    Op->>GUI: Deactivate / expire / break-glass
    Inc->>PF: teardown
    Inc->>Proxy: stop
```

---

## 6. Limites do modelo

- Não modela cada ficheiro PHP/C — só fronteiras.
- Portal admin de licenças = UI do License Server (versão visual própria).
- Scripts de fleet SSH são operacionais externos (fora do runtime appliance).
