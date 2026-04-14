# Runbook - F3.11 Input Triage

## Finalidade

Este runbook governa a triagem de cada entrega dos cinco insumos externos da
F3.11.

Este runbook **nao** substitui o checklist live:

- aqui decide-se se a entrega recebida e valida ou nao;
- no checklist live valida-se o ambiente depois de os insumos estarem em
  verde.

Nota de actualizacao em `2026-04-14`:

- este runbook fica preservado para compatibilidade com o pacote antigo de
  cinco insumos;
- nao usar este runbook para reabrir host live, PostgreSQL, auth/admin ou
  inventario ja saneados;
- para a rodada corrente, triar apenas evidencia nova que afecte `DR-05` ou
  drift novo objectivo.

---

## 1. Ordem obrigatoria de triagem

### 1. Conferir o que chegou primeiro

1. identificar qual dos cinco insumos foi entregue;
2. abrir um registo novo baseado em
   [`f3-11-evidence-intake-template.md`](f3-11-evidence-intake-template.md);
3. confirmar data/hora, origem, responsavel e artefactos recebidos;
4. confirmar se a entrega veio com output bruto ou acesso verificavel.

Se estes quatro pontos falharem, classificar logo como `entregue invalido`.

### 2. Validar o minimo aceitavel do insumo

Aplicar o criterio objectivo correspondente:

| Insumo | O que conferir primeiro | Como decidir que e valido |
|--------|--------------------------|----------------------------|
| host live | SSH/read-only ou output bruto de host, directorio, Git e compose | existe prova suficiente da stack observada, sem adivinhacao |
| PostgreSQL live | identidade da base e queries read-only de schema | existe prova do schema e das tabelas administrativas no ambiente observado |
| credencial admin | owner, escopo formal e login real | existe sessao valida no fluxo oficial e escopo documentado |
| appliance pfSense | SSH, baseline e controlos do lab | existe baseline completa e controlos legitimos de snapshot/relogio/offline/NIC/UUID |
| inventario `LIC-A` a `LIC-F` | artefacto preenchido e prova objectiva em backend | o inventario bate com o backend e reserva licencas reais por cenario |

### 3. Rejeitar com base objectiva quando necessario

Rejeitar de imediato se houver qualquer um destes casos:

- placeholder ainda presente;
- print sem comando ou query de origem;
- ausencia de owner ou escopo formal;
- output incompleto que nao prova o minimo canónico;
- divergencia entre o que foi declarado e o que foi observado;
- evidencia antiga sem timestamp ou sem contexto de execucao.

### 4. Registar o resultado

No registo de intake, preencher:

- comandos ou verificacoes executadas;
- resultado observado;
- classificacao final: `aceito`, `rejeitado` ou `parcial`;
- risco remanescente;
- proximo passo.

Depois actualizar a matriz de aceite com:

- `nao entregue`
- `entregue invalido`
- `entregue parcial`
- `entregue valido`

### 5. Decidir se o insumo ja libera a proxima etapa

Um insumo so e suficiente quando:

- satisfaz todo o minimo canónico do seu dominio;
- tem evidencia bruta verificavel;
- tem aceite registado;
- nao deixa ambiguidade operacional critica para a readiness.

Se faltar um detalhe estrutural, o insumo continua a bloquear.

### 6. Tratar "detalhe pequeno" como blocker quando ele for critico

Os pontos abaixo parecem pequenos, mas continuam a bloquear a F3.11:

- credencial admin sem escopo formal;
- host live sem directorio real provado;
- query DB sem identidade da base;
- appliance sem snapshot/restore legitimo;
- inventario sem prova cruzada no backend.

---

## 2. Regra de classificacao rapida

| Situacao observada | Classificacao correcta | Accao |
|--------------------|------------------------|-------|
| nada chegou | `nao entregue` | manter blocker e cobrar entrega |
| chegou algo, mas nao prova o minimo | `entregue invalido` | rejeitar com causa objectiva |
| chegou parte relevante, mas falta elemento critico | `entregue parcial` | pedir complemento e nao liberar readiness |
| o minimo canónico foi satisfeito integralmente | `entregue valido` | registar aceite e fechar o subgate |

---

## 3. Criterio final de suficiencia

### O insumo e suficiente para a readiness quando:

1. o intake esta completo;
2. a matriz marca `entregue valido`;
3. nao existe ambiguidade critica residual;
4. o resultado e reproduzivel por outro operador.

### O insumo continua a bloquear a F3.11 quando:

1. a prova depende de interpretacao livre;
2. a evidencia nao esta ligada ao ambiente observado;
3. falta owner responsavel;
4. falta output bruto ou acesso verificavel;
5. o resultado ainda nao suporta o checklist live.

---

## 4. Leitura final deste runbook

- este runbook decide aceite/rejeicao da entrega;
- este runbook nao substitui a readiness;
- a regra antiga dos cinco insumos fica preservada apenas como
  compatibilidade;
- no estado corrente, a F3.11 esta alinhada no license-server live e o
  blocker real remanescente e `DR-05`.
