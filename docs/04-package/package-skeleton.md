# Esqueleto do pacote (Bloco 5 — repositório)

## Objetivo

Conter no Git os **artefactos** de um port pfSense-style para `pfSense-pkg-layer7`, preparados para **validação posterior** no lab.

## Localização

`package/pfSense-pkg-layer7/`

## O que existe no código (não confundir com “validado no pfSense”)

- Makefile com `do-build` / `do-install`, `pkg-plist`, `pkg-descr`, `LICENSE`.
- Ficheiros sob `files/`: XML, PHP informativo, rc.d, sample JSON, priv stub, hooks.
- O binário `layer7d` é **produzido no build** a partir de `src/layer7d/main.c`.

## O que falta para afirmar “pacote OK no pfSense”

Toda a secção de evidência em **[`validacao-lab.md`](validacao-lab.md)** (build, `pkg add`, serviço, URL/menu, logs, remove).

## Riscos

- Versão do pfSense CE / FreeBSD pode exigir ajustes após a primeira corrida de validação.
- Registo na GUI depende do POST-INSTALL (`rc.packages`) — só comprovável no appliance.

## Próximo passo

Executar **`validacao-lab.md`** no builder e no pfSense lab; colar outputs. **Sem novas features** até esse gate.
