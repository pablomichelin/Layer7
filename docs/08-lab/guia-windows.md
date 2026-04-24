# Guia Windows — legado, nao vigente

## Estado deste documento

Este ficheiro fica preservado apenas por compatibilidade historica ate uma
reorganizacao estrutural controlada em **F6**.

**Nao usar este documento como guia de desenvolvimento activo.**

O fluxo vigente do projecto Layer7 e:

1. editar ficheiros e documentacao no workspace local em **macOS**;
2. compilar e gerar `.pkg` apenas no **builder FreeBSD**;
3. instalar e validar comportamento real apenas no **pfSense appliance**;
4. tratar CI/Linux/Windows como apoio auxiliar, nunca como gate final.

## Porque o guia Windows foi desactivado

O projecto nao usa Windows como ambiente de desenvolvimento, build ou
validacao. As instrucoes antigas de WSL, PowerShell e smoke local criavam uma
ambiguidade operacional: pareciam oferecer um caminho valido para validar o
produto, mas o pacote depende de FreeBSD/pfSense.

Essa ambiguidade aumenta o risco de falso positivo e execucao no ambiente
errado. Por isso, este documento deixa de conter comandos operacionais.

## Fonte vigente

Para executar qualquer validacao tecnica, seguir:

- [`README.md`](README.md) — indice do laboratorio, SSOT de fluxo (CORTEX,
  roadmap, F4) e ligacao a [`deploy-github-lab`](../04-package/deploy-github-lab.md);
- [`builder-freebsd.md`](builder-freebsd.md) para o builder FreeBSD;
- [`../04-package/validacao-lab.md`](../04-package/validacao-lab.md) para build,
  pacote `.pkg`, instalacao e validacao no pfSense (inicio: *Gates oficiais F4*;
  roteiros **10a** / **10b** / **11** no appliance quando aplicavel);
- [`lab-topology.md`](lab-topology.md) para topologia do appliance/lab.

## Regra operacional

No macOS, o agente pode editar ficheiros, consultar `git status`, rever `diff`
e actualizar documentacao. Build, smoke tecnico, package e validacao funcional
devem acontecer no builder FreeBSD e no appliance pfSense, conforme o gate da
fase.
