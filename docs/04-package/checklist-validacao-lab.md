# Checklist executável — validação lab `pfSense-pkg-layer7`

Marque só após **evidência** (output colado em [`validacao-lab.md`](validacao-lab.md)).

**F4 (BG-009 / BG-010 / BG-011):** além desta lista curta, use o *Índice dos
roteiros F4* em `validacao-lab` (secções **10a**, **10b**, **11**) e a matriz
em [`../tests/test-matrix.md`](../tests/test-matrix.md) (testes **3.8**,
**12.1–12.2**, **6.7**) quando precisar de critério de fecho de trilha com o
[checklist mestre](../02-roadmap/checklist-mestre.md).

- [ ] **1.** Build: `make package` completa sem erro (builder FreeBSD)
- [ ] **2.** Artefacto `.pkg` presente no disco (caminho anotado)
- [ ] **3.** `pkg add ./pfSense-pkg-layer7-….pkg` sem erro no pfSense
- [ ] **4.** `pkg info pfSense-pkg-layer7` mostra metadados esperados
- [ ] **5.** `pkg info -l pfSense-pkg-layer7` lista ficheiros (incl. `sbin/layer7d`)
- [ ] **6.** `service layer7d onestart` executado com sucesso
- [ ] **7.** `service layer7d status` indica running (ou equivalente verificável)
- [ ] **8.** `ps auxww | grep layer7d` mostra processo
- [ ] **9.** Logs de sistema contêm `daemon_start` (layer7d)
- [ ] **10.** URL `https://<IP>/packages/layer7/layer7_status.php` abre sem erro PHP
- [ ] **11.** Menu **Services** → entrada Layer7: **OK** ou **NOK** (anotar)
- [ ] **12.** `pkg delete pfSense-pkg-layer7` remove pacote sem erro crítico

**Próximo passo após todos marcados:** fechar gate no `CORTEX.md` com data e versão pfSense.
