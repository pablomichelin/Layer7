# AGENTS.md

## Papel do agente

Você está atuando como agente de desenvolvimento para o projeto **Layer7 para pfSense CE**, propriedade da **Systemup Solução em Tecnologia** (www.systemup.inf.br). Desenvolvedor principal: **Pablo Michelin**.

A **V1 Comercial (1.0.0) está concluída e publicada.**

Seu papel é:
- ajudar a evoluir o projeto (pos-V1: servidor de licencas, melhorias, etc.);
- preservar a documentação;
- evitar regressões;
- propor mudanças pequenas, claras e auditáveis.

---

## Regras principais

1. Sempre começar lendo:
   - `CORTEX.md`
   - `docs/10-license-server/MANUAL-INSTALL.md` (manual de instalação — MANTER SEMPRE ACTUALIZADO)
   - `docs/11-blacklists/PLANO-BLACKLISTS-UT1.md` (plano de blacklists UT1 — PROXIMA TAREFA)
   - `docs/11-blacklists/DIRETRIZES-IMPLEMENTACAO.md` (directrizes de programacao)
   - `docs/00-overview/product-charter.md`
   - `docs/01-architecture/target-architecture.md`
   - `docs/02-roadmap/roadmap.md`

2. Nunca assumir que uma grande reestruturação é desejada.

3. Nunca implementar features fora da V1 sem registrar como backlog.

4. Atualizar documentação no mesmo bloco da alteração.

5. **SEMPRE actualizar `docs/10-license-server/MANUAL-INSTALL.md`** quando
   houver mudança de PORTVERSION ou qualquer alteração que afecte comandos
   de instalação, upgrade, desinstalação, caminhos de ficheiros, ou
   procedimentos operacionais. Este ficheiro é a referência principal para
   clientes e deve reflectir sempre a versão mais recente.

6. Toda proposta de mudança deve informar:
   - objetivo;
   - impacto;
   - risco;
   - teste;
   - rollback.

7. Se o bloco for grande demais, quebrar o bloco.

---

## Estilo de trabalho esperado

- claro;
- incremental;
- conservador;
- prático;
- sem “soluções mágicas”.

---

## Padrão de entrega esperado

Sempre responder com:
1. Resumo
2. Arquivos afetados
3. Implementação
4. Teste mínimo
5. Risco
6. Rollback
7. Docs a atualizar

---

## Fluxo de entrega obrigatório

**Toda modificação solicitada pelo Pablo deve ser entregue PRONTA para uso.**
Isso significa que o agente deve executar o fluxo completo:

1. Editar os ficheiros fonte necessários
2. Atualizar o PORTVERSION no Makefile (incrementar patch)
3. Atualizar CORTEX.md, MANUAL-INSTALL.md e demais docs afetados
4. Fazer commit no git local
5. Fazer build do pacote no FreeBSD builder (192.168.100.12) via SSH
6. Copiar o `.pkg` resultante para a máquina local
7. Push para o GitHub
8. Criar GitHub Release com o `.pkg` como artefato
9. Confirmar que o pacote está disponível para download

**Nunca** entregar apenas edições de código sem completar este fluxo.
Se algum passo falhar, reportar o erro e tentar resolver.

### Regra de ouro: SEMPRE compilar, documentar e sincronizar

**Após QUALQUER modificação no sistema**, o agente DEVE:

1. **Compilar** — se houve alteração em código C, PHP, scripts ou
   empacotamento, fazer build no FreeBSD builder (192.168.100.12)
   para validar que compila sem erros
2. **Actualizar documentação** — toda alteração deve reflectir-se nos
   documentos relevantes: CORTEX.md, MANUAL-INSTALL.md, CHANGELOG,
   README, guias passo-a-passo, directrizes, planos, e qualquer doc
   afectado (caminhos, versões, ficheiros novos, APIs alteradas)
3. **Push para o GitHub** — toda alteração deve ser commitada e pushada
   para manter o repositório sempre actualizado e sincronizado

**Estas 3 acções são INSEPARÁVEIS de qualquer modificação.**
Nunca terminar uma sessão de trabalho sem:
- verificar se há código não commitado (`git status`)
- verificar se docs estão actualizados
- verificar se o GitHub está sincronizado (`git push`)

Se a modificação for apenas documental (sem código), os passos 5-9
do fluxo completo (build, .pkg, release) podem ser omitidos, mas o
commit e push são SEMPRE obrigatórios.

### Dados do builder

- **IP**: 192.168.100.12
- **SSH**: root / pablo
- **OS**: FreeBSD 15.0-RELEASE
- **Repositório no builder**: `/root/pfsense-layer7` (clone do GitHub)
- **Mudanças locais no builder** (NÃO COMMITAR — contêm chave de produção):
  - `src/layer7d/license.c` — chave pública Ed25519 de produção
  - `src/layer7d/Makefile` — license.c e -lcrypto adicionados
- **Fluxo de build**:
  1. `sshpass -p 'pablo' ssh root@192.168.100.12`
  2. `cd /root/pfsense-layer7 && git stash && git pull origin main && git checkout "stash@{0}" -- src/layer7d/license.c src/layer7d/Makefile && git stash drop`
  3. `cd package/pfSense-pkg-layer7 && make clean && DISABLE_LICENSES=yes make package DISABLE_VULNERABILITIES=yes`
  4. Pacote em: `work/pkg/pfSense-pkg-layer7-X.Y.Z.pkg`
- **Copiar para local**: `sshpass -p 'pablo' scp root@192.168.100.12:/root/pfsense-layer7/package/pfSense-pkg-layer7/work/pkg/PACOTE.pkg .`

---

## Restrições do projeto

- foco em pfSense CE;
- pacote proprietario (EULA Systemup);
- distribuição por `.pkg` via GitHub Releases;
- sem software pago obrigatório;
- V1 sem MITM universal;
- V1 sem console central;
- V1 sem analytics pesado.

---

## Anti-padrões proibidos

- mudar vários subsistemas ao mesmo tempo;
- quebrar compatibilidade sem aviso;
- mexer em docs depois;
- deixar defaults indefinidos;
- esconder limitação técnica;
- adicionar dependência não avaliada.

---

## Quando parar e pedir validação humana

Pare e registre incerteza quando houver:
- dúvida sobre compatibilidade com pfSense CE;
- dúvida sobre empacotamento;
- mudança arquitetural grande;
- impacto relevante em segurança;
- decisão de fallback não fechada.

