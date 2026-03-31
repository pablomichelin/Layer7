## Layer7 v1.6.4 — Auto-start do daemon após reboot do pfSense

Pacote Layer 7 para pfSense CE com classificacao em tempo real via nDPI.

### Correção crítica

- **Daemon não reiniciava após reboot** — o serviço layer7d parava com o shutdown mas não voltava a iniciar automaticamente quando o pfSense reiniciava
- **rc.d fix**: dependência `REQUIRE: LOGIN` (inexistente no pfSense) alterada para `REQUIRE: DAEMON NETWORKING`
- **resync hook**: nova função `layer7_ensure_daemon_running()` garante que o daemon é iniciado durante o boot do pfSense mesmo se o mecanismo rc.d falhar

### Instalacao (um comando)

```sh
fetch -o /tmp/install.sh https://raw.githubusercontent.com/pablomichelin/Layer7/main/install.sh && sh /tmp/install.sh
```

### Rollback

```sh
pkg delete pfSense-pkg-layer7
```

### Compatibilidade

- pfSense CE 2.7.x / 2.8.x / 25.x
- FreeBSD 14 / 15
